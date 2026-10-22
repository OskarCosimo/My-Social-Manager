<?php
// plugins/Medium/MediumPlugin.php

require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class MediumPlugin implements SocialPluginInterface {

    private const API_BASE = 'https://api.medium.com/v1';

    public function getId(): string {
        return 'medium';
    }

    public function getName(): string {
        return 'Medium';
    }

    /**
     * Get official brand color hex code for Medium
     */
    public function getColor(): string {
        return '#00ab6c';
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
            <strong>Connect Medium:</strong><br><br>
            1. Open <a href="https://medium.com/me/settings/security" target="_blank">Medium Settings &rarr; Security and apps</a>.<br>
            2. Scroll down to <strong>Integration tokens</strong>.<br>
            3. Enter a description (e.g. <code>My Social Manager</code>) and click <strong>Get token</strong>.<br>
            4. Copy and paste your token below.
        </div>

        <input type="hidden" name="medium_submit" value="1">

        <div class="mb-4">
            <label class="form-label fw-bold">Medium Integration Token</label>
            <input type="password" name="integration_token" class="form-control" placeholder="Enter your Integration Token" required>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (!isset($requestData['medium_submit'])) {
            throw new Exception("Invalid form submission.");
        }

        $token = trim($requestData['integration_token'] ?? '');
        if (empty($token)) {
            throw new Exception("Medium Integration Token is required.");
        }

        // Test credentials by requesting author profile
        $ch = curl_init(self::API_BASE . '/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];
        $userData = $res['data'] ?? null;

        if ($httpCode !== 200 || empty($userData['id'])) {
            $errorMsg = $res['errors'][0]['message'] ?? "Invalid Integration Token (HTTP {$httpCode}).";
            throw new Exception($errorMsg);
        }

        $authorId = $userData['id'];
        $username = $userData['username'] ?? 'medium_user';
        $authorName = $userData['name'] ?? $username;

        $bundledData = json_encode([
            'token'     => $token,
            'author_id' => $authorId,
            'username'  => $username
        ]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token'     => $bundledData,
            'platform_user_id' => $authorId,
            'platform_name'    => "{$authorName} (@{$username})"
        ];
    }

    /**
     * Live verification of Medium Integration Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $token = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $username = (is_array($decoded) && isset($decoded['username'])) ? $decoded['username'] : '';

        if (empty($token)) {
            return ['valid' => false, 'message' => 'Missing Medium token.'];
        }

        $ch = curl_init(self::API_BASE . '/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['data']['id'])) {
            $name = $res['data']['name'] ?? $username;
            return [
                'valid'   => true,
                'message' => "Medium active (@{$name})"
            ];
        }

        $errorMsg = $res['errors'][0]['message'] ?? "Medium token expired or invalid (HTTP {$httpCode}).";
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
            return ['success' => false, 'error' => 'Invalid Medium credentials.'];
        }

        $token = $decoded['token'] ?? '';
        $authorId = $decoded['author_id'] ?? '';

        if (empty($token) || empty($authorId)) {
            return ['success' => false, 'error' => 'Missing author ID or token. Please reconnect.'];
        }

        // Prepare clean text and title
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $cleanText = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $lines = explode("\n", $cleanText);
        $title = !empty($lines[0]) ? mb_substr($lines[0], 0, 80) : 'Update ' . date('Y-m-d H:i');

        // Build HTML body
        $htmlBody = "<h1>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h1>";

        if (!empty($mediaUrls[0])) {
            $htmlBody .= '<p><img src="' . htmlspecialchars($mediaUrls[0], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" /></p>';
        }

        $htmlBody .= '<p>' . nl2br(htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8')) . '</p>';

        if (!empty($sourceLink)) {
            $htmlBody .= '<p><a href="' . htmlspecialchars($sourceLink, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($sourceLink, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }

        $postPayload = [
            'title'         => $title,
            'contentFormat' => 'html',
            'content'       => $htmlBody,
            'publishStatus' => 'public'
        ];

        if (!empty($sourceLink)) {
            $postPayload['canonicalUrl'] = $sourceLink;
        }

        $url = self::API_BASE . "/users/{$authorId}/posts";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode === 201 && !empty($res['data']['id'])) {
            return [
                'success' => true,
                'post_id' => $res['data']['id']
            ];
        }

        return [
            'success' => false,
            'error'   => $res['errors'][0]['message'] ?? "Medium API Error (HTTP {$httpCode})"
        ];
    }
}