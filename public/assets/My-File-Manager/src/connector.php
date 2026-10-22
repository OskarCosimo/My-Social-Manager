<?php
/**
 * My File Manager - Main Connector
 * Handles authentication, routing, tenant isolation and security
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */
header('X-Requested-With: XMLHttpRequest');

require_once __DIR__ . '/myfilemanager.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/chunkuploader.php';

// Start session for token management and user identification
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// CORS handling
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

/**
 * Format bytes to readable string (e.g. 52428800 -> "50MB")
 */
function formatBytes($bytes, $precision = 0) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . $units[$pow];
}

/**
 * Parse size string with units (e.g., "1GB", "500MB", "2G") to bytes.
 */
function parseFileSize($size, $defaultInBytes) {
    if (empty($size)) return $defaultInBytes;
    
    $size = trim((string)$size);
    
    // If it's pure numbers, assume Megabytes if it's a small number, else Bytes
    if (is_numeric($size)) {
        $val = (float)$size;
        return $val < 10000 ? (int)($val * 1048576) : (int)$val;
    }
    
    // Match standard and short suffixes
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB|PB|K|M|G|T|P|KIB|MIB|GIB|TIB|PIB)?$/i', $size, $matches)) {
        $value = (float)$matches[1];
        $unit = isset($matches[2]) ? strtoupper($matches[2]) : 'B';
        
        switch ($unit) {
            case 'PB': case 'P': case 'PIB': $value *= 1024;
            case 'TB': case 'T': case 'TIB': $value *= 1024;
            case 'GB': case 'G': case 'GIB': $value *= 1024;
            case 'MB': case 'M': case 'MIB': $value *= 1024;
            case 'KB': case 'K': case 'KIB': $value *= 1024;
            case 'B': default: break;
        }
        
        return (int)$value;
    }
    
    return $defaultInBytes;
}

