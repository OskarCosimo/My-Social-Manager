<?php
// plugins/Xcom/XcomPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';

class XcomPlugin implements SocialPluginInterface {
    
    private const API_AUTH_URL = 'https://twitter.com/i/oauth2/authorize';
    private const API_TOKEN_URL = 'https://api.twitter.com/2/oauth2/token';
    private const API_ME_URL = 'https://api.twitter.com/2/users/me';
    private const API_TWEET_URL = 'https://api.twitter.com/2/tweets';

    public function getId(): string {
        return 'xcom';
    }

    public function getName(): string {
        return 'X.com (Twitter)';
    }

    /**
     * Get official brand color hex code for X.com
     */
    public function getColor(): string {
        return '#000000';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'X.com OAuth 2.0 Client ID',
                'placeholder' => 'Enter Client ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'X.com OAuth 2.0 Client Secret',
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
        $callbackUrl = Config::APP_URL . '/plugin/callback';
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK Setup (Highly Recommended):</strong><br>
            X.com has strict rate limits. Using your own Custom App ensures your posts are always delivered.<br><br>
            1. Go to the <a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">X Developer Portal</a> and create a Project/App.<br>
            2. Set up User Authentication (OAuth 2.0 Web App). Set the <strong>Callback URI / Redirect URL</strong> to:<br>
               <code>' . $callbackUrl . '</code><br>
            3. In the App settings, request the following scopes (Permissions): <code>tweet.read</code>, <code>tweet.write</code>, <code>users.read</code>, <code>offline.access</code>.
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
                throw new Exception("X.com Client ID is missing.");
            }

            $codeVerifier = bin2hex(random_bytes(32));
            
            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom,
                'pkce' => $codeVerifier
            ];

            $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('tweet.read tweet.write users.read offline.access'); 
            $state = urlencode($this->getId());
            
            $oauthUrl = self::API_AUTH_URL . "?response_type=code&client_id={$clientId}&redirect_uri={$redirectUri}&scope={$scopes}&state={$state}&code_challenge={$codeChallenge}&code_challenge_method=S256";
            
            header("Location: " . $oauthUrl);
            exit;
        }

        if (isset($requestData['error'])) {
            throw new Exception("X.com Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientId = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';
        $codeVerifier = $byok['pkce'] ?? '';

        if (empty($codeVerifier)) {
            throw new Exception("Security Error: PKCE verifier missing from session.");
        }
        
        $postData = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $this->getCallbackUrl(),
            'code' => $code,
            'code_verifier' => $codeVerifier
        ];

        $basicAuth = base64_encode("{$clientId}:{$clientSecret}");

        $ch = curl_init(self::API_TOKEN_URL);
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
            throw new Exception("Token Error: " . ($response['error_description'] ?? $response['error'] ?? 'Failed to obtain token.'));
        }
        
        $accessToken = $response['access_token'];
        $refreshToken = $response['refresh_token'] ?? '';
        $expiresIn = $response['expires_in'] ?? null;

        $ch2 = curl_init(self::API_ME_URL);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ]);
        $profileRaw = curl_exec($ch2);
        curl_close($ch2);
        
        $profile = json_decode($profileRaw, true);

        $finalTokenData = [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];

        $finalToken = json_encode($finalTokenData);

        unset($_SESSION['byok_' . $this->getId()]);

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $profile['data']['id'] ?? 'unknown_id',
            'platform_name' => '@' . ($profile['data']['username'] ?? 'x_user'),
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Live verification of X.com Access Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        $ch = curl_init(self::API_ME_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . trim($realToken),
            "Content-Type: application/json"
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $profile = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && !empty($profile['data']['id'])) {
            $username = $profile['data']['username'] ?? 'User';
            return [
                'valid' => true,
                'message' => "X.com active (@{$username})"
            ];
        }

        $errorMsg = $profile['detail'] ?? "X.com session expired or unauthorized (HTTP {$httpCode}). Please reconnect.";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $plainText = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $fullText = $plainText;
        if (!empty($sourceLink)) {
            if (strpos($fullText, $sourceLink) === false) {
                $fullText .= "\n\n" . $sourceLink;
            }
        }

        // Truncate to standard 280-character limit
        $maxChars = 280;
        if (mb_strlen($fullText) > $maxChars) {
            $fullText = mb_substr($fullText, 0, $maxChars - 3) . '...';
        }

        $data = ['text' => $fullText];
        
        $ch = curl_init(self::API_TWEET_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . trim($realToken),
            "Content-Type: application/json"
        ]);
        
        $resultRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response = json_decode($resultRaw, true) ?? [];

        if ($httpCode >= 400 || isset($response['errors'])) {
            $errorMsg = 'X API Error (' . $httpCode . ')';
            
            if ($httpCode === 401) {
                $errorMsg = 'X.com Access Token expired or unauthorized. Please reconnect your account.';
            } elseif ($httpCode === 429) {
                $errorMsg = 'X.com API rate limit / credits exceeded (429 Too Many Requests).';
            } elseif (!empty($response['detail'])) {
                $errorMsg .= ': ' . $response['detail'];
            } elseif (!empty($response['errors'][0]['message'])) {
                $errorMsg .= ': ' . $response['errors'][0]['message'];
            } elseif (!empty($response['title'])) {
                $errorMsg .= ': ' . $response['title'];
            }

            return ['success' => false, 'error' => $errorMsg];
        }

        return ['success' => true, 'post_id' => $response['data']['id'] ?? ''];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Open X Developer Portal</h6>
            <p class="text-muted small mb-0">
                Go to the <a href="https://developer.x.com/en/portal/dashboard" target="_blank" class="fw-bold">X (Twitter) Developer Portal</a> and create a Project and an App.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Configure User Authentication Settings (OAuth 2.0)</h6>
            <p class="text-muted small mb-1">
                Under your App Settings, click <strong>Set up</strong> under <strong>User authentication settings</strong>:
            </p>
            <ul class="small mb-1">
                <li>App permissions: <strong>Read and write</strong>.</li>
                <li>Type of App: <strong>Web App, Automated App or Bot</strong>.</li>
                <li>App info &rarr; Callback URI / Redirect URL:</li>
            </ul>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminXRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminXRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
            <p class="text-muted small mb-0">Website URL: <code>' . htmlspecialchars(Config::APP_URL) . '</code></p>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Copy OAuth 2.0 Credentials</h6>
            <p class="text-muted small mb-0">
                Go to the <strong>Keys and tokens</strong> tab. Under <strong>OAuth 2.0 Client ID and Client Secret</strong>, copy the keys and paste them below.<br>
                <em>Note: Do not use the OAuth 1.0a Consumer Keys. X.com uses OAuth 2.0 PKCE.</em>
            </p>
        </div>
        ';
    }
}