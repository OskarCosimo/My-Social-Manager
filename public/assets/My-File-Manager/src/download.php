<?php
/**
 * Public Download Page - Standalone
 * Generic version with configurable authentication
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

// FILE PATH OF THE JSON FILE (MUST BE THE SAME OF THE PLUGIN)
define('PUBLIC_LINKS_DIR', __DIR__ . '/files/public_links');

// DATE/TIME TIMEZONE
define('DISPLAY_TIMEZONE', 'UTC');

// AUTHENTICATION CONFIGURATION
// You can implement your own authentication by modifying the isUserAuthenticated() function below
// or by including your own authentication system
define('AUTH_ENABLED', false); // Set to true to enable custom authentication
define('AUTH_LOGIN_URL', '/login.php'); // URL to redirect for login

// Optional: Include your custom authentication system
// Example: include_once __DIR__ . '/auth/your-auth-system.php';

// ============================================================================
// PREVENT BROWSER CACHING
// ============================================================================
header_remove('ETag');
header_remove('Pragma');
header_remove('Cache-Control');
header_remove('Last-Modified');
header_remove('Expires');

header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Cloudflare-specific headers to force bypass
header('CF-Cache-Status: BYPASS');
header('CDN-Cache-Control: no-cache');

// Vary header to prevent caching different tokens
header('Vary: *');

// Start session
session_start();

/**
 * Find link file by token
 */
function findLinkFile($token) {
    $linkFile = PUBLIC_LINKS_DIR . '/' . $token . '.json';
    return file_exists($linkFile) ? $linkFile : null;
}

/**
 * Load link data by token
 */