try {
    // 1. AUTHENTICATION & DATABASE FETCH
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Authentication required. Please log in.', 401);
    }
    
    $userId = $_SESSION['user_id'];
    
    require_once __DIR__ . '/../../../../app/Core/Database.php'; 
    $db = Database::getInstance();
    
    // Fetch limits from user's current plan
    $stmt = $db->prepare("
        SELECT u.id, p.max_storage_size, p.max_upload_size 
        FROM users u 
        LEFT JOIN plans p ON u.plan_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception('User record not found in database.', 401);
    }

    // 2. TENANT ISOLATION (Dedicated User Folder)
    $userFolder = 'user_' . $userData['id'];
    $PATHTOFILES = __DIR__ . '/../files/' . $userFolder . '/';
    
    if (!is_dir($PATHTOFILES)) {
        if (!@mkdir($PATHTOFILES, 0775, true)) {
            $error = error_get_last();
            throw new Exception("Unable to create user folder at: " . $PATHTOFILES . " - Permission denied.", 500);
        }
    }

    // Convert strings to bytes, setting safe defaults (1GB storage, 50MB upload)
    $storageQuotaBytes = parseFileSize($userData['max_storage_size'] ?? '', 1073741824);
    $uploadLimitBytes = parseFileSize($userData['max_upload_size'] ?? '', 52428800);

    // 3. BUILD DYNAMIC CONFIGURATION
    $config = [
        'rootPath' => $PATHTOFILES,
        'rootUrl' => '', 
        
        'maxQuota' => $storageQuotaBytes,
        'maxFileSize' => $uploadLimitBytes,
        'uploadMaxSize' => $uploadLimitBytes, // Extra safety for some FM versions

        'readOnlyFolders' => ['.trash', 'system', 'config'], 
        'protectedFolders' => ['.trash', 'uploads', 'backup'], 
        'quotaExcludedFolders' => ['backup'], 
        'uploadBlockedFolders' => ['backup'], 
        
        'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*', 'application/pdf', 'text/plain'],
        'banExtensions' => [
            'sh', 'bash', 'pl', 'py', 'exe', 'bat', 'cmd', 'php', 'phtml', 
            'asp', 'aspx', 'jsp', 'js', 'vbs', 'app', 'dmg', 'htaccess', 'docm'
        ],

        'extensionRestrictions' => [
            'log' => ['read' => true, 'write' => false, 'open' => false, 'delete' => false],
            'txt' => ['read' => true, 'write' => true, 'open' => true, 'delete' => true],
            'pdf' => ['read' => true, 'write' => false, 'open' => true, 'delete' => true],
        ],
        'defaultExtensionPermissions' => [
            'read' => true, 'write' => true, 'open' => true, 'delete' => true
        ],
        
        'disabledOperations' => [],
        'chunkSize' => 1048576, // 1MB chunks
        
        'enableTrash' => true,
        'trashPath' => $PATHTOFILES . '.trash/', 
        
        'security' => [
            'enableTokenAuth' => false,
            'tokenSecret' => base64_encode(hash('sha512', 'user123').hash('sha256', 'user123')),
            'enableIPWhitelist' => false,
            'allowedIPs' => [],
        ],

        'plugins' => [
            'clamav' => ['enabled' => false, 'clamdscan_path' => '/usr/bin/clamdscan'], 
            'rate_limiter' => ['enabled' => false], 
            'video_editor' => ['enabled' => false, 'ffmpeg_path' => '/usr/bin/ffmpeg'],
            'ftpplugin' => ['enabled' => false],
            'publiclinks' => ['enabled' => false]
        ],
        
        'authCallback' => function() use ($userData, $storageQuotaBytes) {
            return [
                'id' => $userData['id'],
                'username' => 'User_' . $userData['id'],
                'quota' => $storageQuotaBytes,
                'permissions' => ['read', 'write', 'delete', 'upload', 'download', 'rename', 'copy', 'move', 'mkdir', 'search', 'quota', 'info']
            ];
        }
    ];

    $plugins = [];
    $plugin_dir = __DIR__ . '/plugins/';
    if (is_dir($plugin_dir)) {
        foreach (glob($plugin_dir . '*.php') as $plugin_file) {
            require_once $plugin_file;
            $plugin_name = basename($plugin_file, '.php');
            $class_name = $plugin_name . 'Plugin';
            
            if (isset($config['plugins'][$plugin_name]) && 
                ($config['plugins'][$plugin_name]['enabled'] ?? false) &&
                class_exists($class_name)) {
                try {
                    $plugins[$plugin_name] = new $class_name($config);
                } catch (Exception $e) {
                    error_log("Failed to load plugin {$plugin_name}: " . $e->getMessage());
                }
            }
        }
    }
    $config['plugins'] = $plugins;
    
    $user = call_user_func($config['authCallback']);
    if ($user === false) {
        throw new Exception('Authentication execution failed', 401);
    }

    if (!is_dir($config['trashPath'])) {
        mkdir($config['trashPath'], 0755, true);
    }
    
    $cmd = $_POST['cmd'] ?? $_GET['cmd'] ?? 'open';
    while (ob_get_level()) ob_end_clean();

    // Command interceptors (Public Links, Video Process, Upload)
    if (strpos($cmd, 'publiclink_') === 0) {
        $result = $config['plugins']['publiclinks']->handleCommand($cmd, $_REQUEST, $user);
        echo json_encode($result);
        exit;
    }

    if ($cmd === 'upload') {
        if (in_array($cmd, $config['disabledOperations'])) throw new Exception('Operation not allowed', 403);
        $uploadFile = $_FILES['upload'] ?? null;
        
        $targetPath = $_POST['target'] ?? '';
        if (!empty($targetPath)) {
            $relativePath = base64_decode($targetPath);
            $targetPath = $config['rootPath'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        } else {
            $targetPath = $config['rootPath'];
        }

        // Hard validation: Check if file exceeds the Plan's Max Upload Size
        $fileSize = $uploadFile['size'] ?? 0;
        if ($fileSize > $config['maxFileSize']) {
            http_response_code(413); // Payload Too Large
            echo json_encode([
                'error' => 'File too large! Your plan allows a maximum of ' . formatBytes($config['maxFileSize']) . ' per file.',
                'code' => 413
            ]);
            exit;
        }

        $fm = new MyFileManager($config, $user);
        $quota = $fm->getQuota();
        if ($quota && $quota['total'] > 0 && $quota['free'] < $fileSize) {
            http_response_code(507);
            echo json_encode(['error' => 'Storage Quota exceeded.', 'code' => 507]);
            exit;
        }

        $uploader = new ChunkUploader([
            'maxFileSize' => $config['maxFileSize'],
            'allowedMimeTypes' => $config['allowedMimeTypes'],
            'banExtensions' => $config['banExtensions']
        ]);

        $result = $uploader->handleUpload($uploadFile, $targetPath, $_POST);
        echo json_encode($result);
        exit;
    }

    if ($cmd === 'download') {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');
        while (@ob_end_clean()); 
    }

    if (in_array($cmd, $config['disabledOperations'])) throw new Exception('Operation not allowed', 403);
    
    // DEFAULT EXECUTION
    $fm = new MyFileManager($config, $user);
    $response = $fm->execute($cmd, $_REQUEST);
    
    // --- UI DISPLAY OVERRIDE ---
    // Intercept the JSON response and replace the PHP Server Limit (6G) with the User's Plan Limit
    if (is_array($response)) {
        $formattedLimit = formatBytes($uploadLimitBytes);
        
        array_walk_recursive($response, function(&$value, $key) use ($uploadLimitBytes, $formattedLimit) {
            // Find keys like 'uploadMaxSize', 'upload_max_filesize', etc.
            if (stripos((string)$key, 'upload') !== false && stripos((string)$key, 'max') !== false) {
                // If it expects bytes, pass bytes. If it expects a string, pass the formatted "MB" string.
                $value = is_numeric($value) ? $uploadLimitBytes : $formattedLimit;
            }
        });
    }
    
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $rawCode = $e->getCode();
    error_log("MyFileManager Exception: " . $e->getMessage() . " | Code: " . $rawCode);
    $httpCode = is_numeric($rawCode) ? (int)$rawCode : 500;
    if ($httpCode < 100 || $httpCode > 599) $httpCode = 500;
    
    http_response_code($httpCode);
    echo json_encode(['error' => $e->getMessage(), 'code' => $rawCode]);
    exit;
}