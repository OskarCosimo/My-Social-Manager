<?php
/**
 * // plugins/publiclinks.php
 * Public Links Plugin - Standalone
 * All links stored in a single directory
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class PubliclinksPlugin {
    private $config;
    private $pluginConfig;
    private $linksDir;
    private $allowedExpirations;
    private $allowedWaitTimes;
    private $defaultCancelJsonFile;

    // FILE PATH OF THE JSON DIRECTORY (MUST MATCH download.php)
    const PUBLIC_LINKS_DIR = '/media/contentdelivery.myetv.tv/MYCLOUD/public_links';

    public function __construct($config) {
        $this->config = is_array($config) ? $config : [];
        $this->pluginConfig = $this->config['plugins']['publiclinks'] ?? $this->config;

        // Initialize links directory
        $this->linksDir = $this->initDirectory();

        // Configure allowed expirations (in minutes)
        $this->allowedExpirations = $this->pluginConfig['allowedExpirations'] ?? ($this->config['allowedExpirations'] ?? [
            30, 60, 120, 180, 360, 720, 1440, 2160, 2880
        ]);

        // Configure allowed wait times (in seconds)
        $this->allowedWaitTimes = $this->pluginConfig['allowedWaitTimes'] ?? ($this->config['allowedWaitTimes'] ?? [
            0, 10, 30, 60, 120, 300
        ]);

        // Configure default cancelJsonFile behavior
        $this->defaultCancelJsonFile = $this->pluginConfig['defaultCancelJsonFile'] ?? ($this->config['defaultCancelJsonFile'] ?? true);
    }

    /**
     * Initialize public links directory
     *
     * @return string
     */
    private function initDirectory() {
        $dir = self::PUBLIC_LINKS_DIR;

        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);

            // Protect from direct HTTP access (Apache 2.4 with 2.2 fallback)
            $htaccessContent = <<<HTACCESS
# Block HTTP access to JSON files
<FilesMatch "\.json$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order Allow,Deny
        Deny from all
    </IfModule>
</FilesMatch>

