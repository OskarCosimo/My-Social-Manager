<?php
// plugins/Devto/DevtoPlugin.php

require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class DevtoPlugin implements SocialPluginInterface {

    private const API_BASE = 'https://dev.to/api';

    public function getId(): string {
        return 'devto';
    }

    public function getName(): string {
        return 'DEV Community (Dev.to)';
    }

    /**
     * Get official brand color hex code for DEV Community
     */
    public function getColor(): string {
        return '#0A0A0A';
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
            <strong>Connect DEV Community (Dev.to):</strong><br><br>
            1. Log in to <a href="https://dev.to/settings/extensions" target="_blank">Dev.to &rarr; Settings &rarr; Extensions</a>.<br>
            2. Scroll down to <strong>DEV Community API Keys</strong>.<br>
            3. Enter a description and click <strong>Generate API Key</strong>.<br>
            4. Copy your API Key and paste it below.
        </div>

        <input type="hidden" name="devto_submit" value="1">

        <div class="mb-4">
            <label class="form-label fw-bold">Dev.to API Key</label>
            <input type="password" name="api_key" class="form-control" placeholder="Enter your Dev.to API Key" required>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (!isset($requestData['devto_submit'])) {
            throw new Exception("Invalid form submission.");
        }

        $apiKey = trim($requestData['api_key'] ?? '');
        if (empty($apiKey)) {
            throw new Exception("Dev.to API Key is required.");
        }

        // Test credentials by requesting current user
        $ch = curl_init(self::API_BASE . '/users/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "api-key: {$apiKey}",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode !== 200 || empty($res['id'])) {
            $errorMsg = $res['error'] ?? "Invalid Dev.to API Key (HTTP {$httpCode}).";
            throw new Exception($errorMsg);
        }

        $userId = (string)$res['id'];
        $username = $res['username'] ?? 'dev_user';
        $name = $res['name'] ?? $username;

        $bundledData = json_encode([
            'api_key'  => $apiKey,
            'user_id'  => $userId,
            'username' => $username
        ]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token'     => $bundledData,
            'platform_user_id' => $userId,
            'platform_name'    => "{$name} (@{$username})"
        ];
    }

    /**
     * Live verification of Dev.to API Key
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $apiKey = (is_array($decoded) && isset($decoded['api_key'])) ? $decoded['api_key'] : $accessToken;
        $username = (is_array($decoded) && isset($decoded['username'])) ? $decoded['username'] : '';

        if (empty($apiKey)) {
            return ['valid' => false, 'message' => 'Missing Dev.to API key.'];
        }

        $ch = curl_init(self::API_BASE . '/users/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "api-key: {$apiKey}",
            "Accept: application/json"
        ]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['id'])) {
            $name = $res['name'] ?? $username;
            return [
                'valid'   => true,
                'message' => "Dev.to active (@{$name})"
            ];
        }

        $errorMsg = $res['error'] ?? "Dev.to API key invalid or unauthorized (HTTP {$httpCode}).";
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
            return ['success' => false, 'error' => 'Invalid Dev.to credentials.'];
        }

        $apiKey = $decoded['api_key'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Missing Dev.to API Key. Please reconnect.'];
        }

        // Clean text and generate title
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $cleanText = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $lines = explode("\n", $cleanText);
        $title = !empty($lines[0]) ? mb_substr($lines[0], 0, 100) : 'Article ' . date('Y-m-d H:i');

        // Prepare Markdown body
        $markdownBody = $cleanText;
        if (!empty($sourceLink)) {
            $markdownBody .= "\n\n[Original link](" . $sourceLink . ")";
        }

        $articleData = [
            'title'         => $title,
            'published'     => true,
            'body_markdown' => $markdownBody
        ];

        if (!empty($mediaUrls[0])) {
            $articleData['main_image'] = $mediaUrls[0];
        }

        if (!empty($sourceLink)) {
            $articleData['canonical_url'] = $sourceLink;
        }

        $payload = json_encode(['article' => $articleData]);

        $url = self::API_BASE . '/articles';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "api-key: {$apiKey}",
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
            'error'   => $res['error'] ?? "Dev.to API Error (HTTP {$httpCode})"
        ];
    }
}