<?php
// plugins/Myetv/MyetvPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class MyetvPlugin implements SocialPluginInterface {
    
    private const API_BASE_URL = 'https://developers.myetv.tv';
    private const API_AUTH_URL = self::API_BASE_URL . '/api/oauth/authorize.php';
    private const API_TOKEN_URL = self::API_BASE_URL . '/api/oauth/token.php';
    private const API_ME_URL = self::API_BASE_URL . '/api/v1/profile.php';
    private const API_POST_URL = self::API_BASE_URL . '/api/v1/publishcontents.php';

    public function getId(): string {
        return 'myetv';
    }

    public function getName(): string {
        return 'MYETV';
    }

    public function getColor(): string {
        return 'goldenrod';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'MYETV Client ID',
                'placeholder' => 'Enter your App Client ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'MYETV Client Secret',
                'placeholder' => 'Enter your App Client Secret'
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
        $state = urlencode($this->getId()); 
        
        return self::API_AUTH_URL . "?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope=profile%20email&state={$state}";
    }

    public function handleCallback(array $requestData): array {
        if (isset($requestData['error'])) {
            throw new Exception("MYETV Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $settings = PluginSetting::getForPlugin($this->getId());
        
        $postData = [
            'grant_type' => 'authorization_code',
            'client_id' => $settings['client_id'] ?? '',
            'client_secret' => $settings['client_secret'] ?? '',
            'redirect_uri' => $this->getCallbackUrl(),
            'code' => $code
        ];

        $ch = curl_init(self::API_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $responseRaw = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($responseRaw, true);

        if (isset($response['error']) || empty($response['access_token'])) {
            throw new Exception("Token Error: " . ($response['error_description'] ?? 'Failed to obtain token.'));
        }
        
        $accessToken = $response['access_token'];
        $expiresIn = $response['expires_in'] ?? null;
        $refreshToken = $response['refresh_token'] ?? null;

        $ch2 = curl_init(self::API_ME_URL);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Accept: application/json"
        ]);
        $profileRaw = curl_exec($ch2);
        curl_close($ch2);
        
        $profile = json_decode($profileRaw, true);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        $username = $profile['username'] ?? ($profile['name'] ?? '');
        $type = (!empty($profile['type']) && strtolower($profile['type']) === 'network') ? 'network' : 'account';
        $feedUrl = "https://feed.myetv.tv/{$type}/" . rawurlencode($username);

        $bundledToken = json_encode([
            'token'       => $accessToken,
            'type'        => $type,
            'username'    => $username,
            'profile_url' => $profile['profile_url'] ?? '',
            'feed_url'    => $feedUrl
        ]);

        return [
            'access_token'     => $bundledToken,
            'platform_user_id' => $profile['id'] ?? 'unknown_id',
            'platform_name'    => $profile['username'] ?? $profile['name'] ?? 'MYETV Network',
            'expires_in'       => $expiresIn,
            'refresh_token'    => $refreshToken
        ];
    }

    /**
     * Live verification of MYETV user access token
     */
    public function checkTokenStatus(string $accessToken): array {
        $realToken = $accessToken;
        $decoded = json_decode($accessToken, true);
        if (is_array($decoded) && isset($decoded['token'])) {
            $realToken = $decoded['token'];
        }

        $ch = curl_init(self::API_ME_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$realToken}",
            "Accept: application/json"
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $profile = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && (!empty($profile['id']) || !empty($profile['username']))) {
            $name = $profile['username'] ?? $profile['name'] ?? 'User';
            return [
                'valid' => true,
                'message' => "MYETV active (@{$name})"
            ];
        }

        $errorMsg = $profile['message'] ?? $profile['error_description'] ?? "Session expired or invalid (HTTP {$httpCode})";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    /**
     * Publishes content to MYETV based on official API v1 documentation.
     */
    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $realToken = $accessToken;
        $decoded = json_decode($accessToken, true);
        if (is_array($decoded) && isset($decoded['token'])) {
            $realToken = $decoded['token'];
        }

        $plainContent = trim(html_entity_decode(strip_tags($content)));

        $payload = [
            'type'    => 'text',
            'text'    => $plainContent,
            'privacy' => 'public'
        ];

        if (!empty($mediaUrls[0])) {
            $payload['type'] = 'image';
            $payload['image_url'] = $mediaUrls[0];
        } elseif (!empty($sourceLink)) {
            $payload['type'] = 'link';
            $payload['link_url'] = $sourceLink;
        }

        $ch = curl_init(self::API_POST_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $realToken,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'error'   => 'cURL Error: ' . $curlError
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && (isset($responseData['status']) && $responseData['status'] === 'success')) {
            return [
                'success'  => true,
                'post_id'  => $responseData['content_id'] ?? ($responseData['id'] ?? 'myetv_' . time())
            ];
        }

        return [
            'success' => false,
            'error'   => $responseData['message'] ?? 'API Error (HTTP ' . $httpCode . ')'
        ];
    }

    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Open MYETV Developers</h6>
            <p class="text-muted small mb-0">
                Go to the <a href="https://developers.myetv.tv/" target="_blank" class="fw-bold">MYETV Developer Console</a> and create or select your OAuth App.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Set Authorized Redirect URI</h6>
            <p class="text-muted small mb-1">
                In your App settings, ensure the <strong>Redirect URI</strong> is set to:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminMyetvRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminMyetvRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Copy App Credentials</h6>
            <p class="text-muted small mb-0">
                Copy your <strong>Client ID</strong> and <strong>Client Secret</strong> from the MYETV Developer Console and save them below.
            </p>
        </div>
        ';
    }
}