<?php
// plugins/Linkedin/LinkedinPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class LinkedinPlugin implements SocialPluginInterface {
    
    public function getId(): string {
        return 'linkedin';
    }

    public function getName(): string {
        return 'LinkedIn (Profile)';
    }

    /**
     * Get official brand color hex code for LinkedIn
     */
    public function getColor(): string {
        return '#0A66C2';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'LinkedIn Client ID',
                'placeholder' => 'Enter your Client ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'LinkedIn Client Secret',
                'placeholder' => 'Enter your Client Secret'
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
            For LinkedIn Profiles. If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developer Portal</a> and create a new App.<br>
            2. Request the "Share on LinkedIn" and "Sign In with LinkedIn using OpenID Connect" products.<br>
            3. In the Auth tab, set the <strong>Authorized redirect URLs</strong> to:<br>
               <code>' . $callbackUrl . '</code><br>
            4. Required scopes: <code>openid</code>, <code>profile</code>, <code>w_member_social</code>.
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
                throw new Exception("Client ID is missing. Admin configuration required or custom keys must be provided.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('openid profile w_member_social');
            $state = urlencode($this->getId());
            
            $oauthUrl = "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id={$clientId}&redirect_uri={$redirectUri}&scope={$scopes}&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        if (isset($requestData['error'])) {
            throw new Exception("LinkedIn Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientId = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        $tokenUrl = "https://www.linkedin.com/oauth/v2/accessToken";
        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenRes = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($tokenRes['error'])) {
            throw new Exception($tokenRes['error_description'] ?? $tokenRes['error']);
        }

        $accessToken = $tokenRes['access_token'];
        $expiresIn = $tokenRes['expires_in'] ?? null;
        $refreshToken = $tokenRes['refresh_token'] ?? null;

        $meUrl = "https://api.linkedin.com/v2/userinfo";
        $ch2 = curl_init($meUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        $meRes = json_decode(curl_exec($ch2), true);
        curl_close($ch2);

        if (isset($meRes['message'])) {
            throw new Exception("LinkedIn API Error: " . $meRes['message']);
        }

        $userId = $meRes['sub'] ?? '';
        $userName = $meRes['name'] ?? 'LinkedIn User';

        if (empty($userId)) {
            throw new Exception("Could not retrieve LinkedIn user ID.");
        }

        $finalTokenData = [
            'token' => $accessToken,
            'user_id' => $userId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];

        $finalToken = json_encode($finalTokenData);

        unset($_SESSION['byok_' . $this->getId()]);

        require_once __DIR__ . '/../../app/Models/UsageManager.php';
        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $userId,
            'platform_name' => $userName,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Live verification of LinkedIn User Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;

        $meUrl = "https://api.linkedin.com/v2/userinfo";
        $ch = curl_init($meUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($realToken)
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['sub'])) {
            $name = $res['name'] ?? 'LinkedIn User';
            return [
                'valid' => true,
                'message' => "Token active ({$name})"
            ];
        }

        $errorMsg = $res['message'] ?? 'LinkedIn session expired or revoked.';
        if ($httpCode === 401) {
            $errorMsg = 'LinkedIn Access Token expired or revoked. Please reconnect.';
        }

        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $userId = (is_array($decoded) && isset($decoded['user_id'])) ? $decoded['user_id'] : null;

        // Retrieve the OpenID SUB (User ID) if missing from JSON structure
        if (!$userId) {
            $meUrl = "https://api.linkedin.com/v2/userinfo";
            $ch = curl_init($meUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . trim($realToken)]);
            $meRes = json_decode(curl_exec($ch), true);
            curl_close($ch);

            $userId = $meRes['sub'] ?? '';
            if (empty($userId)) {
                return ['success' => false, 'error' => 'Could not retrieve LinkedIn user ID for publishing. Please reconnect your account.'];
            }
        }

        $authorUrn = 'urn:li:person:' . $userId;

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $plainContent = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Call the updated LinkedIn REST Posts API
        $postUrl = "https://api.linkedin.com/rest/posts";
        
        $postBody = [
            'author' => $authorUrn,
            'commentary' => $plainContent,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => []
            ],
            'lifecycleState' => 'PUBLISHED'
        ];

        // 1. If images exist, attach image preview correctly
        if (!empty($mediaUrls[0])) {
            $postBody['content'] = [
                'media' => [
                    'title' => mb_substr($plainContent, 0, 100) ?: 'Image',
                    'id'    => $mediaUrls[0]
                ]
            ];
        } 
        // 2. Otherwise attach external link/article if sourceLink exists
        elseif (!empty($sourceLink)) {
            $postBody['content'] = [
                'article' => [
                    'source' => $sourceLink,
                    'title'  => mb_substr($plainContent, 0, 100) ?: 'Link'
                ]
            ];
        }

        $ch2 = curl_init($postUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($postBody));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($realToken),
            'Content-Type: application/json',
            'LinkedIn-Version: 202601',
            'X-Restli-Protocol-Version: 2.0.0'
        ]);
        
        $responseRaw = curl_exec($ch2);
        $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        $postRes = json_decode($responseRaw, true) ?? [];

        if ($httpCode >= 400) {
            $errorMsg = $postRes['message'] ?? 'Failed to publish to LinkedIn Profile';
            if ($httpCode === 401) {
                $errorMsg = 'LinkedIn Access Token expired or revoked. Please reconnect your profile.';
            }
            return ['success' => false, 'error' => "LinkedIn API Error ({$httpCode}): " . $errorMsg];
        }

        return ['success' => true, 'post_id' => $postRes['id'] ?? 'published'];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Create a LinkedIn App</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://www.linkedin.com/developers/apps" target="_blank" class="fw-bold">LinkedIn Developer Portal</a> and click <strong>Create App</strong>. Enter your app name and associate your LinkedIn Company Page.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Request Required Products</h6>
            <p class="text-muted small mb-0">
                In the <strong>Products</strong> tab, request access to:
                <ul class="mb-0 mt-1">
                    <li><strong>Share on LinkedIn</strong> (enables <code>w_member_social</code>)</li>
                    <li><strong>Sign In with LinkedIn using OpenID Connect</strong> (enables <code>openid</code>, <code>profile</code>)</li>
                </ul>
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Configure Authorized Redirect URL</h6>
            <p class="text-muted small mb-1">
                In the <strong>Auth</strong> tab, scroll down to <strong>Authorized redirect URLs for your app</strong> and add:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminLiRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminLiRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 4: Copy Client Credentials</h6>
            <p class="text-muted small mb-0">
                From the <strong>Auth</strong> tab, copy <strong>Client ID</strong> and <strong>Primary Client Secret</strong> and paste them into the fields below.
            </p>
        </div>
        ';
    }
}