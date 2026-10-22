<?php
// plugins/Wordpress/WordpressPlugin.php

require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class WordpressPlugin implements SocialPluginInterface {

    public function getId(): string {
        return 'wordpress';
    }

    public function getName(): string {
        return 'WordPress (REST API)';
    }

    /**
     * Get official brand color hex code for WordPress
     */
    public function getColor(): string {
        return '#21759B';
    }

    public function getSettingsFields(): array {
        return [];
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    public function getManualSetupHtml(): string {
        return '
        <div class="alert alert-info small mb-4">
            <strong>Connect your WordPress Site:</strong><br><br>
            1. Open your WordPress Admin Dashboard (WordPress 5.6+ required).<br>
            2. Go to <strong>Users &rarr; Profile</strong> and scroll to <strong>Application Passwords</strong>.<br>
            3. Enter <code>My Social Manager</code> and click <strong>Add New Application Password</strong>.<br>
            4. Copy the generated 24-character password and enter your details below.
        </div>

        <input type="hidden" name="wp_submit" value="1">

        <div class="mb-3">
            <label class="form-label fw-bold">WordPress Site URL</label>
            <input type="url" name="site_url" class="form-control" placeholder="https://example.com" required>
            <div class="form-text">Include https:// and no trailing slash.</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">WordPress Username or Email</label>
            <input type="text" name="wp_username" class="form-control" placeholder="e.g. admin or editor" required>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Application Password</label>
            <input type="password" name="app_password" class="form-control" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (!isset($requestData['wp_submit'])) {
            throw new Exception("Invalid form submission.");
        }

        $rawSiteUrl = trim($requestData['site_url'] ?? '');
        $username = trim($requestData['wp_username'] ?? '');
        $appPassword = trim($requestData['app_password'] ?? '');

        if (empty($rawSiteUrl) || empty($username) || empty($appPassword)) {
            throw new Exception("Site URL, Username, and Application Password are required.");
        }

        $parsed = parse_url($rawSiteUrl);
        if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
            throw new Exception("Please enter a valid Site URL (e.g. https://yourblog.com).");
        }

        $siteUrl = rtrim($parsed['scheme'] . '://' . $parsed['host'] . ($parsed['path'] ?? ''), '/');

        // Test credentials by requesting current user profile
        $authHeader = 'Basic ' . base64_encode("{$username}:{$appPassword}");
        $apiUrl = "{$siteUrl}/wp-json/wp/v2/users/me";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$authHeader}",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode !== 200 || empty($res['id'])) {
            $errorMsg = $res['message'] ?? "WordPress rejected credentials (HTTP {$httpCode}). Make sure REST API and Application Passwords are enabled.";
            throw new Exception($errorMsg);
        }

        $userDisplayName = $res['name'] ?? $username;
        $hostName = $parsed['host'];

        $bundledData = json_encode([
            'site_url'     => $siteUrl,
            'username'     => $username,
            'app_password' => $appPassword,
            'user_id'      => $res['id']
        ]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token'     => $bundledData,
            'platform_user_id' => $siteUrl . '_' . $res['id'],
            'platform_name'    => "{$userDisplayName} ({$hostName})"
        ];
    }

    /**
     * Live verification of WordPress REST API credentials
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        if (!is_array($decoded)) {
            return ['valid' => false, 'message' => 'Malformed WordPress credentials.'];
        }

        $siteUrl = $decoded['site_url'] ?? '';
        $username = $decoded['username'] ?? '';
        $appPassword = $decoded['app_password'] ?? '';

        if (empty($siteUrl) || empty($username) || empty($appPassword)) {
            return ['valid' => false, 'message' => 'Incomplete WordPress credentials stored.'];
        }

        $authHeader = 'Basic ' . base64_encode("{$username}:{$appPassword}");
        $apiUrl = "{$siteUrl}/wp-json/wp/v2/users/me";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$authHeader}",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['id'])) {
            $name = $res['name'] ?? $username;
            $host = parse_url($siteUrl, PHP_URL_HOST);
            return [
                'valid'   => true,
                'message' => "WordPress active (@{$name} on {$host})"
            ];
        }

        $errorMsg = $res['message'] ?? "WordPress authorization failed (HTTP {$httpCode}). Check Application Password.";
        return [
            'valid'   => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken): array {
        return $this->publishPostWithLink($content, $mediaUrls, $accessToken, null);
    }

    public function publishPostWithLink(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Invalid WordPress credentials stored.'];
        }

        $siteUrl = $decoded['site_url'] ?? '';
        $username = $decoded['username'] ?? '';
        $appPassword = $decoded['app_password'] ?? '';

        $authHeader = 'Basic ' . base64_encode("{$username}:{$appPassword}");

        // Format HTML content
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $cleanText = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Generate clean post title (first 80 chars of text)
        $lines = explode("\n", $cleanText);
        $title = !empty($lines[0]) ? mb_substr($lines[0], 0, 80) : 'Social Update ' . date('Y-m-d H:i');

        $bodyContent = nl2br(htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8'));
        if (!empty($sourceLink)) {
            $bodyContent .= '<p><a href="' . htmlspecialchars($sourceLink, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($sourceLink, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }

        // 1. Upload featured image to WordPress media library if image exists
        $featuredMediaId = null;
        if (!empty($mediaUrls[0])) {
            $featuredMediaId = $this->uploadMediaToWordPress($siteUrl, $authHeader, $mediaUrls[0]);
        }

        // 2. Publish post via REST API
        $postsUrl = "{$siteUrl}/wp-json/wp/v2/posts";
        $postData = [
            'title'   => $title,
            'content' => $bodyContent,
            'status'  => 'publish'
        ];

        if ($featuredMediaId) {
            $postData['featured_media'] = $featuredMediaId;
        }

        $ch = curl_init($postsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$authHeader}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode === 201 && !empty($res['id'])) {
            return [
                'success' => true,
                'post_id' => (string)$res['id']
            ];
        }

        return [
            'success' => false,
            'error'   => $res['message'] ?? "WordPress REST API Error (HTTP {$httpCode})"
        ];
    }

    /**
     * Upload an image to the WordPress Media Library and return its attachment ID
     */
    private function uploadMediaToWordPress(string $siteUrl, string $authHeader, string $imageUrl): ?int {
        $imageData = @file_get_contents($imageUrl);
        if (!$imageData) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData) ?: 'image/jpeg';
        finfo_close($finfo);

        $filename = basename(parse_url($imageUrl, PHP_URL_PATH)) ?: 'image.jpg';

        $mediaUrl = "{$siteUrl}/wp-json/wp/v2/media";
        $ch = curl_init($mediaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$authHeader}",
            "Content-Type: {$mimeType}",
            "Content-Disposition: attachment; filename=\"{$filename}\"",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];
        return $res['id'] ?? null;
    }
}