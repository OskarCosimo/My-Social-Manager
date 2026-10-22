<?php
// plugins/Threads/ThreadsPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class ThreadsPlugin implements SocialPluginInterface {
    
    private const API_VERSION = 'v1.0';
    private const GRAPH_URL = 'https://graph.threads.net';

    public function getId(): string {
        return 'threads';
    }

    public function getName(): string {
        return 'Threads (Meta)';
    }

    /**
     * Get official brand color hex code for Threads
     */
    public function getColor(): string {
        return '#101010';
    }

    public function getSettingsFields(): array {
        return [
            'app_id' => [
                'type' => 'text',
                'label' => 'Threads App ID',
                'placeholder' => 'Enter Meta / Threads App ID'
            ],
            'app_secret' => [
                'type' => 'password',
                'label' => 'Threads App Secret',
                'placeholder' => 'Enter Meta / Threads App Secret'
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
            <strong>BYOK Setup for Threads:</strong><br>
            If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://developers.facebook.com/" target="_blank">Meta for Developers</a> and open or create your App.<br>
            2. Add the <strong>Threads API</strong> use case to your app.<br>
            3. Set the <strong>Redirect Callback URL</strong> to:<br>
               <code>' . $callbackUrl . '</code><br>
            4. Required permissions: <code>threads_basic</code>, <code>threads_content_publish</code>.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom Threads App ID</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom Threads App Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        // STEP 1: Intercept BYOK submission and redirect to Threads OAuth
        if (isset($requestData['byok_submit'])) {
            $appId = trim($requestData['custom_client_id'] ?? '');
            $appSecret = trim($requestData['custom_client_secret'] ?? '');
            $isCustom = true;

            if (empty($appId) || empty($appSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $appId = $settings['app_id'] ?? '';
                $appSecret = $settings['app_secret'] ?? '';
                $isCustom = false;
            }

            if (empty($appId)) {
                throw new Exception("Threads App ID is missing. Admin configuration or custom BYOK keys required.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = 'threads_basic,threads_content_publish';
            $state = urlencode($this->getId());

            $oauthUrl = "https://threads.net/oauth/authorize?client_id={$appId}&redirect_uri={$redirectUri}&scope={$scopes}&response_type=code&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: Handle OAuth return code
        if (isset($requestData['error'])) {
            throw new Exception("Threads Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $appId = $byok['client_id'] ?? '';
        $appSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        // Exchange code for short-lived access token
        $tokenUrl = self::GRAPH_URL . '/oauth/access_token';
        $postData = [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $resRaw = curl_exec($ch);
        curl_close($ch);

        $shortRes = json_decode($resRaw, true) ?? [];
        if (isset($shortRes['error_message']) || empty($shortRes['access_token'])) {
            throw new Exception("Threads Token Error: " . ($shortRes['error_message'] ?? 'Failed to exchange code.'));
        }

        $shortToken = $shortRes['access_token'];
        $userId = (string)($shortRes['user_id'] ?? '');

        // Exchange short-lived token for long-lived token (60 days)
        $exchangeUrl = self::GRAPH_URL . "/access_token?grant_type=th_exchange_token&client_secret={$appSecret}&access_token={$shortToken}";
        $ch2 = curl_init($exchangeUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $longRaw = curl_exec($ch2);
        curl_close($ch2);

        $longRes = json_decode($longRaw, true) ?? [];
        $finalToken = $longRes['access_token'] ?? $shortToken;
        $expiresIn = $longRes['expires_in'] ?? 5184000; // Default 60 days in seconds

        // Fetch user profile name/handle
        $meUrl = self::GRAPH_URL . "/" . self::API_VERSION . "/me?fields=id,username,threads_profile_picture_url&access_token={$finalToken}";
        $ch3 = curl_init($meUrl);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
        $meRaw = curl_exec($ch3);
        curl_close($ch3);

        $meData = json_decode($meRaw, true) ?? [];
        $username = $meData['username'] ?? ('user_' . $userId);

        $bundledToken = json_encode([
            'access_token'  => $finalToken,
            'threads_user_id' => $userId,
            'app_id'        => $appId,
            'app_secret'    => $appSecret,
            'is_custom'     => $byok['is_custom'] ?? false
        ]);

        unset($_SESSION['byok_' . $this->getId()]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token'     => $bundledToken,
            'platform_user_id' => $userId,
            'platform_name'    => '@' . $username . ' (Threads)',
            'expires_in'       => $expiresIn
        ];
    }

    /**
     * Live verification of Threads Access Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['access_token'])) ? $decoded['access_token'] : $accessToken;

        $url = self::GRAPH_URL . "/" . self::API_VERSION . "/me?fields=id,username&access_token=" . urlencode($realToken);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['id'])) {
            $username = $res['username'] ?? 'User';
            return [
                'valid'   => true,
                'message' => "Threads active (@{$username})"
            ];
        }

        $errorMsg = $res['error']['message'] ?? "Threads token expired or invalid (HTTP {$httpCode}). Please reconnect.";
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
            return ['success' => false, 'error' => 'Invalid Threads credentials.'];
        }

        $token = $decoded['access_token'] ?? '';
        $userId = $decoded['threads_user_id'] ?? 'me';

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanMessage = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Step 1: Create Media Container
        $containerUrl = self::GRAPH_URL . "/" . self::API_VERSION . "/{$userId}/threads";
        $containerData = [
            'access_token' => $token,
            'text'         => $cleanMessage
        ];

        // Attach image or external link card
        if (!empty($mediaUrls[0])) {
            $containerData['media_type'] = 'IMAGE';
            $containerData['image_url']  = $mediaUrls[0];
        } else {
            $containerData['media_type'] = 'TEXT';
            if (!empty($sourceLink)) {
                $containerData['link_attachment'] = $sourceLink;
            }
        }

        $ch1 = curl_init($containerUrl);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query($containerData));
        $res1Raw = curl_exec($ch1);
        curl_close($ch1);

        $res1 = json_decode($res1Raw, true) ?? [];
        if (isset($res1['error']) || empty($res1['id'])) {
            return ['success' => false, 'error' => $res1['error']['message'] ?? 'Failed to create Threads container.'];
        }

        $creationId = $res1['id'];

        // Short pause to ensure Meta media ingestion is ready
        sleep(2);

        // Step 2: Publish the container
        $publishUrl = self::GRAPH_URL . "/" . self::API_VERSION . "/{$userId}/threads_publish";
        $publishData = [
            'creation_id'  => $creationId,
            'access_token' => $token
        ];

        $ch2 = curl_init($publishUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($publishData));
        $res2Raw = curl_exec($ch2);
        curl_close($ch2);

        $res2 = json_decode($res2Raw, true) ?? [];
        if (isset($res2['error']) || empty($res2['id'])) {
            return ['success' => false, 'error' => $res2['error']['message'] ?? 'Failed to publish Thread.'];
        }

        return ['success' => true, 'post_id' => $res2['id']];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Create an App on Meta for Developers</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://developers.facebook.com/apps/" target="_blank" class="fw-bold">Meta for Developers</a>, click <strong>Create App</strong>, and choose <strong>Business</strong> or <strong>Other</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Add Threads API Product</h6>
            <p class="text-muted small mb-0">
                In your App dashboard, under <strong>Use cases</strong> or <strong>Add Products</strong>, add the <strong>Threads API</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Set Redirect Callback URL</h6>
            <p class="text-muted small mb-1">
                In the Threads API Settings, enter the following <strong>Redirect Callback URL</strong>:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminThreadsRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminThreadsRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 4: Required Scopes & Test Users</h6>
            <p class="text-muted small mb-0">
                Permissions required: <code>threads_basic</code>, <code>threads_content_publish</code>.<br>
                <em>Tip: While your Meta App is in Development mode, go to <strong>App Roles &rarr; Roles</strong> and add your Instagram/Threads account as a Tester.</em>
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 5: Copy App ID & Secret</h6>
            <p class="text-muted small mb-0">
                Go to <strong>App settings &rarr; Basic</strong>, copy your <strong>App ID</strong> and <strong>App Secret</strong>, paste them below, and click <strong>Save Config</strong>.
            </p>
        </div>
        ';
    }
}