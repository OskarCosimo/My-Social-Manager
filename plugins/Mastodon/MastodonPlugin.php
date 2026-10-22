<?php
// plugins/Mastodon/MastodonPlugin.php

/**
 * My Social Manager - Mastodon Plugin
 * Handles authentication, manual setup, and posting to any Mastodon instance.
 */

require_once __DIR__ . '/../../app/Core/Config.php';

class MastodonPlugin implements SocialPluginInterface {
    
    public function getId(): string {
        return 'mastodon';
    }

    public function getName(): string {
        return 'Mastodon';
    }

    /**
     * Get official brand color hex code for Mastodon
     */
    public function getColor(): string {
        return '#6364FF';
    }

    public function getSettingsFields(): array {
        return [];
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=mastodon';
    }

    public function handleCallback(array $requestData): array {
        $rawInstanceUrl = $requestData['instance_url'] ?? '';
        $accessToken = $requestData['access_token'] ?? '';

        if (empty($accessToken) || empty($rawInstanceUrl)) {
            throw new Exception("Instance URL and Access Token are required.");
        }

        $parsedUrl = parse_url(trim($rawInstanceUrl));
        if (!isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            throw new Exception("Invalid Mastodon instance URL. Please enter a valid domain (e.g., https://mastodon.social).");
        }

        $instanceUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        $ch = curl_init("{$instanceUrl}/api/v1/accounts/verify_credentials");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}"
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);

        if ($httpCode >= 400 || isset($response['error']) || empty($response['id'])) {
            throw new Exception("Invalid Access Token or Instance URL. Could not verify credentials.");
        }

        $bundledToken = json_encode([
            'instance' => $instanceUrl,
            'token' => $accessToken
        ]);

        return [
            'access_token' => $bundledToken,
            'platform_user_id' => $response['id'],
            'platform_name' => '@' . $response['username'] . ' (' . $parsedUrl['host'] . ')'
        ];
    }

    /**
     * Live verification of Mastodon instance credentials
     */
    public function checkTokenStatus(string $bundledToken): array {
        $credentials = json_decode($bundledToken, true);
        $instanceUrl = $credentials['instance'] ?? '';
        $accessToken = $credentials['token'] ?? '';

        if (empty($instanceUrl) || empty($accessToken)) {
            return ['valid' => false, 'message' => 'Malformed credentials.'];
        }

        $ch = curl_init("{$instanceUrl}/api/v1/accounts/verify_credentials");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}"
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode === 200 && !empty($response['id'])) {
            $username = $response['username'] ?? 'User';
            return [
                'valid' => true,
                'message' => "Active (@{$username})"
            ];
        }

        $errorMsg = $response['error'] ?? "Failed to verify credentials (HTTP {$httpCode})";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function getManualSetupHtml(): string {
        return '
        <div class="alert alert-info small mb-4">
            <strong>Connect Mastodon:</strong><br>
            1. Go to your Mastodon instance settings -> Development -> New Application.<br>
            2. Grant scopes: <code>read</code>, <code>write:statuses</code>, <code>write:media</code>.<br>
            3. Generate your Access Token and paste it below along with your instance URL.
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Instance URL</label>
            <input type="text" name="instance_url" class="form-control" placeholder="https://mastodon.social" required>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Access Token</label>
            <input type="password" name="access_token" class="form-control" placeholder="Enter your Access Token" required>
        </div>
        ';
    }

    public function publishPost(string $content, array $mediaUrls, string $bundledToken, ?string $sourceLink = null): array {
        $credentials = json_decode($bundledToken, true);
        $instanceUrl = $credentials['instance'] ?? '';
        $accessToken = $credentials['token'] ?? '';
        
        if (empty($instanceUrl) || empty($accessToken)) {
            return ['success' => false, 'error' => 'Corrupted credentials. Please reconnect your account.'];
        }

        $plainContent = trim(html_entity_decode(strip_tags($content)));

        $fullText = $plainContent;
        if (!empty($sourceLink)) {
            if (strpos($fullText, $sourceLink) === false) {
                $fullText .= "\n\n" . $sourceLink;
            }
        }

        $uploadedMediaIds = [];

        // Upload images natively via Mastodon Media API
        if (!empty($mediaUrls)) {
            foreach ($mediaUrls as $mediaUrl) {
                $mediaId = $this->uploadMediaToMastodon($instanceUrl, $accessToken, $mediaUrl);
                if ($mediaId) {
                    $uploadedMediaIds[] = $mediaId;
                }
            }
        }

        $data = [
            'status'     => $fullText,
            'visibility' => 'public'
        ];

        if (!empty($uploadedMediaIds)) {
            $data['media_ids'] = $uploadedMediaIds;
        }

        $ch = curl_init("{$instanceUrl}/api/v1/statuses");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}"
        ]);
        
        $resultRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($resultRaw, true);

        if ($httpCode >= 400 || isset($response['error'])) {
            return ['success' => false, 'error' => $response['error'] ?? 'Mastodon API Error ' . $httpCode];
        }

        return ['success' => true, 'post_id' => $response['id'] ?? ''];
    }

    private function uploadMediaToMastodon(string $instanceUrl, string $token, string $mediaUrl): ?string {
        $fileData = @file_get_contents($mediaUrl);
        if (!$fileData) {
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'masto_img_');
        file_put_contents($tmpFile, $fileData);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpFile) ?: 'image/jpeg';
        finfo_close($finfo);

        $cfile = new CURLFile($tmpFile, $mimeType, basename($mediaUrl));

        $ch = curl_init("{$instanceUrl}/api/v2/media");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);

        $resultRaw = curl_exec($ch);
        curl_close($ch);
        @unlink($tmpFile);

        $response = json_decode($resultRaw, true);
        return $response['id'] ?? null;
    }
}