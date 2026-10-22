<?php
// plugins/LinkedinPages/LinkedinPages.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class LinkedinPagesPlugin implements SocialPluginInterface {
    
    public function getId(): string {
        return 'linkedin_pages';
    }

    public function getName(): string {
        return 'LinkedIn (Pages)';
    }

    /**
     * Get official brand color hex code for LinkedIn Pages
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
            For LinkedIn Pages.<br><br>
            1. Go to <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developer Portal</a>.<br>
            2. Set <strong>Authorized redirect URLs</strong> to: <code>' . $callbackUrl . '</code><br>
            3. Required scopes: <code>openid</code>, <code>profile</code>, <code>w_organization_social</code>, <code>r_organization_social</code>, <code>rw_organization_admin</code>.
        </div>
        <input type="hidden" name="byok_submit" value="1">
        <div class="mb-3"><label class="form-label fw-bold">Custom Client ID</label><input type="text" name="custom_client_id" class="form-control"></div>
        <div class="mb-4"><label class="form-label fw-bold">Custom Client Secret</label><input type="password" name="custom_client_secret" class="form-control"></div>';
    }

    public function handleCallback(array $requestData): array {
        if (isset($_POST['selected_org_urn']) && isset($_SESSION['temp_li_pages'])) {
            $selectedUrn = $_POST['selected_org_urn'];
            $pages = $_SESSION['temp_li_pages'];
            $byok = $_SESSION['temp_li_byok'] ?? [];
            $accessToken = $_SESSION['temp_li_token'] ?? '';
            $expiresIn = $_SESSION['temp_li_expires_in'] ?? null;
            $refreshToken = $_SESSION['temp_li_refresh_token'] ?? null;
            
            $pageName = 'LinkedIn Page';
            foreach ($pages as $p) {
                if ($p['urn'] === $selectedUrn) {
                    $pageName = $p['name'];
                    break;
                }
            }
            
            $finalToken = json_encode([
                'token' => $accessToken,
                'client_id' => $byok['client_id'] ?? '',
                'client_secret' => $byok['client_secret'] ?? '',
                'org_urn' => $selectedUrn
            ]);
            
            unset(
                $_SESSION['temp_li_pages'], 
                $_SESSION['temp_li_byok'], 
                $_SESSION['temp_li_token'], 
                $_SESSION['temp_li_expires_in'], 
                $_SESSION['temp_li_refresh_token'], 
                $_SESSION['byok_' . $this->getId()]
            );
            
            if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
                throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
            }

            return [
                'access_token' => $finalToken,
                'platform_user_id' => $selectedUrn,
                'platform_name' => $pageName,
                'expires_in' => $expiresIn,
                'refresh_token' => $refreshToken
            ];
        }

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
                throw new Exception("Client ID is missing.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('openid profile w_organization_social r_organization_social rw_organization_admin');
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

        $orgsUrl = "https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED";
        $ch2 = curl_init($orgsUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        $orgsRes = json_decode(curl_exec($ch2), true);
        curl_close($ch2);

        if (empty($orgsRes['elements'])) {
            throw new Exception("No LinkedIn Pages found where you are an Administrator.");
        }

        $validPages = [];
        foreach ($orgsRes['elements'] as $element) {
            $urn = $element['organization'];
            $orgId = str_replace('urn:li:organization:', '', $urn);
            
            $nameUrl = "https://api.linkedin.com/v2/organizations/{$orgId}";
            $ch3 = curl_init($nameUrl);
            curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch3, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
            $nameRes = json_decode(curl_exec($ch3), true);
            curl_close($ch3);

            $pageName = $nameRes['localizedName'] ?? 'Unknown Page (' . $orgId . ')';
            
            $validPages[] = ['urn' => $urn, 'name' => $pageName];
        }

        if (count($validPages) > 1) {
            $_SESSION['temp_li_pages'] = $validPages;
            $_SESSION['temp_li_byok'] = $byok;
            $_SESSION['temp_li_token'] = $accessToken;
            $_SESSION['temp_li_expires_in'] = $expiresIn;
            $_SESSION['temp_li_refresh_token'] = $refreshToken;
            
            $html = '<div style="font-family: system-ui; max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">';
            $html .= '<h3 style="color: #0077b5;">Select LinkedIn Page</h3><form method="POST">';
            foreach ($validPages as $p) {
                $html .= '<div style="margin-bottom: 10px;"><label><input type="radio" name="selected_org_urn" value="'.$p['urn'].'" required> ' . htmlspecialchars($p['name']) . '</label></div>';
            }
            $html .= '<button type="submit" style="width:100%; padding:10px; background:#0077b5; color:#fff; border:none;">Connect Page</button></form></div>';
            echo $html;
            exit;
        }

        $page = $validPages[0];
        $finalToken = json_encode([
            'token' => $accessToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'org_urn' => $page['urn']
        ]);

        unset($_SESSION['byok_' . $this->getId()]);

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $page['urn'],
            'platform_name' => $page['name'],
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Live verification of LinkedIn Page Token and Administrator permissions
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $orgUrn = (is_array($decoded) && isset($decoded['org_urn'])) ? $decoded['org_urn'] : null;

        if (!$orgUrn) {
            return ['valid' => false, 'message' => 'Missing Organization identifier.'];
        }

        $orgId = str_replace('urn:li:organization:', '', $orgUrn);
        $nameUrl = "https://api.linkedin.com/v2/organizations/{$orgId}";

        $ch = curl_init($nameUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($realToken)
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $nameRes = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && !empty($nameRes['id'])) {
            $pageName = $nameRes['localizedName'] ?? 'LinkedIn Page';
            return [
                'valid' => true,
                'message' => "Page token active ({$pageName})"
            ];
        }

        $errorMsg = $nameRes['message'] ?? 'LinkedIn session expired or admin permissions lost.';
        if ($httpCode === 401) {
            $errorMsg = 'LinkedIn Access Token expired or revoked. Please reconnect the Page.';
        }

        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        $realToken = (is_array($decoded) && isset($decoded['token'])) ? $decoded['token'] : $accessToken;
        $orgUrn = (is_array($decoded) && isset($decoded['org_urn'])) ? $decoded['org_urn'] : null;

        if (!$orgUrn) {
            return ['success' => false, 'error' => 'Could not determine Organization URN. Please reconnect the account.'];
        }

        $plainContent = trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $postUrl = "https://api.linkedin.com/rest/posts";
        
        $postBody = [
            'author' => $orgUrn,
            'commentary' => $plainContent,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => []
            ],
            'lifecycleState' => 'PUBLISHED'
        ];

        // 1. If native images exist, attach image preview correctly
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

        $ch = curl_init($postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($realToken),
            'Content-Type: application/json',
            'LinkedIn-Version: 202601',
            'X-Restli-Protocol-Version: 2.0.0'
        ]);
        
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $postRes = json_decode($responseRaw, true) ?? [];

        if ($httpCode >= 400) {
            $errorMsg = $postRes['message'] ?? 'Failed to publish to LinkedIn';
            if ($httpCode === 401) {
                $errorMsg = 'LinkedIn Access Token expired or revoked. Please reconnect.';
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
            <h6 class="fw-bold text-primary mb-1">Step 1: Create a LinkedIn App for Organizations</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://www.linkedin.com/developers/apps" target="_blank" class="fw-bold">LinkedIn Developer Portal</a> and create or select your App.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Request Community Management API</h6>
            <p class="text-muted small mb-0">
                In the <strong>Products</strong> tab, request access to <strong>Community Management API</strong> (or <strong>Share on LinkedIn</strong> with Organization permissions).<br>
                Required scopes: <code>openid</code>, <code>profile</code>, <code>w_organization_social</code>, <code>r_organization_social</code>, <code>rw_organization_admin</code>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Configure Redirect URL</h6>
            <p class="text-muted small mb-1">
                In the <strong>Auth</strong> tab, add the following to <strong>Authorized redirect URLs for your app</strong>:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminLiPagesRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminLiPagesRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 4: Save Credentials</h6>
            <p class="text-muted small mb-0">
                Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> from the <strong>Auth</strong> tab and paste them below.
            </p>
        </div>
        ';
    }
}