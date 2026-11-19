<?php
// public/assets/My-File-Manager/src/connector.php

/**
 * My File Manager - Main Connector
 *
 * Main entry point for all file operations
 * Handles session authentication, database plan quotas, tenant isolation and security
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

header('X-Requested-With: XMLHttpRequest');

require_once __DIR__ . '/myfilemanager.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/chunkuploader.php';

// Start session for token management and user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json; charset=utf-8');

/**
 * Format bytes to readable string (e.g. 52428800 -> "50MB")
 */
function formatBytes($bytes, $precision = 0): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . $units[$pow];
}

/**
 * Parse size string with units (e.g., "200MB", "10GB") to bytes
 */
function parseFileSize($size, int $defaultInBytes = 0): int {
    if (empty($size)) return $defaultInBytes;
    if (is_numeric($size)) {
        $val = (float)$size;
        return $val < 10000 ? (int)($val * 1048576) : (int)$val;
    }

    $size = trim((string)$size);

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
    // 1. AUTHENTICATION & DATABASE PLAN FETCH
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Authentication required. Please log in.', 401);
    }

    $userId = (int)$_SESSION['user_id'];

    require_once __DIR__ . '/../../../../app/Core/Database.php'; 
    $db = Database::getInstance();

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

    // 2. TENANT ISOLATION (Dedicated User Directory)
    $userFolder = 'user_' . $userData['id'];
    $PATHTOFILES = __DIR__ . '/../files/' . $userFolder . '/';

    if (!is_dir($PATHTOFILES)) {
        if (!@mkdir($PATHTOFILES, 0775, true)) {
            throw new Exception("Unable to create user folder at: {$PATHTOFILES} - Permission denied.", 500);
        }
    }

    // Convert plan quota strings to bytes (defaults: 1GB storage, 50MB per upload)
    $storageQuotaBytes = parseFileSize($userData['max_storage_size'] ?? '', 1073741824);
    $uploadLimitBytes  = parseFileSize($userData['max_upload_size'] ?? '', 52428800);

    // 3. MAIN CONFIGURATION
    $config = [
        // Root path isolated for this specific user
        'rootPath' => $PATHTOFILES,

        // URL path for accessing files
        'rootUrl' => '',

        // Plan storage quota
        'maxQuota' => $storageQuotaBytes,

        // Plan max file upload size
        'maxFileSize' => $uploadLimitBytes,
        'uploadMaxSize' => $uploadLimitBytes,

        // Folders where upload/mkdir/rename are blocked
        'readOnlyFolders' => ['.trash', 'system', 'config'],

        // Folders that cannot be renamed/deleted
        'protectedFolders' => ['.trash', 'uploads', 'backup'],

        // Folders excluded from quota calculation
        'quotaExcludedFolders' => ['backup'],

        // Folders where upload and mkdir are completely blocked
        'uploadBlockedFolders' => ['backup'],

        // Allowed MIME types
        'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*', 'application/pdf', 'text/plain'],

        // Ban dangerous file extensions
        'banExtensions' => [
            'sh', 'bash', 'csh', 'ksh', 'zsh', 'tcsh', 'dash',
            'pl', 'perl', 'py', 'pyc', 'pyo', 'pyw', 'pyz',
            'rb', 'rbw', 'cgi', 'fcgi',
            'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl',
            'msi', 'msp', 'dll', 'ocx', 'sys',
            'php', 'php3', 'php4', 'php5', 'phtml', 'php7', 'phar',
            'asp', 'aspx', 'asa', 'aspx.cs', 'ashx',
            'jsp', 'jspx', 'jsw', 'jssp', 'do',
            'cfm', 'cfml', 'cfc',
            'js', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',
            'app', 'dmg', 'pkg', 'mpkg',
            'conf', 'cnf', 'ini', 'cfg', 'config',
            'htaccess', 'htpasswd', 'htgroup',
            'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
        ],

        'extensionRestrictions' => [
            'log' => ['read' => true, 'write' => false, 'open' => false, 'delete' => false],
            'txt' => ['read' => true, 'write' => true, 'open' => true, 'delete' => true],
            'pdf' => ['read' => true, 'write' => false, 'open' => true, 'delete' => true]
        ],

        'defaultExtensionPermissions' => [
            'read' => true,
            'write' => true,
            'open' => true,
            'delete' => true
        ],

        'disabledOperations' => [],

        'chunkSize' => 1048576, // 1MB chunks

        'enableTrash' => true,
        'trashPath' => $PATHTOFILES . '.trash/',

        'security' => [
            'enableTokenAuth' => false,
            'tokenSecret' => base64_encode(hash('sha512', 'user123') . hash('sha256', 'user123')),
            'enableIPWhitelist' => false,
            'allowedIPs' => [],
            'allowedOrigins' => [],
        ],

        'plugins' => [
            'clamav' => [
                'enabled' => false,
                'clamdscan_path' => '/usr/bin/clamdscan',
            ],
            'rate_limiter' => [
                'enabled' => false
            ],
            'video_editor' => [
                'enabled' => false,
                'ffmpeg_path' => '/usr/bin/ffmpeg',
            ],
            'ftpplugin' => [
                'enabled' => false
            ],
            'publiclinks' => [
                'enabled' => false,
            ]
        ],

        // Authenticated user mapping with plan quota
        'authCallback' => function () use ($userData, $storageQuotaBytes) {
            return [
                'id' => $userData['id'],
                'username' => 'User_' . $userData['id'],
                'quota' => $storageQuotaBytes,
                'permissions' => ['read', 'write', 'delete', 'upload', 'download', 'rename', 'copy', 'move', 'mkdir', 'search', 'quota', 'info']
            ];
        }
    ];

    // CORS handling (same-origin or whitelisted)
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        $originHost = parse_url($origin, PHP_URL_HOST);
        $serverHost = $_SERVER['HTTP_HOST'] ?? '';
        $serverHostOnly = explode(':', $serverHost)[0];
        $allowedOrigins = $config['security']['allowedOrigins'] ?? [];

        if (($originHost && strcasecmp($originHost, $serverHostOnly) === 0) || in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        exit(0);
    }

    // Load available plugins
    $plugins = [];
    $plugin_dir = __DIR__ . '/plugins/';
    if (is_dir($plugin_dir)) {
        foreach (glob($plugin_dir . '*.php') ?: [] as $plugin_file) {
            require_once $plugin_file;
            $plugin_name = basename($plugin_file, '.php');
            $class_name = $plugin_name . 'Plugin';

            if (!empty($config['plugins'][$plugin_name]['enabled']) && class_exists($class_name)) {
                try {
                    $plugins[$plugin_name] = new $class_name($config);
                } catch (Exception $e) {
                    error_log("Failed to load plugin {$plugin_name}: " . $e->getMessage());
                }
            }
        }
    }
    $config['plugins'] = $plugins;

    // Validate user authentication
    $user = call_user_func($config['authCallback']);
    if ($user === false || !is_array($user)) {
        throw new Exception('Authentication required', 401);
    }

    // Token validation
    if (!empty($config['security']['enableTokenAuth'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
        if (stripos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        $token = trim($token);

        if (!Security::validateToken($token, $config['security']['tokenSecret'], $user['id'])) {
            throw new Exception('Invalid security token', 403);
        }
    }

    // IP whitelist
    if (!empty($config['security']['enableIPWhitelist'])) {
        if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $config['security']['allowedIPs'] ?? [], true)) {
            throw new Exception('IP address not allowed', 403);
        }
    }

    // Create trash folder inside user tenant if missing
    if (!empty($config['enableTrash']) && !is_dir($config['trashPath'])) {
        mkdir($config['trashPath'], 0750, true);
    }

    $requestParams = array_merge($_GET, $_POST);
    $cmd = strtolower(trim((string)($requestParams['cmd'] ?? 'open')));

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (in_array($cmd, $config['disabledOperations'], true)) {
        throw new Exception('Operation not allowed', 403);
    }

    // Public links command handler
    if (strpos($cmd, 'publiclink_') === 0) {
        if (!isset($config['plugins']['publiclinks'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Public links plugin not available']);
            exit;
        }

        $result = $config['plugins']['publiclinks']->handleCommand($cmd, $requestParams, $user);
        echo json_encode($result);
        exit;
    }

    // Video processing command handler
    if ($cmd === 'video_process') {
        if (!in_array('write', $user['permissions'] ?? [], true)) {
            throw new Exception('Permission denied', 403);
        }

        if (!isset($config['plugins']['rate_limiter'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Rate limiter not available']);
            exit;
        }

        $rateCheck = $config['plugins']['rate_limiter']->checkVideoProcessLimit($_SERVER['REMOTE_ADDR'] ?? '');
        if (!$rateCheck['success']) {
            http_response_code(429);
            echo json_encode($rateCheck);
            exit;
        }

        if (!isset($config['plugins']['video_editor'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Video processor plugin not available']);
            exit;
        }

        $file_hash = $requestParams['target'] ?? '';
        $params_json = $requestParams['params'] ?? '{}';
        $params = json_decode($params_json, true);
        if (!is_array($params)) {
            throw new Exception('Invalid video processing parameters', 400);
        }

        $result = $config['plugins']['video_editor']->processVideo($file_hash, $params, $config['rootPath']);

        http_response_code($result['code'] ?? ($result['success'] ? 200 : 500));
        echo json_encode($result);
        exit;
    }

    // File upload with full security checks and plan limit enforcement
    if ($cmd === 'upload') {
        if (!in_array('write', $user['permissions'] ?? [], true)) {
            throw new Exception('Permission denied', 403);
        }

        $security = new Security();
        $uploadFile = $_FILES['upload'] ?? null;

        if (!$uploadFile || empty($uploadFile['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded', 'code' => 400]);
            exit;
        }

        // Banned extension validation
        $fileNames = is_array($uploadFile['name']) ? $uploadFile['name'] : [$uploadFile['name']];
        foreach ($fileNames as $fileName) {
            $sanitizedName = $security->sanitizeFilename((string)$fileName);
            if ($sanitizedName === '' || $security->hasBlockedExtension($sanitizedName, $config['banExtensions'])) {
                $ext = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));
                http_response_code(403);
                echo json_encode(['error' => 'Dangerous extension: ' . $ext, 'code' => 403]);
                exit;
            }
        }

        $rawSize = is_array($uploadFile['size']) ? (int)($uploadFile['size'][0] ?? 0) : (int)($uploadFile['size'] ?? 0);
        if ($rawSize <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty files not allowed', 'code' => 400]);
            exit;
        }

        // Hard validation: Check if file exceeds the Plan's Max Upload Size
        if ($rawSize > $config['maxFileSize']) {
            http_response_code(413);
            echo json_encode([
                'error' => 'File too large! Your plan allows a maximum of ' . formatBytes($config['maxFileSize']) . ' per file.',
                'code' => 413
            ]);
            exit;
        }

        // Resolve target directory strictly within rootPath
        $encodedTarget = $_POST['target'] ?? '';
        if ($encodedTarget !== '') {
            $relativePath = base64_decode((string)$encodedTarget, true);
            if ($relativePath === false) {
                throw new Exception('Invalid target hash', 400);
            }
            $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

            if (!$security->isPathSafe($relativePath, $config['rootPath'])) {
                throw new Exception('Invalid target path', 403);
            }

            $targetPath = $relativePath === ''
                ? $config['rootPath']
                : rtrim($config['rootPath'], '/\\') . DIRECTORY_SEPARATOR . $relativePath;
        } else {
            $targetPath = $config['rootPath'];
        }

        $realTargetPath = realpath($targetPath);
        $realRootPath = realpath($config['rootPath']);

        if (!$realTargetPath || !is_dir($realTargetPath) || !$security->isPathSafe($realTargetPath, $config['rootPath'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Target directory does not exist', 'code' => 404]);
            exit;
        }

        $relativePathCheck = '';
        if ($realRootPath && strpos($realTargetPath, $realRootPath) === 0) {
            $relativePathCheck = substr($realTargetPath, strlen($realRootPath));
            $relativePathCheck = ltrim($relativePathCheck, DIRECTORY_SEPARATOR);
            $relativePathCheck = str_replace(DIRECTORY_SEPARATOR, '/', $relativePathCheck);
        }

        foreach ($config['readOnlyFolders'] ?? [] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePathCheck === $folder || strpos($relativePathCheck, $folder . '/') === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot upload to this folder: read-only', 'code' => 403]);
                exit;
            }
        }

        foreach ($config['uploadBlockedFolders'] ?? [] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePathCheck === $folder || strpos($relativePathCheck, $folder . '/') === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot upload to this folder (upload blocked)', 'code' => 403]);
                exit;
            }
        }

        // Quota check
        $fm = new MyFileManager($config, $user);
        $quota = $fm->getQuota();
        $quotaFree = null;

        if ($quota && $quota['total'] > 0) {
            $quotaFree = $quota['free'];
            if ($quota['free'] < $rawSize) {
                http_response_code(507);
                echo json_encode([
                    'error' => 'Storage Quota exceeded. Available: ' . formatBytes($quota['free']) . ', Required: ' . formatBytes($rawSize),
                    'code' => 507
                ]);
                exit;
            }
        }

        $uploader = new ChunkUploader([
            'maxFileSize' => $config['maxFileSize'],
            'allowedMimeTypes' => $config['allowedMimeTypes'],
            'banExtensions' => $config['banExtensions'],
            'quotaFree' => $quotaFree
        ]);

        $result = $uploader->handleUpload($uploadFile, $realTargetPath, $_POST);

        // ClamAV security scan on upload completion
        if (isset($result['completed']) && $result['completed'] === true && isset($result['path'])) {
            if (isset($config['plugins']['clamav'])) {
                $clamavScanner = $config['plugins']['clamav'];
                $scanResult = $clamavScanner->scanFile($result['path']);

                if (!$scanResult['success'] || !$scanResult['is_safe']) {
                    @unlink($result['path']);
                    error_log('ClamAV Security Alert: Malware detected in ' . $result['path'] . ' - File destroyed.');

                    http_response_code(406);
                    echo json_encode([
                        'error' => 'Security Alert: Potential Malware detected. The upload has been blocked.',
                        'code' => 406
                    ]);
                    exit;
                }
            }
        }

        echo json_encode($result);
        exit;
    }

    if ($cmd === 'download') {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');

        while (@ob_end_clean());
    }

    // Default operation execution
    $fm = new MyFileManager($config, $user);
    $response = $fm->execute($cmd, $requestParams);

    // --- UI DISPLAY OVERRIDE ---
    // Replace server php.ini limits with the user's current plan upload limit in UI JSON responses
    if (is_array($response)) {
        $formattedLimit = formatBytes($uploadLimitBytes);

        array_walk_recursive($response, function (&$value, $key) use ($uploadLimitBytes, $formattedLimit) {
            if (stripos((string)$key, 'upload') !== false && stripos((string)$key, 'max') !== false) {
                $value = is_numeric($value) ? $uploadLimitBytes : $formattedLimit;
            }
        });
    }

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $code = (int)$e->getCode();
    error_log("MyFileManager Exception: " . $e->getMessage() . " | Code: " . $code);
    http_response_code(($code >= 400 && $code < 600) ? $code : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $code ?: 500
    ]);
    exit;
}