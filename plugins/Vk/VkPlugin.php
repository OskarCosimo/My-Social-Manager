<?php
// plugins/Vk/VkPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class VkPlugin implements SocialPluginInterface {
    
    // VK requires the API version to be explicitly passed in requests
    private const API_VERSION = '5.199'; 
    private const API_BASE_URL = 'https://api.vk.com/method';
    private const OAUTH_BASE_URL = 'https://oauth.vk.com';
    
    public function getId(): string {
        return 'vk';
    }

    public function getName(): string {
        return 'VK.com';
    }

    /**
     * Get official brand color hex code for VK.com
     */
    public function getColor(): string {
        return '#0077FF';
    }

    public function getSettingsFields(): array {
        return [
            'app_id' => [
                'type' => 'text',
                'label' => 'VK App ID',
                'placeholder' => 'Enter your VK App ID'
            ],
            'app_secret' => [
                'type' => 'password',
                'label' => 'VK Secure Key (Secret)',
                'placeholder' => 'Enter your VK Secure Key'
            ]
        ];
    }

    private function getCallbackUrl(): string {
        return Config::APP_URL . '/plugin/callback';
    }

    /**
     * Redirect to the local manual setup form to offer the BYOK choice
     */
    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    /**
     * Display the BYOK form to the user with explicit instructions for VK
     */
    public function getManualSetupHtml(): string {
        $callbackUrl = $this->getCallbackUrl();
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>VK.com (VKontakte) Setup:</strong><br>
            To avoid global rate limits, you can use your own VK App. If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://vk.com/apps?act=manage" target="_blank">VK Developers</a> and create a new App (Standalone or Website).<br>
            2. In the App settings, set the <strong>Authorized redirect URI</strong> to:<br>
               <code>' . $callbackUrl . '</code><br>
            3. Note: The app will request "wall" and "offline" permissions to post on your behalf.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom VK App ID</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom VK Secure Key</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    /**
     * Handles the OAuth flow for VK
     */
    public function handleCallback(array $requestData): array {
        // STEP 1: Intercept the BYOK form submission and trigger OAuth redirect
        if (isset($requestData['byok_submit'])) {
            $clientId = trim($requestData['custom_client_id'] ?? '');
            $clientSecret = trim($requestData['custom_client_secret'] ?? '');
            $isCustom = true;

            // Fallback to global Admin settings if user left fields blank
            if (empty($clientId) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientId = $settings['app_id'] ?? '';
                $clientSecret = $settings['app_secret'] ?? '';
                $isCustom = false;
            }

            if (empty($clientId)) {
                throw new Exception("VK App ID is missing. Admin configuration required or custom keys must be provided.");
            }

            // Store keys in session temporarily for the OAuth exchange
            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            // scopes: wall (to post), offline (token doesn't expire quickly)
            $scopes = 'wall,offline'; 
            $state = urlencode($this->getId());
            
            $oauthUrl = self::OAUTH_BASE_URL . "/authorize?client_id={$clientId}&display=page&redirect_uri={$redirectUri}&scope={$scopes}&response_type=code&v=" . self::API_VERSION . "&state={$state}";
            
            // Redirect the user to VK immediately
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: Handle the returning OAuth code
        if (isset($requestData['error'])) {
            throw new Exception("VK Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing from VK.");
        }

        // Retrieve the stored BYOK keys
        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $appId = $byok['client_id'] ?? '';
        $appSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        // Exchange Code for User Access Token
        $tokenUrl = self::OAUTH_BASE_URL . "/access_token?client_id={$appId}&client_secret={$appSecret}&redirect_uri={$redirectUri}&code={$code}";
        
        $response = $this->curlGet($tokenUrl);
        
        if (isset($response['error'])) {
            throw new Exception($response['error_description'] ?? 'Failed to retrieve VK access token');
        }
        
        $userAccessToken = $response['access_token'];
        $vkUserId = $response['user_id'];
        $expiresIn = (!empty($response['expires_in']) && (int)$response['expires_in'] > 0) ? (int)$response['expires_in'] : null;

        // Get basic user info to display the name in the dashboard
        $userInfoUrl = self::API_BASE_URL . "/users.get?user_ids={$vkUserId}&access_token={$userAccessToken}&v=" . self::API_VERSION;
        $userInfo = $this->curlGet($userInfoUrl);
        
        $platformName = "VK User {$vkUserId}";
        if (isset($userInfo['response'][0])) {
            $user = $userInfo['response'][0];
            $platformName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }

        // Bundle custom keys with the token
        $finalToken = $userAccessToken;
        if (!empty($byok['is_custom'])) {
            $finalToken = json_encode([
                'token' => $userAccessToken,
                'client_id' => $appId,
                'client_secret' => $appSecret
            ]);
        }

        // Cleanup session
        unset($_SESSION['byok_' . $this->getId()]);

        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User session not found.");
        }

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $finalToken,
            'platform_user_id' => (string)$vkUserId,
            'platform_name' => $platformName,
            'expires_in' => $expiresIn
        ];
    }

    /**
     * Live verification of VK access token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        $url = self::API_BASE_URL . "/users.get?access_token=" . urlencode($realToken) . "&v=" . self::API_VERSION;
        $res = $this->curlGet($url);

        if (isset($res['response'][0]['id'])) {
            $user = $res['response'][0];
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            return [
                'valid' => true,
                'message' => "VK active ({$name})"
            ];
        }

        $errorMsg = $res['error']['error_msg'] ?? 'VK session expired or invalid. Please reconnect.';
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    /**
     * Publishes a post to VK (User's Wall)
     */
    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        // Extract the real token if it was bundled in a BYOK JSON wrapper
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        $url = self::API_BASE_URL . "/wall.post";
        
        $cleanContent = trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $attachments = [];
        if (!empty($mediaUrls[0])) {
            $attachments[] = $mediaUrls[0];
        }
        if (!empty($sourceLink)) {
            $attachments[] = $sourceLink;
        }

        $data = [
            'message' => $cleanContent,
            'access_token' => $realToken,
            'v' => self::API_VERSION
        ];

        if (!empty($attachments)) {
            $data['attachments'] = implode(',', $attachments); 
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true);

        if (isset($response['error'])) {
            return ['success' => false, 'error' => $response['error']['error_msg'] ?? 'Unknown VK Error'];
        }

        return ['success' => true, 'post_id' => (string)($response['response']['post_id'] ?? '')];
    }

    private function curlGet(string $url): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
            <h6 class="fw-bold text-primary mb-1">Step 1: Create a VK App</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://vk.com/apps?act=manage" target="_blank" class="fw-bold">VK Developers</a> and click <strong>Create App</strong>. Choose <strong>Standalone application</strong> or <strong>Website</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Configure Authorized Redirect URI</h6>
            <p class="text-muted small mb-1">
                In your App <strong>Settings</strong>, set the <strong>Authorized redirect URI</strong> to:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminVkRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminVkRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Copy App ID & Secure Key</h6>
            <p class="text-muted small mb-0">
                Copy the <strong>App ID</strong> and <strong>Secure Key</strong> from the Settings page and paste them into the fields below.
            </p>
        </div>
        ';
    }
}