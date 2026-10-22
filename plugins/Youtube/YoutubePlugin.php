<?php
// plugins/Youtube/YoutubePlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';

class YoutubePlugin implements SocialPluginInterface {
    
    private const API_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_YT_CHANNELS = 'https://www.googleapis.com/youtube/v3/channels';
    private const API_YT_UPLOAD = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';

    public function getId(): string {
        return 'youtube';
    }

    public function getName(): string {
        return 'YouTube Channel';
    }

    /**
     * Get official brand color hex code for YouTube
     */
    public function getColor(): string {
        return '#FF0000';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'Google Client ID',
                'placeholder' => 'Enter Client ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'Google Client Secret',
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

    /**
     * Display the BYOK form to the user with explicit instructions
     */
    public function getManualSetupHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK (Bring Your Own Key) Setup:</strong><br>
            YouTube API quotas for video uploads are very strict. Add your own Google Cloud Client ID to bypass shared limits.<br><br>
            1. Go to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create a project.<br>
            2. Enable the <strong>YouTube Data API v3</strong> in the Library.<br>
            3. Go to Credentials and create an <strong>OAuth 2.0 Client ID (Web application)</strong>.<br>
            4. Add the following to <strong>Authorized redirect URIs</strong>:<br>
               <code>' . $callbackUrl . '</code><br>
            5. Ensure your app has access to the <code>youtube.upload</code> and <code>youtube.readonly</code> scopes.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom Google Client ID</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom Google Client Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (isset($requestData['byok_submit'])) {
            $clientId = trim($requestData['custom_client_id'] ?? '');
            $clientSecret = trim($requestData['custom_client_secret'] ?? '');
            $isCustom = true;

            if (empty($clientId) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientId = $settings['client_id'] ?? '';
                $clientSecret = $settings['client_secret'] ?? '';
                $isCustom = false;
            }

            if (empty($clientId)) {
                throw new Exception("Google Client ID missing.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly'); 
            $state = urlencode($this->getId());
            
            $oauthUrl = "https://accounts.google.com/o/oauth2/v2/auth?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scopes}&state={$state}&access_type=offline&prompt=consent";
            header("Location: " . $oauthUrl);
            exit;
        }

        if (isset($requestData['error'])) {
            throw new Exception("YouTube Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientId = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';
        
        $postData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->getCallbackUrl()
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
        
        $ch2 = curl_init(self::API_YT_CHANNELS . '?part=snippet&mine=true');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        $channelRaw = curl_exec($ch2);
        curl_close($ch2);
        
        $channelData = json_decode($channelRaw, true);

        if (empty($channelData['items'])) {
            throw new Exception("No YouTube Channel found for this Google Account.");
        }

        $channel = $channelData['items'][0];
        
        $finalToken = json_encode([
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ]);
        
        unset($_SESSION['byok_' . $this->getId()]);

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $channel['id'],
            'platform_name' => $channel['snippet']['title'] ?? 'YouTube Channel',
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Live verification of YouTube/Google access token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        $ch = curl_init(self::API_YT_CHANNELS . '?part=snippet&mine=true');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$realToken}"]);
        $channelRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $channelData = json_decode($channelRaw, true) ?? [];

        if ($httpCode === 200 && !empty($channelData['items'][0]['snippet']['title'])) {
            $channelTitle = $channelData['items'][0]['snippet']['title'];
            return [
                'valid' => true,
                'message' => "YouTube active ({$channelTitle})"
            ];
        }

        $errorMsg = $channelData['error']['message'] ?? "Google access token expired or invalid (HTTP {$httpCode}). Please reconnect.";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        if (empty($mediaUrls)) {
            return ['success' => false, 'error' => 'YouTube requires a valid video file path.'];
        }

        $videoPath = $mediaUrls[0];
        if (!file_exists($videoPath)) {
            return ['success' => false, 'error' => "Video file not found on server: {$videoPath}. Requires File Manager integration."];
        }

        return ['success' => false, 'error' => 'YouTube Upload logic pending File Manager implementation.'];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Create Google Cloud Project</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://console.cloud.google.com/" target="_blank" class="fw-bold">Google Cloud Console</a> and create or select a project.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Enable YouTube Data API v3</h6>
            <p class="text-muted small mb-0">
                Go to <strong>APIs & Services &rarr; Library</strong>, search for <strong>YouTube Data API v3</strong>, and click <strong>Enable</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Configure Consent Screen & Scopes</h6>
            <p class="text-muted small mb-0">
                Under <strong>OAuth consent screen</strong>, select External and add scopes:<br>
                <code>https://www.googleapis.com/auth/youtube.upload</code> and <code>https://www.googleapis.com/auth/youtube.readonly</code>.<br>
                While in testing, add your Google account to <strong>Test Users</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 4: Create OAuth Client ID</h6>
            <p class="text-muted small mb-1">
                Go to <strong>Credentials &rarr; Create Credentials &rarr; OAuth client ID</strong> (Web application) and add this Authorized redirect URI:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminYtRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminYtRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 5: Copy Keys</h6>
            <p class="text-muted small mb-0">
                Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> and paste them below.
            </p>
        </div>
        ';
    }
}