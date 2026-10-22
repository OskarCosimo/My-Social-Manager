<?php
// plugins/Twitch/TwitchPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';
require_once __DIR__ . '/../../app/Core/Database.php';

class TwitchPlugin implements SocialPluginInterface {

    public function getId(): string {
        return 'twitch';
    }

    public function getName(): string {
        return 'Twitch Channel';
    }

    /**
     * Get official brand color hex code for Twitch
     */
    public function getColor(): string {
        return '#9146FF';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'Twitch Client ID',
                'placeholder' => 'Enter Client ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'Twitch Client Secret',
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
            For Twitch Channel. If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://dev.twitch.tv/console" target="_blank">Twitch Developer Console</a> and register an Application.<br>
            2. Set the <strong>OAuth Redirect URL</strong> to:<br>
                <code>' . $callbackUrl . '</code><br>
            3. Select category <code>Application Integration</code> or <code>Chat Bot</code>.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom Client ID</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom Client Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        // STEP 1: Intercept BYOK form submission and redirect to Twitch OAuth
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
                throw new Exception("Twitch Client ID is missing. Admin configuration required or custom keys must be provided.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('user:write:chat channel:manage:broadcast user:read:chat');
            $state = urlencode($this->getId());

            $oauthUrl = "https://id.twitch.tv/oauth2/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scopes}&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: Handle OAuth return code
        if (isset($requestData['error'])) {
            throw new Exception("Twitch Error: " . ($requestData['error_description'] ?? $requestData['error']));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientId = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            $settings = PluginSetting::getForPlugin($this->getId());
            $clientId = $settings['client_id'] ?? '';
            $clientSecret = $settings['client_secret'] ?? '';
        }

        $redirectUri = $this->getCallbackUrl();

        // Exchange code for Access Token & Refresh Token
        $tokenUrl = "https://id.twitch.tv/oauth2/token";
        $postFields = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri
        ];

        $response = $this->curlPost($tokenUrl, $postFields);
        if (isset($response['status']) && $response['status'] >= 400) {
            throw new Exception($response['message'] ?? 'Failed to exchange authorization code.');
        }

        $accessToken = $response['access_token'] ?? '';
        $refreshToken = $response['refresh_token'] ?? '';
        $expiresIn = $response['expires_in'] ?? null;

        // Get User Information
        $userResponse = $this->curlGetWithHeaders("https://api.twitch.tv/helix/users", [
            'Authorization: Bearer ' . $accessToken,
            'Client-Id: ' . $clientId
        ]);

        $userData = $userResponse['data'][0] ?? null;
        if (!$userData) {
            throw new Exception("Failed to fetch Twitch user profile.");
        }

        $broadcasterId = $userData['id'];
        $broadcasterName = $userData['display_name'] ?? $userData['login'];

        $finalToken = json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'broadcaster_id' => $broadcasterId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'is_custom' => $byok['is_custom'] ?? false
        ]);

        unset($_SESSION['byok_' . $this->getId()]);

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $broadcasterId,
            'platform_name' => $broadcasterName,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Helper to retrieve a valid Twitch token, automatically refreshing it if expired
     */
    public function getValidAccessToken(string $storedAccessToken): array {
        $decoded = json_decode($storedAccessToken, true);
        if (!is_array($decoded)) {
            return [
                'access_token' => $storedAccessToken,
                'broadcaster_id' => null,
                'client_id' => null
            ];
        }

        $accessToken = $decoded['access_token'] ?? '';
        $refreshToken = $decoded['refresh_token'] ?? '';
        $broadcasterId = $decoded['broadcaster_id'] ?? '';
        $clientId = $decoded['client_id'] ?? '';
        $clientSecret = $decoded['client_secret'] ?? '';

        // Validate token against Twitch official validate endpoint
        $validateUrl = "https://id.twitch.tv/oauth2/validate";
        $ch = curl_init($validateUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . trim($accessToken)]);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $valData = json_decode($responseRaw, true) ?? [];

        // Token is still valid
        if ($httpCode === 200 && !empty($valData['user_id'])) {
            $expiresAt = null;
            if (!empty($valData['expires_in'])) {
                $expiresAt = date('Y-m-d H:i:s', time() + (int)$valData['expires_in']);
            }

            return [
                'access_token' => $accessToken,
                'broadcaster_id' => $broadcasterId,
                'client_id' => $clientId,
                'login' => $valData['login'] ?? 'Twitch User',
                'expires_at' => $expiresAt
            ];
        }

        // Token expired or invalid: attempt refresh if refresh_token is available
        if (!empty($refreshToken)) {
            if (empty($clientId) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientId = $settings['client_id'] ?? '';
                $clientSecret = $settings['client_secret'] ?? '';
            }

            if (!empty($clientId) && !empty($clientSecret)) {
                $postFields = [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret
                ];

                $refreshRes = $this->curlPost("https://id.twitch.tv/oauth2/token", $postFields);

                if (!empty($refreshRes['access_token'])) {
                    $newAccessToken = $refreshRes['access_token'];
                    $newRefreshToken = $refreshRes['refresh_token'] ?? $refreshToken;
                    $expiresIn = (int)($refreshRes['expires_in'] ?? 14400); // 4 hours standard
                    $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

                    $decoded['access_token'] = $newAccessToken;
                    $decoded['refresh_token'] = $newRefreshToken;
                    $updatedJson = json_encode($decoded);

                    // Update token in database for this account
                    try {
                        $db = Database::getInstance();
                        $upStmt = $db->prepare("
                            UPDATE social_accounts 
                            SET access_token = ?, refresh_token = ?, token_expires_at = ? 
                            WHERE access_token = ?
                        ");
                        $upStmt->execute([$updatedJson, $newRefreshToken, $newExpiresAt, $storedAccessToken]);
                    } catch (Exception $e) {
                        error_log("Twitch auto-refresh DB update failed: " . $e->getMessage());
                    }

                    return [
                        'access_token' => $newAccessToken,
                        'broadcaster_id' => $broadcasterId,
                        'client_id' => $clientId,
                        'login' => $decoded['broadcaster_name'] ?? 'Twitch User',
                        'expires_at' => $newExpiresAt
                    ];
                }
            }
        }

        return [
            'access_token' => $accessToken,
            'broadcaster_id' => $broadcasterId,
            'client_id' => $clientId,
            'error' => $valData['message'] ?? 'Twitch token expired or revoked.'
        ];
    }

    /**
     * Live verification of Twitch access token with auto-refresh support
     */
    public function checkTokenStatus(string $accessToken): array {
        $tokenInfo = $this->getValidAccessToken($accessToken);

        if (!empty($tokenInfo['error'])) {
            return [
                'valid' => false,
                'message' => $tokenInfo['error'] . " Please reconnect."
            ];
        }

        $login = $tokenInfo['login'] ?? 'Channel';
        $status = [
            'valid' => true,
            'message' => "Twitch active (@{$login})"
        ];

        if (isset($tokenInfo['expires_at'])) {
            $status['expires_at'] = $tokenInfo['expires_at'];
        }

        return $status;
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken): array {
        return $this->publishPostWithLink($content, $mediaUrls, $accessToken, null);
    }

    public function publishPostWithLink(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $tokenInfo = $this->getValidAccessToken($accessToken);
        
        if (!empty($tokenInfo['error'])) {
            return ['success' => false, 'error' => 'Twitch authorization expired. Please reconnect your account.'];
        }

        $token = $tokenInfo['access_token'];
        $broadcasterId = $tokenInfo['broadcaster_id'];
        $clientId = $tokenInfo['client_id'];

        // Standardize text content
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanMessage = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if (!empty($sourceLink)) {
            $cleanMessage .= " " . $sourceLink;
        }

        // Send Chat Announcement via Twitch Helix API
        $url = "https://api.twitch.tv/helix/chat/messages";
        $headers = [
            'Authorization: Bearer ' . $token,
            'Client-Id: ' . $clientId,
            'Content-Type: application/json'
        ];

        $payload = json_encode([
            'broadcaster_id' => $broadcasterId,
            'sender_id' => $broadcasterId,
            'message' => mb_strimwidth($cleanMessage, 0, 500, "...")
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode >= 400 || (isset($response['data'][0]['is_sent']) && !$response['data'][0]['is_sent'])) {
            return ['success' => false, 'error' => $response['message'] ?? 'Failed to post message to Twitch chat.'];
        }

        return ['success' => true, 'post_id' => $response['data'][0]['message_id'] ?? 'twitch_' . time()];
    }

    private function curlPost(string $url, array $fields): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true) ?? [];
    }

    private function curlGetWithHeaders(string $url, array $headers): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
            <h6 class="fw-bold text-primary mb-1">Step 1: Open Twitch Developer Console</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://dev.twitch.tv/console" target="_blank" class="fw-bold">Twitch Developer Console</a> and log in with your Twitch account.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Register Application & Set Redirect URI</h6>
            <p class="text-muted small mb-1">
                Click <strong>Register Your Application</strong>. Select category <em>Application Integration</em> or <em>Chat Bot</em>, and add this <strong>OAuth Redirect URL</strong>:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminTwitchRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminTwitchRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Generate Secret & Copy Keys</h6>
            <p class="text-muted small mb-0">
                Copy the <strong>Client ID</strong>, click <strong>New Secret</strong> to generate your <strong>Client Secret</strong>, and paste both into the form below.
            </p>
        </div>
        ';
    }
}