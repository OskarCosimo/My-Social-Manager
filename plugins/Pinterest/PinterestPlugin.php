<?php
// plugins/Pinterest/PinterestPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class PinterestPlugin implements SocialPluginInterface {
    
    private const API_BASE = 'https://api.pinterest.com/v5';

    public function getId(): string {
        return 'pinterest';
    }

    public function getName(): string {
        return 'Pinterest';
    }

    /**
     * Get official brand color hex code for Pinterest
     */
    public function getColor(): string {
        return '#E60023';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'Pinterest App ID',
                'placeholder' => 'Enter App ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'Pinterest App Secret',
                'placeholder' => 'Enter App Secret'
            ],
            'default_image' => [
                'type' => 'text',
                'label' => 'Default Fallback Image URL (for text-only posts)',
                'placeholder' => 'https://example.com/default-pinterest.jpg or /public/assets/...'
            ]
        ];
    }

    private function getCallbackUrl(): string {
        return Config::APP_URL . '/plugin/callback';
    }

    public function getAuthUrl(): string {
        $settings = PluginSetting::getForPlugin($this->getId());
        $clientId = $settings['client_id'] ?? '';
        
        $redirectUri = urlencode($this->getCallbackUrl());
        // Require permissions to read user, boards, and write pins
        $scopes = urlencode('user_accounts:read boards:read pins:write'); 
        $state = urlencode($this->getId());
        
        return "https://www.pinterest.com/oauth/?consumer_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scopes}&state={$state}";
    }

    public function handleCallback(array $requestData): array {
        if (isset($requestData['error'])) {
            throw new Exception("Pinterest Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        $settings = PluginSetting::getForPlugin($this->getId());
        $clientId = $settings['client_id'] ?? '';
        $clientSecret = $settings['client_secret'] ?? '';

        $postData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getCallbackUrl()
        ];

        // Pinterest requires Basic Auth using Base64(client_id:client_secret)
        $basicAuth = base64_encode("{$clientId}:{$clientSecret}");

        $ch = curl_init(self::API_BASE . '/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$basicAuth}",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        
        $responseRaw = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($responseRaw, true);

        if (isset($response['error']) || empty($response['access_token'])) {
            throw new Exception("Token Error: " . ($response['message'] ?? 'Failed to obtain token.'));
        }
        
        $accessToken = $response['access_token'];
        $expiresIn = $response['expires_in'] ?? null;
        $refreshToken = $response['refresh_token'] ?? null;

        // 1. Get User Profile
        $profileRes = $this->curlGet(self::API_BASE . '/user_account', $accessToken);
        $username = $profileRes['username'] ?? 'pinterest_user';

        // 2. Get the first Board ID (Pins require a board to be attached to)
        $boardsRes = $this->curlGet(self::API_BASE . '/boards', $accessToken);
        $boardId = $boardsRes['items'][0]['id'] ?? null;

        if (!$boardId) {
            throw new Exception("No Pinterest boards found. You must create at least one board to publish pins.");
        }

        require_once __DIR__ . '/../../app/Models/UsageManager.php';
        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $accessToken,
            'platform_user_id' => $boardId, // Save the Board ID for quick publishing
            'platform_name' => '@' . $username . ' (Board: ' . ($boardsRes['items'][0]['name'] ?? '') . ')',
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Live verification of Pinterest Access Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $realToken = $accessToken;
        $decoded = json_decode($accessToken, true);
        if (is_array($decoded) && isset($decoded['token'])) {
            $realToken = $decoded['token'];
        }

        $ch = curl_init(self::API_BASE . '/user_account');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$realToken}"]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($result, true) ?? [];

        if ($httpCode === 200 && !empty($res['username'])) {
            return [
                'valid' => true,
                'message' => "Pinterest active (@{$res['username']})"
            ];
        }

        $errorMsg = $res['message'] ?? 'Pinterest access token expired or revoked.';
        if ($httpCode === 401) {
            $errorMsg = 'Pinterest session expired. Please reconnect.';
        }

        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        if (empty($mediaUrls)) {
            return ['success' => false, 'error' => 'Pinterest requires at least one image URL.'];
        }

        // Retrieve the saved Board ID
        $boardsRes = $this->curlGet(self::API_BASE . '/boards', $accessToken);
        $boardId = $boardsRes['items'][0]['id'] ?? null;

        if (!$boardId) {
            return ['success' => false, 'error' => 'No Pinterest board found for this account.'];
        }

        $plainDescription = trim(html_entity_decode(strip_tags($content)));

        $data = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $mediaUrls[0]
            ],
            'description' => $plainDescription
        ];

        // Attach outbound link to the Pinterest Pin if provided
        if (!empty($sourceLink)) {
            $data['link'] = $sourceLink;
        }

        $ch = curl_init(self::API_BASE . '/pins');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ]);
        
        $resultRaw = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($resultRaw, true);

        if (isset($response['code']) || isset($response['message'])) {
            return ['success' => false, 'error' => $response['message'] ?? 'API Error'];
        }

        return ['success' => true, 'post_id' => $response['id'] ?? null];
    }

    private function curlGet(string $url, string $token): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true) ?? [];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Open Pinterest Developers Portal</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://developers.pinterest.com/apps/" target="_blank" class="fw-bold">Pinterest Developers Portal</a> and click <strong>Create app</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Add Redirect URI</h6>
            <p class="text-muted small mb-1">
                In your App settings under <strong>Redirect URIs</strong>, add the following callback URL:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminPinterestRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminPinterestRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Copy App Credentials</h6>
            <p class="text-muted small mb-0">
                Copy the <strong>App ID</strong> (Consumer ID) and <strong>App Secret Key</strong> and paste them into the configuration form below.
            </p>
        </div>
        ';
    }
}