function loadLinkData($token) {
    $linkFile = findLinkFile($token);
    
    if (!$linkFile) {
        error_log("❌ Token not found: {$token}");
        return null;
    }
    
    error_log("✅ Found link file: {$linkFile}");
    
    if (!is_readable($linkFile)) {
        error_log("❌ Link file not readable");
        return null;
    }
    
    $content = file_get_contents($linkFile);
    if ($content === false) {
        error_log("❌ Failed to read file");
        return null;
    }
    
    $data = json_decode($content, true);
    if ($data === null) {
        error_log("❌ JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $data;
}

/**
 * Increment download counter
 */
function incrementDownload($token, $linkData) {
    $linkFile = findLinkFile($token);
    
    if (!$linkFile) {
        error_log("❌ Cannot increment: file not found");
        return false;
    }
    
    $linkData['download_count']++;
    $linkData['last_download_at'] = time();
    
    $result = file_put_contents($linkFile, json_encode($linkData, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        error_log("❌ Failed to update download count");
        return false;
    }
    
    error_log("✅ Download count incremented to: " . $linkData['download_count']);
    return true;
}

/**
 * Check if user is authenticated
 * 
 * CUSTOMIZE THIS FUNCTION FOR YOUR AUTHENTICATION SYSTEM
 * 
 * Examples:
 * - return !empty($_SESSION['user_id']);
 * - return !empty($_SESSION['logged_in']);
 * - return isset($_COOKIE['auth_token']) && validateToken($_COOKIE['auth_token']);
 * - return checkCustomAuth();
 */
function isUserAuthenticated() {
    if (!AUTH_ENABLED) {
        return false; // Authentication disabled, registered links not available
    }
    
    // Example implementations (uncomment and modify as needed):
    
    // Basic session check
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    
    // Or check for logged_in flag
    // return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    
    // Or check cookie
    // return isset($_COOKIE['auth_token']) && !empty($_COOKIE['auth_token']);
    
    // Or use custom function
    // return yourCustomAuthCheck();
}

/**
 * Get current authenticated user info (optional)
 * Return array with 'username' or null if not authenticated
 */
function getCurrentUser() {
    if (!isUserAuthenticated()) {
        return null;
    }
    
    // Customize based on your authentication system
    return [
        'username' => $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User',
        'id' => $_SESSION['user_id'] ?? null
    ];
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

$token = $_GET['t'] ?? '';
$action = $_GET['action'] ?? 'view';

if (empty($token)) {
    die('❌ Invalid download link');
}

// Load link data
$link = loadLinkData($token);

if (!$link) {
    die('❌ Link not found or expired');
}

// Check expiration
if ($link['expires_at'] < time()) {
    die('❌ Link has expired on ' . date('Y-m-d H:i:s', $link['expires_at']));
}

// Check max downloads
if ($link['max_downloads'] > 0 && $link['download_count'] >= $link['max_downloads']) {
    die('❌ Download limit reached (' . $link['max_downloads'] . ' downloads)');
}

// Check if registered users only
if ($link['link_type'] === 'registered') {
    if (!AUTH_ENABLED) {
        http_response_code(403);
        die('🔒 This link requires authentication, but authentication is not enabled on this server.');
    }
    
    if (!isUserAuthenticated()) {
        http_response_code(403);
        $loginUrl = AUTH_LOGIN_URL;
        die('🔒 This link requires login. Please <a href="' . htmlspecialchars($loginUrl) . '" style="color: #667eea; text-decoration: underline;">login</a> first to access this file.');
    }
    
    $currentUser = getCurrentUser();
    if ($currentUser) {
        error_log("✅ Registered link accessed by user: {$currentUser['username']}");
    }
}

// ============================================================================
// DOWNLOAD ACTION
// ============================================================================

if ($action === 'download') {
    // Re-check authentication for registered links on actual download
    if ($link['link_type'] === 'registered') {
        if (!AUTH_ENABLED || !isUserAuthenticated()) {
            http_response_code(403);
            die('🔒 Authentication required for download');
        }
    }
    
    // Verify wait time has passed
    $waitKey = 'wait_' . $token;
    if (!isset($_SESSION[$waitKey]) || (time() - $_SESSION[$waitKey]) < $link['wait_seconds']) {
        die('⏳ Please wait the required time before downloading');
    }
    
    // Construct file path
    $fileHash = $link['file_hash'];
    $filePath = base64_decode($fileHash);
    $rootPath = rtrim($link['root_path'], '/');
    $fullPath = $rootPath . '/' . $filePath;
    
    error_log("📁 Attempting to download: {$fullPath}");
    
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        error_log("❌ File not found: {$fullPath}");
        die('❌ File not found on server');
    }
    
    // Log download with user info if authenticated
    $currentUser = getCurrentUser();
    $userInfo = $currentUser ? " by user: {$currentUser['username']}" : " (anonymous)";
    error_log("📥 Download starting: {$link['file_name']}{$userInfo}");
    
    // Increment download counter
    incrementDownload($token, $link);
    
    // Clean all output buffers
    while (ob_get_level()) ob_end_clean();
    
    // Disable compression
    @ini_set('zlib.output_compression', 0);
    @ini_set('memory_limit', '-1');
    @ini_set('max_execution_time', '0');
    
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    
    // Set headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($link['file_name']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Stream file
    $fp = fopen($fullPath, 'rb');
    if ($fp === false) {
        die('❌ Cannot open file');
    }
    
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
    
    error_log("✅ Download completed for: {$link['file_name']}{$userInfo}");
    exit;
}

// ============================================================================
// WAIT PAGE
// ============================================================================

$_SESSION['wait_' . $token] = time();

// Reload link data
$link = loadLinkData($token);

$expiresIn = $link['expires_at'] - time();
$expiresMinutes = ceil($expiresIn / 60);
$fileSizeMB = number_format($link['file_size'] / 1024 / 1024, 2);

// Format expiration date
$timezone = DISPLAY_TIMEZONE;
$expiresDate = new DateTime('@' . $link['expires_at']);
$expiresDate->setTimezone(new DateTimeZone($timezone));
$expiresFormatted = $expiresDate->format('Y-m-d H:i');

// Check link type for display
$isRegisteredOnly = ($link['link_type'] === 'registered');
$currentUser = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Download: <?= htmlspecialchars($link['file_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .dl-page-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 40px);
        }
        
        .dl-wrapper {
            background: white;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .dl-title { 
            color: #333; 
            margin-bottom: 10px; 
            font-size: 28px; 
        }
        
        .dl-user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .dl-file-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 4px solid #667eea;
        }
        
        .dl-file-info div { 
            margin: 8px 0; 
            color: #555; 
        }
        
        .dl-file-info strong { 
            color: #333; 
        }
        
        .dl-link-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .dl-link-type-public {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .dl-link-type-registered {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .dl-countdown {
            font-size: 72px;
            color: #667eea;
            text-align: center;
            margin: 40px 0;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .dl-status-text { 
            text-align: center; 
            color: #666; 
            margin: 20px 0; 
            font-size: 16px; 
        }
        
        .dl-button {
            display: block;
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            border: none;
            cursor: pointer;
        }
        
        .dl-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .dl-progress-wrapper {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .dl-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 1s linear;
        }
        
        .dl-footer { 
            text-align: center; 
            margin-top: 20px; 
            color: #666; 
            font-size: 12px; 
        }
        
        .dl-footer .timezone-info {
            color: #999;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .dl-wrapper {
                padding: 30px 20px;
            }
            
            .dl-title {
                font-size: 24px;
            }
            
            .dl-countdown {
                font-size: 56px;
            }
        }
    </style>
</head>
<body>
    <div class="dl-page-wrapper">
        <div class="dl-wrapper">
            <h1 class="dl-title">📥 Download File</h1>
            
            <?php if ($currentUser): ?>
                <div class="dl-user-badge">
                    👤 Logged in as: <?= htmlspecialchars($currentUser['username']) ?>
                </div>
            <?php endif; ?>
            
            <div class="dl-file-info">
                <div>
                    <strong>📄 File:</strong> <?= htmlspecialchars($link['file_name']) ?>
                </div>
                <div>
                    <strong>🔒 Access Type:</strong>
                    <?php if ($isRegisteredOnly): ?>
                        <span class="dl-link-type-badge dl-link-type-registered">🔐 Registered Users Only</span>
                    <?php else: ?>
                        <span class="dl-link-type-badge dl-link-type-public">🌐 Public Link</span>
                    <?php endif; ?>
                </div>
                <div><strong>💾 Size:</strong> <?= $fileSizeMB ?> MB</div>
                <div><strong>📊 Downloads:</strong> <?= $link['download_count'] ?><?= $link['max_downloads'] > 0 ? ' / ' . $link['max_downloads'] : '' ?></div>
                <div><strong>⏰ Expires in:</strong> <?= $expiresMinutes ?> minutes</div>
            </div>
            
            <p class="dl-status-text">⏳ Please wait while we prepare your download...</p>
            
            <div class="dl-progress-wrapper">
                <div class="dl-progress-bar" id="dlProgressBar"></div>
            </div>
            
            <div class="dl-countdown" id="dlTimer"><?= $link['wait_seconds'] ?></div>
            
            <a href="?t=<?= htmlspecialchars($token) ?>&action=download" 
               class="dl-button" 
               id="dlButton" 
               style="display: none;">
                ⬇️ Download Now
            </a>
            
            <div class="dl-footer">
                Secure download • Expires <strong><?= $expiresFormatted ?></strong> <span class="timezone-info">(<?= $timezone ?>)</span>
            </div>
        </div>
    </div>
    
    <script>
        let timeLeft = <?= $link['wait_seconds'] ?>;
        const totalTime = timeLeft;
        const timerEl = document.getElementById('dlTimer');
        const downloadBtn = document.getElementById('dlButton');
        const progressBar = document.getElementById('dlProgressBar');
        const statusText = document.querySelector('.dl-status-text');
        
        const countdown = setInterval(() => {
            timeLeft--;
            timerEl.textContent = timeLeft;
            progressBar.style.width = ((totalTime - timeLeft) / totalTime * 100) + '%';
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerEl.textContent = '✅';
                timerEl.style.color = '#28a745';
                progressBar.style.width = '100%';
                downloadBtn.style.display = 'block';
                statusText.textContent = '✅ Ready to download!';
            }
        }, 1000);
    </script>
</body>
</html>
