<?php
// plugins/Instagram/InstagramPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class InstagramPlugin implements SocialPluginInterface {
    
    private const API_VERSION = 'v19.0';
    
    public function getId(): string {
        return 'instagram';
    }

    public function getName(): string {
        return 'Instagram (Business)';
    }

    /**
     * Get official brand color hex code for Instagram
     */
    public function getColor(): string {
        return '#E1306C';
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

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    public function getManualSetupHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK (Bring Your Own Key) Setup:</strong><br>
            For Instagram Business. If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> and create a new App.<br>
            2. Add the Facebook Login product and set the <strong>Valid OAuth Redirect URI</strong> to:<br>
               <code>' . $callbackUrl . '</code><br>
            3. Ensure your app has the following permissions requested: <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>.
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
        if (isset($_POST['selected_ig_id']) && isset($_SESSION['temp_ig_accounts'])) {
            $selectedId = $_POST['selected_ig_id'];
            $accounts = $_SESSION['temp_ig_accounts'];
            $byok = $_SESSION['temp_ig_byok'] ?? [];
            
            $account = null;
            foreach ($accounts as $a) {
                if ($a['ig_id'] === $selectedId) {
                    $account = $a;
                    break;
                }
            }
            
            if (!$account) {
                throw new Exception("Invalid Instagram account.");
            }

            $appId = $byok['client_id'] ?? '';
            $appSecret = $byok['client_secret'] ?? '';
            
            $finalToken = json_encode([
                'token' => $account['page_token'],
                'ig_id' => $account['ig_id'],
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'is_custom' => $byok['is_custom'] ?? false
            ]);

            $expiresAt = $this->inspectTokenExpiry($account['page_token'], $appId, $appSecret);
            
            unset($_SESSION['temp_ig_accounts'], $_SESSION['temp_ig_byok'], $_SESSION['byok_' . $this->getId()]);
            
            require_once __DIR__ . '/../../app/Models/UsageManager.php';
            if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
                throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
            }

            return [
                'access_token' => $finalToken,
                'platform_user_id' => $account['ig_id'],
                'platform_name' => $account['name'],
                'token_expires_at' => $expiresAt
            ];
        }

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
            $scopes = 'instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement';
            $state = urlencode($this->getId());
            
            $oauthUrl = "https://www.facebook.com/" . self::API_VERSION . "/dialog/oauth?client_id={$clientId}&redirect_uri={$redirectUri}&scope={$scopes}&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        if (isset($requestData['error'])) {
            throw new Exception("Instagram Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $appId = $byok['client_id'] ?? '';
        $appSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        $tokenUrl = "https://graph.facebook.com/" . self::API_VERSION . "/oauth/access_token?client_id={$appId}&redirect_uri={$redirectUri}&client_secret={$appSecret}&code={$code}";
        $response = $this->curlGet($tokenUrl);
        if (isset($response['error'])) {
            throw new Exception($response['error']['message']);
        }
        
        $userAccessToken = $response['access_token'];

        $pagesUrl = "https://graph.facebook.com/" . self::API_VERSION . "/me/accounts?fields=instagram_business_account,name,access_token&access_token={$userAccessToken}";
        $pagesResponse = $this->curlGet($pagesUrl);

        $validIgAccounts = [];
        if (!empty($pagesResponse['data'])) {
            foreach ($pagesResponse['data'] as $page) {
                if (isset($page['instagram_business_account']['id'])) {
                    $validIgAccounts[] = [
                        'ig_id' => $page['instagram_business_account']['id'],
                        'page_token' => $page['access_token'],
                        'name' => $page['name'] . ' (IG)'
                    ];
                }
            }
        }

        if (empty($validIgAccounts)) {
            throw new Exception("No linked Instagram Business Account found. Make sure your IG account is professional and linked to a Facebook Page.");
        }

        if (count($validIgAccounts) > 1) {
            $_SESSION['temp_ig_accounts'] = $validIgAccounts;
            $_SESSION['temp_ig_byok'] = $byok;
            
            $html = '<div style="font-family: system-ui, sans-serif; max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">';
            $html .= '<h3 style="margin-top: 0; color: #E1306C;">Select Instagram Account</h3>';
            $html .= '<p style="color: #555; font-size: 14px;">Multiple IG Business accounts found. Select which one to connect:</p>';
            $html .= '<form method="POST" action="">';
            foreach ($validIgAccounts as $a) {
                $html .= '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 5px; cursor: pointer;">';
                $html .= '<label style="cursor: pointer; display: block; font-weight: 500;">';
                $html .= '<input type="radio" name="selected_ig_id" value="'.$a['ig_id'].'" required style="margin-right: 10px;"> ' . htmlspecialchars($a['name']);
                $html .= '</label></div>';
            }
            $html .= '<button type="submit" style="margin-top: 15px; width: 100%; padding: 12px; background: #E1306C; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Connect Instagram</button>';
            $html .= '</form></div>';
            echo $html;
            exit;
        }

        $account = $validIgAccounts[0];
        $finalToken = json_encode([
            'token' => $account['page_token'],
            'ig_id' => $account['ig_id'],
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'is_custom' => $byok['is_custom'] ?? false
        ]);

        $expiresAt = $this->inspectTokenExpiry($account['page_token'], $appId, $appSecret);

        unset($_SESSION['byok_' . $this->getId()]);

        require_once __DIR__ . '/../../app/Models/UsageManager.php';
        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $account['ig_id'],
            'platform_name' => $account['name'],
            'token_expires_at' => $expiresAt
        ];
    }

    /**
     * Live verification of Instagram Business account token validity
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $igAccountId = (is_array($decoded) && isset($decoded['ig_id'])) ? $decoded['ig_id'] : null;
        $appId = (is_array($decoded) && isset($decoded['client_id'])) ? $decoded['client_id'] : '';
        $appSecret = (is_array($decoded) && isset($decoded['client_secret'])) ? $decoded['client_secret'] : '';

        if (!$igAccountId) {
            return ['valid' => false, 'message' => 'Missing Instagram Account ID.'];
        }

        // Verify IG account connection via Graph API
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$igAccountId}?fields=id,username,name&access_token=" . urlencode($realToken);
        $res = $this->curlGet($url);

        if (isset($res['error'])) {
            return [
                'valid' => false,
                'message' => $res['error']['message'] ?? 'Token invalid or expired.'
            ];
        }

        $username = $res['username'] ?? $res['name'] ?? 'Instagram Business';
        $expiresAt = $this->inspectTokenExpiry($realToken, $appId, $appSecret);

        return [
            'valid' => true,
            'message' => "Active Instagram account (@{$username})",
            'expires_at' => $expiresAt
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $igAccountId = (is_array($decoded) && isset($decoded['ig_id'])) ? $decoded['ig_id'] : null;

        if (!$igAccountId) {
            $pagesUrl = "https://graph.facebook.com/" . self::API_VERSION . "/me/accounts?fields=instagram_business_account&access_token={$realToken}";
            $pagesResponse = $this->curlGet($pagesUrl);
            $igAccountId = $pagesResponse['data'][0]['instagram_business_account']['id'] ?? null;
        }

        if (!$igAccountId) {
            return ['success' => false, 'error' => 'Could not determine IG Account ID.'];
        }

        $rawUrl = !empty($mediaUrls[0]) ? $mediaUrls[0] : null;

        if (empty($rawUrl)) {
            return ['success' => false, 'error' => 'Instagram API requires an image file. Text-only posts are not supported.'];
        }

        $imageUrl = $rawUrl;
        if (!preg_match('~^https?://~i', $imageUrl)) {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $host = $_SERVER['HTTP_HOST'] ?? parse_url(Config::APP_URL, PHP_URL_HOST);
            $baseUrl = $scheme . "://" . $host;
            $imageUrl = rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/');
        }

        // Automatic conversion of PNG/WEBP to JPG for Instagram compliance
        $parsedPath = parse_url($imageUrl, PHP_URL_PATH);
        $extension = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));

        if (in_array($extension, ['png', 'webp'])) {
            $imageUrl = $this->convertImageToJpg($imageUrl);
        }

        $plainCaption = trim(html_entity_decode(strip_tags($content)));
        if (!empty($sourceLink) && strpos($plainCaption, $sourceLink) === false) {
            $plainCaption .= "\n\n" . $sourceLink;
        }

        $isVideo = (bool)preg_match('/\.(mp4|mov)$/i', $parsedPath);

        // Step 1: Create media container
        $containerUrl = "https://graph.facebook.com/" . self::API_VERSION . "/{$igAccountId}/media";
        
        $containerData = [
            'caption'      => $plainCaption,
            'access_token' => $realToken
        ];

        if ($isVideo) {
            $containerData['media_type'] = 'REELS';
            $containerData['video_url']   = $imageUrl;
        } else {
            $containerData['image_url']   = $imageUrl;
        }

        $ch1 = curl_init($containerUrl);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query($containerData));
        $containerRes = json_decode(curl_exec($ch1), true);
        curl_close($ch1);

        if (isset($containerRes['error'])) {
            return ['success' => false, 'error' => 'IG Container Error: ' . $containerRes['error']['message'] . ' (URL: ' . $imageUrl . ')'];
        }

        $creationId = $containerRes['id'] ?? null;
        if (!$creationId) {
            return ['success' => false, 'error' => 'Failed to retrieve media creation ID from Instagram.'];
        }

        // Add a delay to allow Meta servers to process media before triggering publication
        sleep(3);

        // Step 2: Publish the container
        $publishUrl = "https://graph.facebook.com/" . self::API_VERSION . "/{$igAccountId}/media_publish";
        $publishData = [
            'creation_id'  => $creationId,
            'access_token' => $realToken
        ];

        $ch2 = curl_init($publishUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($publishData));
        $publishRes = json_decode(curl_exec($ch2), true);
        curl_close($ch2);

        if (isset($publishRes['error'])) {
            return ['success' => false, 'error' => 'IG Publish Error: ' . $publishRes['error']['message']];
        }

        return ['success' => true, 'post_id' => $publishRes['id'] ?? ''];
    }

    /**
     * Helper to convert non-JPG images (like PNG or WEBP) to JPG on the fly
     */
    private function convertImageToJpg(string $imageUrl): string {
        try {
            $parsedPath = parse_url($imageUrl, PHP_URL_PATH);
            $localFilePath = $_SERVER['DOCUMENT_ROOT'] . $parsedPath;

            if (file_exists($localFilePath)) {
                $imageInfo = getimagesize($localFilePath);
                $mime = $imageInfo['mime'] ?? '';

                $srcImage = null;
                if ($mime === 'image/png') {
                    $srcImage = imagecreatefrompng($localFilePath);
                } elseif ($mime === 'image/webp') {
                    $srcImage = imagecreatefromwebp($localFilePath);
                }

                if ($srcImage) {
                    $jpgPath = preg_replace('/\.(png|webp)$/i', '.jpg', $localFilePath);
                    
                    // Create truecolor canvas to remove alpha transparency issues
                    $bg = imagecreatetruecolor(imagesx($srcImage), imagesy($srcImage));
                    $white = imagecolorallocate($bg, 255, 255, 255);
                    imagefill($bg, 0, 0, $white);
                    imagecopy($bg, $srcImage, 0, 0, 0, 0, imagesx($srcImage), imagesy($srcImage));

                    imagejpeg($bg, $jpgPath, 90);
                    imagedestroy($srcImage);
                    imagedestroy($bg);

                    return preg_replace('/\.(png|webp)$/i', '.jpg', $imageUrl);
                }
            }
        } catch (\Throwable $e) {
            error_log("Instagram PNG->JPG conversion failed: " . $e->getMessage());
        }

        return $imageUrl;
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
            <h6 class="fw-bold text-primary mb-1">Step 1: Open Meta for Developers</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://developers.facebook.com/" target="_blank" class="fw-bold">Meta for Developers</a>. You can use the same Business App created for Facebook or create a dedicated one.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Add Instagram Graph API & Facebook Login</h6>
            <p class="text-muted small mb-1">
                Add <strong>Instagram Graph API</strong> and ensure <strong>Facebook Login for Business</strong> has the Valid OAuth Redirect URI set to:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminIgRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminIgRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Required Permissions</h6>
            <p class="text-muted small mb-0">
                Request permissions: <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>.<br>
                <em>Note: Instagram accounts must be Professional/Business accounts linked to a Facebook Page.</em>
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 4: Copy Keys</h6>
            <p class="text-muted small mb-0">
                Copy <strong>App ID</strong> and <strong>App Secret</strong> from <strong>App settings &rarr; Basic</strong> and paste them into the configuration below.
            </p>
        </div>
        ';
    }
}