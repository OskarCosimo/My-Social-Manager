<?php
// plugins/TikTok/TikTokPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class TikTokPlugin implements SocialPluginInterface {

    public function getId(): string {
        return 'tiktok';
    }

    public function getName(): string {
        return 'TikTok Account';
    }

    /**
     * Get official brand color hex code for TikTok
     */
    public function getColor(): string {
        return '#00F2FE';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'TikTok Client Key / App Key',
                'placeholder' => 'Enter Client Key'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'TikTok Client Secret',
                'placeholder' => 'Enter Client Secret'
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
        $callbackUrl = $this->getCallbackUrl();
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK (Bring Your Own Key) Setup:</strong><br>
            For TikTok. Leave blank to use System Default App.<br><br>
            1. Go to <a href="https://developers.tiktok.com/" target="_blank">TikTok for Developers</a> and create an app.<br>
            2. Enable <strong>Content Posting API</strong> and <strong>Login Kit</strong>.<br>
            3. Set the <strong>Redirect URI</strong> to:<br>
                <code>' . $callbackUrl . '</code>
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom Client Key</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom Client Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        // STEP 1: BYOK redirect
        if (isset($requestData['byok_submit'])) {
            $clientKey = trim($requestData['custom_client_id'] ?? '');
            $clientSecret = trim($requestData['custom_client_secret'] ?? '');
            $isCustom = true;

            if (empty($clientKey) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientKey = $settings['client_id'] ?? $settings['client_key'] ?? '';
                $clientSecret = $settings['client_secret'] ?? '';
                $isCustom = false;
            }

            if (empty($clientKey)) {
                throw new Exception("TikTok Client Key is missing. Admin configuration required or custom keys must be provided.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientKey,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = 'user.info.basic,video.upload,video.publish';
            $state = urlencode($this->getId());

            $oauthUrl = "https://www.tiktok.com/v2/auth/authorize/?client_key={$clientKey}&response_type=code&scope={$scopes}&redirect_uri={$redirectUri}&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: OAuth Callback
        if (isset($requestData['error'])) {
            throw new Exception("TikTok Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientKey = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        // Exchange code for user token
        $tokenUrl = "https://open.tiktokapis.com/v2/oauth/token/";
        $postData = [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];
        $accessToken = $response['access_token'] ?? $response['data']['access_token'] ?? '';
        $openId = $response['open_id'] ?? $response['data']['open_id'] ?? '';
        $expiresIn = $response['expires_in'] ?? $response['data']['expires_in'] ?? null;
        $refreshToken = $response['refresh_token'] ?? $response['data']['refresh_token'] ?? null;

        if (empty($accessToken)) {
            throw new Exception($response['error_description'] ?? $response['error']['message'] ?? 'Failed to retrieve TikTok Access Token.');
        }

        // Fetch user profile info
        $userUrl = "https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name,avatar_url";
        $userCh = curl_init($userUrl);
        curl_setopt($userCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($userCh, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        $userResult = curl_exec($userCh);
        curl_close($userCh);

        $userResponse = json_decode($userResult, true) ?? [];
        $displayName = $userResponse['data']['user']['display_name'] ?? ('TikTok_' . substr($openId, 0, 6));

        $finalToken = json_encode([
            'access_token' => $accessToken,
            'open_id' => $openId,
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'is_custom' => $byok['is_custom'] ?? false
        ]);

        unset($_SESSION['byok_' . $this->getId()]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $openId,
            'platform_name' => $displayName,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Live verification of TikTok Access Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $token = (is_array($decoded) && isset($decoded['access_token'])) ? $decoded['access_token'] : $accessToken;

        $userUrl = "https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name,avatar_url";
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $userResponse = json_decode($result, true) ?? [];

        if (isset($userResponse['data']['user']['display_name'])) {
            $displayName = $userResponse['data']['user']['display_name'];
            return [
                'valid' => true,
                'message' => "TikTok active (@{$displayName})"
            ];
        }

        $errorMsg = $userResponse['error']['message'] ?? "TikTok session expired or invalid (HTTP {$httpCode})";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken): array {
        return $this->publishPostWithLink($content, $mediaUrls, $accessToken, null);
    }

    public function publishPostWithLink(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Invalid TikTok credentials.'];
        }

        $token = $decoded['access_token'] ?? '';

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanMessage = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if (!empty($sourceLink)) {
            $cleanMessage .= " " . $sourceLink;
        }

        $videoUrl = $mediaUrls[0] ?? null;
        if (empty($videoUrl)) {
            return ['success' => false, 'error' => 'TikTok requires a media URL (Video/Photo) to publish.'];
        }

        // TikTok Content Posting API Init
        $url = "https://open.tiktokapis.com/v2/post/publish/video/init/";
        $payload = json_encode([
            'post_info' => [
                'title' => mb_strimwidth($cleanMessage, 0, 2200, "..."),
                'privacy_level' => 'PUBLIC_TO_EVERYONE',
                'disable_duet' => false,
                'disable_stitch' => false,
                'disable_comment' => false
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $videoUrl
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if (isset($response['error']['code']) && $response['error']['code'] !== 'ok') {
            return ['success' => false, 'error' => $response['error']['message'] ?? 'TikTok API error.'];
        }

        return ['success' => true, 'post_id' => $response['data']['publish_id'] ?? 'tiktok_' . time()];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Register on TikTok for Developers</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://developers.tiktok.com/" target="_blank" class="fw-bold">TikTok for Developers</a>, log in, and click <strong>Manage Apps &rarr; Create an App</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Enable Products & Scopes</h6>
            <p class="text-muted small mb-1">
                In your App dashboard, add and configure:
            </p>
            <ul class="small mb-1">
                <li><strong>Login Kit</strong> (for authentication: <code>user.info.basic</code>).</li>
                <li><strong>Content Posting API</strong> (for video/photo publishing: <code>video.upload</code>, <code>video.publish</code>).</li>
            </ul>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Set Redirect URI</h6>
            <p class="text-muted small mb-1">
                In the <strong>Login Kit</strong> configuration, set the <strong>Redirect Domain / URI</strong> to:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminTikTokRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminTikTokRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 4: Copy Client Key & Secret</h6>
            <p class="text-muted small mb-0">
                Copy your <strong>Client Key</strong> and <strong>Client Secret</strong> from the App overview tab and save them below.
            </p>
        </div>
        ';
    }
}