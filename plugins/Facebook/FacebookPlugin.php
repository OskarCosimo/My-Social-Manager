<?php
// plugins/Facebook/FacebookPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class FacebookPlugin implements SocialPluginInterface {
    
    private const API_VERSION = 'v19.0';
    
    public function getId(): string {
        return 'facebook';
    }

    public function getName(): string {
        return 'Facebook Pages';
    }

    /**
     * Get official brand color hex code for Facebook
     */
    public function getColor(): string {
        return '#1877F2';
    }

    public function getSettingsFields(): array {
        return [
            'app_id' => [
                'type' => 'text',
                'label' => 'Facebook App ID',
                'placeholder' => 'Enter App ID'
            ],
            'app_secret' => [
                'type' => 'password',
                'label' => 'Facebook App Secret',
                'placeholder' => 'Enter App Secret'
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
     * Display the BYOK form to the user with explicit instructions
     */
    public function getManualSetupHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK (Bring Your Own Key) Setup:</strong><br>
            For Facebook Pages. If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> and create a new App.<br>
            2. Add the Facebook Login product and set the <strong>Valid OAuth Redirect URI</strong> to:<br>
                <code>' . $callbackUrl . '</code><br>
            3. Ensure your app has the following permissions requested: <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom App ID</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom App Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        // STEP 0: Check return from Facebook account selection form
        if (isset($_POST['selected_fb_page_id']) && isset($_SESSION['temp_fb_accounts'])) {
            $selectedId = $_POST['selected_fb_page_id'];
            $accounts = $_SESSION['temp_fb_accounts'];
            $byok = $_SESSION['temp_fb_byok'] ?? [];
            
            $account = null;
            foreach ($accounts as $a) {
                if ($a['id'] === $selectedId) {
                    $account = $a;
                    break;
                }
            }
            
            if (!$account) {
                throw new Exception("Invalid Facebook account.");
            }

            $appId = $byok['client_id'] ?? '';
            $appSecret = $byok['client_secret'] ?? '';

            // Store the FB ID inside the token structure
            $finalToken = json_encode([
                'token' => $account['access_token'],
                'page_id' => $account['id'],
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'is_custom' => $byok['is_custom'] ?? false
            ]);

            // Calculate expiration date if available
            $expiresAt = $this->inspectTokenExpiry($account['access_token'], $appId, $appSecret);
            
            // Cleanup session
            unset($_SESSION['temp_fb_accounts'], $_SESSION['temp_fb_byok'], $_SESSION['byok_' . $this->getId()]);
            
            require_once __DIR__ . '/../../app/Models/UsageManager.php';
            if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
                throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
            }

            return [
                'access_token' => $finalToken,
                'platform_user_id' => $account['id'],
                'platform_name' => $account['name'],
                'token_expires_at' => $expiresAt
            ];
        }

        // STEP 1: Intercept BYOK form submission and redirect
        if (isset($requestData['byok_submit'])) {
            $clientId = trim($requestData['custom_client_id'] ?? '');
            $clientSecret = trim($requestData['custom_client_secret'] ?? '');
            $isCustom = true;

            if (empty($clientId) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientId = $settings['app_id'] ?? '';
                $clientSecret = $settings['app_secret'] ?? '';
                $isCustom = false;
            }

            if (empty($clientId)) {
                throw new Exception("App ID is missing. Admin configuration required or custom keys must be provided.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = 'pages_show_list,pages_read_engagement,pages_manage_posts';
            $state = urlencode($this->getId());
            
            $oauthUrl = "https://www.facebook.com/" . self::API_VERSION . "/dialog/oauth?client_id={$clientId}&redirect_uri={$redirectUri}&scope={$scopes}&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: Handle returning OAuth code
        if (isset($requestData['error'])) {
            throw new Exception("Facebook Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $appId = $byok['client_id'] ?? '';
        $appSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        // Exchange code for User Access Token
        $tokenUrl = "https://graph.facebook.com/" . self::API_VERSION . "/oauth/access_token?client_id={$appId}&redirect_uri={$redirectUri}&client_secret={$appSecret}&code={$code}";
        $response = $this->curlGet($tokenUrl);
        if (isset($response['error'])) {
            throw new Exception($response['error']['message']);
        }
        
        $userAccessToken = $response['access_token'];

        // Get Long-Lived Token
        $longTokenUrl = "https://graph.facebook.com/" . self::API_VERSION . "/oauth/access_token?grant_type=fb_exchange_token&client_id={$appId}&client_secret={$appSecret}&fb_exchange_token={$userAccessToken}";
        $longResponse = $this->curlGet($longTokenUrl);
        $userAccessToken = $longResponse['access_token'] ?? $userAccessToken;

        // Find Facebook Pages
        $pagesUrl = "https://graph.facebook.com/" . self::API_VERSION . "/me/accounts?fields=name,access_token,id&access_token={$userAccessToken}";
        $pagesResponse = $this->curlGet($pagesUrl);

        $validFbAccounts = $pagesResponse['data'] ?? [];

        if (empty($validFbAccounts)) {
            throw new Exception("No Facebook Pages found. Make sure you have at least one Page created on your account.");
        }

        // STEP 3: FACEBOOK ACCOUNT SELECTION (If there are multiple connected Pages)
        if (count($validFbAccounts) > 1) {
            $_SESSION['temp_fb_accounts'] = $validFbAccounts;
            $_SESSION['temp_fb_byok'] = $byok;
            
            $html = '<div style="font-family: system-ui, sans-serif; max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">';
            $html .= '<h3 style="margin-top: 0; color: #1877F2;">Select Facebook Page</h3>';
            $html .= '<p style="color: #555; font-size: 14px;">Multiple Pages found. Select which one to connect:</p>';
            $html .= '<form method="POST" action="">';
            foreach ($validFbAccounts as $a) {
                $html .= '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 5px; cursor: pointer;">';
                $html .= '<label style="cursor: pointer; display: block; font-weight: 500;">';
                $html .= '<input type="radio" name="selected_fb_page_id" value="'.$a['id'].'" required style="margin-right: 10px;"> ' . htmlspecialchars($a['name']);
                $html .= '</label></div>';
            }
            $html .= '<button type="submit" style="margin-top: 15px; width: 100%; padding: 12px; background: #1877F2; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Connect Facebook Page</button>';
            $html .= '</form></div>';
            echo $html;
            exit;
        }

        // Auto-selection if there is only 1
        $account = $validFbAccounts[0];
        $finalToken = json_encode([
            'token' => $account['access_token'],
            'page_id' => $account['id'],
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'is_custom' => $byok['is_custom'] ?? false
        ]);

        $expiresAt = $this->inspectTokenExpiry($account['access_token'], $appId, $appSecret);

        unset($_SESSION['byok_' . $this->getId()]);

        require_once __DIR__ . '/../../app/Models/UsageManager.php';
        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $account['id'],
            'platform_name' => $account['name'],
            'token_expires_at' => $expiresAt
        ];
    }

    /**
     * Live verification of Facebook Page token validity
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $pageId = (is_array($decoded) && isset($decoded['page_id'])) ? $decoded['page_id'] : 'me';
        $appId = (is_array($decoded) && isset($decoded['client_id'])) ? $decoded['client_id'] : '';
        $appSecret = (is_array($decoded) && isset($decoded['client_secret'])) ? $decoded['client_secret'] : '';

        // Verify Page access via Graph API
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$pageId}?fields=id,name&access_token=" . urlencode($realToken);
        $res = $this->curlGet($url);

        if (isset($res['error'])) {
            return [
                'valid' => false,
                'message' => $res['error']['message'] ?? 'Token invalid or expired.'
            ];
        }

        $pageName = $res['name'] ?? 'Facebook Page';
        $expiresAt = $this->inspectTokenExpiry($realToken, $appId, $appSecret);

        return [
            'valid' => true,
            'message' => "Page token is active ({$pageName})",
            'expires_at' => $expiresAt
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken): array {
        return $this->publishPostWithLink($content, $mediaUrls, $accessToken, null);
    }

    public function publishPostWithLink(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        // Extract token and Page ID from JSON wrapper
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $pageId = (is_array($decoded) && isset($decoded['page_id'])) ? $decoded['page_id'] : 'me';

        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$pageId}/feed";

        // 1. Convert HTML tags to newline characters and clean input
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanMessage = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $data = [
            'access_token' => $realToken
        ];

        // Ensure link previews generate correctly for RSS
        if (!empty($sourceLink)) {
            $cleanMessage = trim(str_replace($sourceLink, '', $cleanMessage));
            $data['link'] = $sourceLink;
        }

        $data['message'] = $cleanMessage;

        // Fallback for image posts if no link is provided
        if (empty($sourceLink) && !empty($mediaUrls)) {
            $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$pageId}/photos";
            $data['url'] = $mediaUrls[0];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if (isset($response['error'])) {
            return ['success' => false, 'error' => $response['error']['message']];
        }

        return ['success' => true, 'post_id' => $response['id'] ?? 'unknown'];
    }

    /**
     * Inspect token expiration timestamp using Facebook debug_token endpoint
     */
    private function inspectTokenExpiry(string $token, string $appId, string $appSecret): ?string {
        if (empty($appId) || empty($appSecret)) {
            $settings = PluginSetting::getForPlugin($this->getId());
            $appId = $settings['app_id'] ?? '';
            $appSecret = $settings['app_secret'] ?? '';
        }

        if (empty($appId) || empty($appSecret)) {
            return null;
        }

        $debugUrl = "https://graph.facebook.com/" . self::API_VERSION . "/debug_token?input_token=" . urlencode($token) . "&access_token={$appId}|{$appSecret}";
        $debugRes = $this->curlGet($debugUrl);

        if (!empty($debugRes['data']['expires_at']) && $debugRes['data']['expires_at'] > 0) {
            return date('Y-m-d H:i:s', $debugRes['data']['expires_at']);
        }

        return null;
    }

    private function curlGet(string $url): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
            <h6 class="fw-bold text-primary mb-1">Step 1: Create a Meta App</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://developers.facebook.com/" target="_blank" class="fw-bold">Meta for Developers</a>, log in, and click <strong>Create App</strong>. Select <strong>Business</strong> as the app type.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Add Facebook Login Product</h6>
            <p class="text-muted small mb-1">
                Add the <strong>Facebook Login for Business</strong> product to your App. Under <strong>Settings</strong>, set the <strong>Valid OAuth Redirect URI</strong> to:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminFbRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminFbRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Required Permissions</h6>
            <p class="text-muted small mb-0">
                Ensure your app requests the following permissions for Page posting:<br>
                <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 4: Copy Keys & Switch Live</h6>
            <p class="text-muted small mb-0">
                Go to <strong>App settings &rarr; Basic</strong>, copy your <strong>App ID</strong> and <strong>App Secret</strong>, paste them below, and switch your app mode to <strong>Live</strong>.
            </p>
        </div>
        ';
    }
}