# Disable directory listing
Options -Indexes
HTACCESS;

            @file_put_contents($dir . '/.htaccess', $htaccessContent);
            error_log("Created public links directory: {$dir}");
        }

        return $dir;
    }

    /**
     * Validate token format (64 lowercase hex characters)
     *
     * @param string $token
     * @return bool
     */
    private function isValidToken($token) {
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    /**
     * Find link file by token safely
     *
     * @param string $token
     * @return string|null
     */
    private function findLinkFile($token) {
        if (!$this->isValidToken($token)) {
            return null;
        }
        $linkFile = $this->linksDir . DIRECTORY_SEPARATOR . $token . '.json';
        return is_file($linkFile) ? $linkFile : null;
    }

    /**
     * Validate expiration time against allowed values
     *
     * @param mixed $minutes
     * @return int
     */
    private function validateExpiration($minutes) {
        $minutes = intval($minutes);
        if (!in_array($minutes, $this->allowedExpirations, true)) {
            error_log("Invalid expiration {$minutes} minutes. Using default: {$this->allowedExpirations[0]}");
            return (int)$this->allowedExpirations[0];
        }
        return $minutes;
    }

    /**
     * Validate wait time against allowed values
     *
     * @param mixed $seconds
     * @return int
     */
    private function validateWaitTime($seconds) {
        $seconds = intval($seconds);
        if (!in_array($seconds, $this->allowedWaitTimes, true)) {
            error_log("Invalid wait time {$seconds} seconds. Using default: {$this->allowedWaitTimes[0]}");
            return (int)$this->allowedWaitTimes[0];
        }
        return $seconds;
    }

    /**
     * Handle plugin commands
     *
     * @param string $cmd
     * @param array $params
     * @param array $user
     * @return array
     * @throws Exception
     */
    public function handleCommand($cmd, $params, $user) {
        switch ($cmd) {
            case 'publiclink_create':
                return $this->createLink($params, $user);
            case 'publiclink_list':
                return $this->listLinks($user);
            case 'publiclink_delete':
                return $this->deleteLink($params, $user);
            case 'publiclink_get_config':
                return $this->getConfig();
            default:
                throw new Exception('Unknown publiclinks command', 400);
        }
    }

    /**
     * Get plugin configuration (for client-side validation)
     *
     * @return array
     */
    private function getConfig() {
        return [
            'success' => true,
            'config' => [
                'allowedExpirations' => $this->allowedExpirations,
                'allowedWaitTimes' => $this->allowedWaitTimes,
                'defaultCancelJsonFile' => $this->defaultCancelJsonFile
            ]
        ];
    }

    /**
     * Create new public link
     *
     * @param array $params
     * @param array $user
     * @return array
     * @throws Exception
     */
    private function createLink($params, $user) {
        if (!in_array('read', $user['permissions'] ?? [], true)) {
            throw new Exception('Permission denied', 403);
        }

        $fileHash = (string)($params['file_hash'] ?? '');
        $fileName = (string)($params['file_name'] ?? '');
        $linkType = (string)($params['link_type'] ?? 'public');
        $expirationMinutes = intval($params['expiration_minutes'] ?? 30);
        $waitSeconds = intval($params['wait_seconds'] ?? 30);
        $maxDownloads = intval($params['max_downloads'] ?? 0);
        $cancelJsonFile = ($params['cancel_json_file'] ?? '1') === '1';

        if ($fileHash === '' || $fileName === '') {
            throw new Exception('Invalid file data', 400);
        }

        // Validate target file exists inside user's rootPath
        $rootPath = $this->config['rootPath'] ?? '';
        if ($rootPath === '') {
            throw new Exception('Root path not configured', 500);
        }

        $decodedRelative = base64_decode($fileHash, true);
        if ($decodedRelative === false || $decodedRelative === '') {
            throw new Exception('Invalid file hash', 400);
        }

        $decodedRelative = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decodedRelative), DIRECTORY_SEPARATOR);
        $candidatePath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . $decodedRelative;

        if (class_exists('Security')) {
            $security = new Security();
            if (!$security->isPathSafe($decodedRelative, $rootPath) || !$security->isPathSafe($candidatePath, $rootPath)) {
                throw new Exception('Invalid file path', 403);
            }
        }

        $realFile = realpath($candidatePath);
        $realRoot = realpath($rootPath);
        if (!$realFile || !$realRoot || !is_file($realFile) || strpos($realFile, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
            throw new Exception('Target file not found', 404);
        }

        // Use real server-side metadata instead of trusting client-supplied filename/size
        $safeFileName = basename($realFile);
        $realFileSize = (int)@filesize($realFile);

        // Validate link type
        if (!in_array($linkType, ['public', 'registered'], true)) {
            throw new Exception('Invalid link type', 400);
        }

        // Server-side validation of expiration, wait time, and max downloads
        $expirationMinutes = $this->validateExpiration($expirationMinutes);
        $waitSeconds = $this->validateWaitTime($waitSeconds);

        if ($maxDownloads < 0) {
            $maxDownloads = 0;
        }
        if ($maxDownloads > 1000) {
            $maxDownloads = 1000;
        }

        // Generate secure unique token (64 hex chars)
        $maxAttempts = 10;
        $attempt = 0;
        do {
            $token = bin2hex(random_bytes(32));
            $linkFile = $this->linksDir . DIRECTORY_SEPARATOR . $token . '.json';
            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw new Exception('Failed to generate unique token', 500);
            }
        } while (file_exists($linkFile));

        $createdAt = time();
        $expiresAt = $createdAt + ($expirationMinutes * 60);

        $linkData = [
            'token' => $token,
            'user_id' => $user['id'],
            'user_name' => $user['username'] ?? 'Unknown',
            'file_hash' => $fileHash,
            'file_name' => $safeFileName,
            'file_size' => $realFileSize,
            'link_type' => $linkType,
            'wait_seconds' => $waitSeconds,
            'max_downloads' => $maxDownloads,
            'download_count' => 0,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
            'last_download_at' => null,
            'root_path' => $this->config['rootPath'],
            'cancel_json_file' => $cancelJsonFile
        ];

        if (@file_put_contents($linkFile, json_encode($linkData, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new Exception('Failed to save public link', 500);
        }
        @chmod($linkFile, 0640);

        $this->cleanExpiredLinks();

        return [
            'success' => true,
            'token' => $token,
            'download_url' => '/download.php?t=' . $token,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * List user's links
     *
     * @param array $user
     * @return array
     */
    private function listLinks($user) {
        $links = [];
        if (!is_dir($this->linksDir)) {
            return ['success' => true, 'links' => []];
        }

        $files = glob($this->linksDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $now = time();

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $linkData = json_decode($raw, true);
            if (!is_array($linkData) || !isset($linkData['user_id'])) {
                continue;
            }

            // Skip links from other users
            if ((string)$linkData['user_id'] !== (string)$user['id']) {
                continue;
            }

            $isExpired = isset($linkData['expires_at']) && $linkData['expires_at'] < $now;
            $cancelJsonFile = $linkData['cancel_json_file'] ?? true;

            if ($isExpired && $cancelJsonFile) {
                @unlink($file);
                continue;
            }

            if ($isExpired) {
                $linkData['is_expired'] = true;
            }

            $links[] = $linkData;
        }

        usort($links, function ($a, $b) {
            return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
        });

        return ['success' => true, 'links' => $links];
    }

    /**
     * Delete a link
     *
     * @param array $params
     * @param array $user
     * @return array
     * @throws Exception
     */
    private function deleteLink($params, $user) {
        $token = trim((string)($params['link_token'] ?? ''));

        if (!$this->isValidToken($token)) {
            throw new Exception('Invalid token', 400);
        }

        $linkFile = $this->findLinkFile($token);
        if (!$linkFile) {
            throw new Exception('Link not found', 404);
        }

        $raw = @file_get_contents($linkFile);
        $linkData = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($linkData) || !isset($linkData['user_id'])) {
            throw new Exception('Invalid link data', 500);
        }

        // Verify ownership
        if ((string)$linkData['user_id'] !== (string)$user['id']) {
            throw new Exception('Permission denied', 403);
        }

        @unlink($linkFile);

        return ['success' => true];
    }

    /**
     * Clean expired links
     */
    private function cleanExpiredLinks() {
        if (!is_dir($this->linksDir)) {
            return;
        }

        $files = glob($this->linksDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $now = time();
        $cleaned = 0;

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $linkData = json_decode($raw, true);

            if (is_array($linkData) && isset($linkData['expires_at']) && $linkData['expires_at'] < $now) {
                $cancelJsonFile = $linkData['cancel_json_file'] ?? true;
                if ($cancelJsonFile && @unlink($file)) {
                    $cleaned++;
                }
            }
        }

        if ($cleaned > 0) {
            error_log("Cleaned {$cleaned} expired links");
        }
    }
}