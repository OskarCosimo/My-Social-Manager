<?php
// plugins/GoogleBusiness/GoogleBusinessPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class GoogleBusinessPlugin implements SocialPluginInterface {

    private const API_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const API_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_ACCOUNTS_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';
    private const API_LOCATIONS_BASE = 'https://mybusinessbusinessinformation.googleapis.com/v1';

    public function getId(): string {
        return 'google_business';
    }

    public function getName(): string {
        return 'Google Business Profile';
    }

    /**
     * Get official brand color hex code for Google Business Profile
     */
    public function getColor(): string {
        return '#4285F4';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'Google Client ID',
                'placeholder' => 'Enter Google Client ID'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'Google Client Secret',
                'placeholder' => 'Enter Google Client Secret'
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
     * Render the setup form with an embedded Bootstrap 5 step-by-step modal guide
     */
    public function getManualSetupHtml(): string {
        $callbackUrl = $this->getCallbackUrl();

        return '
        <div class="alert alert-primary d-flex align-items-center justify-content-between p-3 mb-4 shadow-sm">
            <div>
                <strong>Need help obtaining your Google API keys?</strong><br>
                <small class="text-muted">Setting up Google Cloud Console requires enabling specific Business APIs and permissions.</small>
            </div>
            <button type="button" class="btn btn-primary btn-sm ms-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#gbpSetupGuideModal">
                📖 Step-by-Step Guide
            </button>
        </div>

        <!-- Bootstrap 5 Modal Guide -->
        <div class="modal fade" id="gbpSetupGuideModal" tabindex="-1" aria-labelledby="gbpSetupGuideModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold" id="gbpSetupGuideModalLabel">
                            🏢 How to get Google Business Profile API Credentials
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 fs-6">
                        
                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-1">Step 1: Create a Project in Google Cloud</h6>
                            <p class="text-muted small mb-0">
                                Go to the <a href="https://console.cloud.google.com/" target="_blank" class="fw-bold">Google Cloud Console</a>, sign in with the Google Account that manages your Google Business Profile, and create a new project (e.g. <code>My Social Manager</code>).
                            </p>
                        </div>

                        <hr class="my-3 text-muted">

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-1">Step 2: Enable Required Business APIs</h6>
                            <p class="text-muted small mb-1">
                                In the left navigation menu, go to <strong>APIs & Services &rarr; Library</strong> and enable both:
                            </p>
                            <ul class="small mb-0">
                                <li><code>My Business Account Management API</code></li>
                                <li><code>My Business Business Information API</code></li>
                            </ul>
                        </div>

                        <hr class="my-3 text-muted">

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-1">Step 3: Configure the OAuth Consent Screen</h6>
                            <p class="text-muted small mb-1">
                                In the left menu, click <strong>OAuth consent screen</strong>:
                            </p>
                            <ul class="small mb-1">
                                <li>Choose <strong>External</strong> and fill in your App Name and Email.</li>
                                <li>Under <strong>Scopes</strong>, add: <code>https://www.googleapis.com/auth/business.manage</code></li>
                                <li><strong>Important (Test Users):</strong> While the app status is in "Testing", scroll to <strong>Test Users</strong> and add your personal Gmail address that owns or manages the Business Profile.</li>
                            </ul>
                        </div>

                        <hr class="my-3 text-muted">

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-1">Step 4: Create OAuth 2.0 Credentials</h6>
                            <p class="text-muted small mb-1">
                                In the left menu, go to <strong>Credentials &rarr; Create Credentials &rarr; OAuth client ID</strong>:
                            </p>
                            <ul class="small mb-2">
                                <li>Select <strong>Application type: Web application</strong>.</li>
                                <li>Set Authorized redirect URI to:</li>
                            </ul>
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" class="form-control font-monospace" id="gbpRedirectUriInput" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'gbpRedirectUriInput\').value); this.innerText=\'Copied!\';">Copy URI</button>
                            </div>
                        </div>

                        <hr class="my-3 text-muted">

                        <div>
                            <h6 class="fw-bold text-primary mb-1">Step 5: Copy Keys</h6>
                            <p class="text-muted small mb-0">
                                Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> and paste them into the fields below.
                            </p>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Got it, thanks!</button>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="byok_submit" value="1">

        <div class="mb-3">
            <label class="form-label fw-bold">Google Client ID</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="xxxx.apps.googleusercontent.com">
            <div class="form-text">Leave blank to use the system default credentials (if configured by admin).</div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Google Client Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Enter Client Secret">
            <div class="form-text">Leave blank to use the system default credentials (if configured by admin).</div>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        // STEP 0: Check selection from multiple locations form
        if (isset($_POST['selected_location_name']) && isset($_SESSION['temp_gbp_locations'])) {
            $selectedLocation = $_POST['selected_location_name'];
            $locations = $_SESSION['temp_gbp_locations'];
            $byok = $_SESSION['temp_gbp_byok'] ?? [];
            $tokenData = $_SESSION['temp_gbp_token'] ?? [];

            $locationTitle = 'Google Business Location';
            $accountName = '';
            foreach ($locations as $loc) {
                if ($loc['name'] === $selectedLocation) {
                    $locationTitle = $loc['title'];
                    $accountName = $loc['account_name'];
                    break;
                }
            }

            $finalToken = json_encode([
                'access_token'  => $tokenData['access_token'] ?? '',
                'refresh_token' => $tokenData['refresh_token'] ?? '',
                'location_name' => $selectedLocation,
                'account_name'  => $accountName,
                'client_id'     => $byok['client_id'] ?? '',
                'client_secret' => $byok['client_secret'] ?? '',
                'is_custom'     => $byok['is_custom'] ?? false
            ]);

            $expiresIn = $tokenData['expires_in'] ?? 3600;
            $refreshToken = $tokenData['refresh_token'] ?? null;

            unset($_SESSION['temp_gbp_locations'], $_SESSION['temp_gbp_byok'], $_SESSION['temp_gbp_token'], $_SESSION['byok_' . $this->getId()]);

            if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
                throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
            }

            return [
                'access_token'     => $finalToken,
                'platform_user_id' => $selectedLocation,
                'platform_name'    => $locationTitle . ' (Google)',
                'expires_in'       => $expiresIn,
                'refresh_token'    => $refreshToken
            ];
        }

        // STEP 1: BYOK submission and OAuth redirect
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
                throw new Exception("Google Client ID is missing.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('https://www.googleapis.com/auth/business.manage');
            $state = urlencode($this->getId());

            $oauthUrl = self::API_AUTH_URL . "?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scopes}&state={$state}&access_type=offline&prompt=consent";
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: Handle OAuth return code
        if (isset($requestData['error'])) {
            throw new Exception("Google Business Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientId = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';
        $redirectUri = $this->getCallbackUrl();

        $postData = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri
        ];

        $ch = curl_init(self::API_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $resRaw = curl_exec($ch);
        curl_close($ch);

        $tokenRes = json_decode($resRaw, true) ?? [];
        if (isset($tokenRes['error']) || empty($tokenRes['access_token'])) {
            throw new Exception("Token Error: " . ($tokenRes['error_description'] ?? 'Failed to exchange token.'));
        }

        $accessToken = $tokenRes['access_token'];
        $refreshToken = $tokenRes['refresh_token'] ?? null;
        $expiresIn = $tokenRes['expires_in'] ?? 3600;

        // Fetch Google Business Accounts
        $accCh = curl_init(self::API_ACCOUNTS_URL);
        curl_setopt($accCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($accCh, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        $accRaw = curl_exec($accCh);
        curl_close($accCh);

        $accData = json_decode($accRaw, true) ?? [];
        $accounts = $accData['accounts'] ?? [];

        if (empty($accounts)) {
            throw new Exception("No Google Business Accounts found for this Google profile.");
        }

        // Fetch Locations across accounts
        $validLocations = [];
        foreach ($accounts as $account) {
            $accountName = $account['name']; // e.g. "accounts/12345"
            $locUrl = self::API_LOCATIONS_BASE . "/{$accountName}/locations?readMask=name,title";

            $locCh = curl_init($locUrl);
            curl_setopt($locCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($locCh, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
            $locRaw = curl_exec($locCh);
            curl_close($locCh);

            $locData = json_decode($locRaw, true) ?? [];
            if (!empty($locData['locations'])) {
                foreach ($locData['locations'] as $location) {
                    $validLocations[] = [
                        'name'         => $location['name'], // e.g. "locations/67890"
                        'title'        => $location['title'] ?? 'Store Location',
                        'account_name' => $accountName
                    ];
                }
            }
        }

        if (empty($validLocations)) {
            throw new Exception("No verified business locations found in your Google Business Profile.");
        }

        // STEP 3: Location Selection if more than one found
        if (count($validLocations) > 1) {
            $_SESSION['temp_gbp_locations'] = $validLocations;
            $_SESSION['temp_gbp_byok'] = $byok;
            $_SESSION['temp_gbp_token'] = $tokenRes;

            $html = '<div style="font-family: system-ui, sans-serif; max-width: 420px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">';
            $html .= '<h3 style="margin-top: 0; color: #4285F4;">Select Business Location</h3>';
            $html .= '<p style="color: #555; font-size: 14px;">Choose which business location you want to publish to:</p>';
            $html .= '<form method="POST" action="">';
            foreach ($validLocations as $loc) {
                $html .= '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 5px; cursor: pointer;">';
                $html .= '<label style="cursor: pointer; display: block; font-weight: 500;">';
                $html .= '<input type="radio" name="selected_location_name" value="'.$loc['name'].'" required style="margin-right: 10px;"> ' . htmlspecialchars($loc['title']);
                $html .= '</label></div>';
            }
            $html .= '<button type="submit" style="margin-top: 15px; width: 100%; padding: 12px; background: #4285F4; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Connect Location</button>';
            $html .= '</form></div>';
            echo $html;
            exit;
        }

        $location = $validLocations[0];
        $finalToken = json_encode([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'location_name' => $location['name'],
            'account_name'  => $location['account_name'],
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'is_custom'     => $byok['is_custom'] ?? false
        ]);

        unset($_SESSION['byok_' . $this->getId()]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token'     => $finalToken,
            'platform_user_id' => $location['name'],
            'platform_name'    => $location['title'] . ' (Google)',
            'expires_in'       => $expiresIn,
            'refresh_token'    => $refreshToken
        ];
    }

    /**
     * Live verification of Google Business Profile access token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $token = (is_array($decoded) && isset($decoded['access_token'])) ? $decoded['access_token'] : $accessToken;
        $locName = (is_array($decoded) && isset($decoded['location_name'])) ? $decoded['location_name'] : '';

        $ch = curl_init(self::API_ACCOUNTS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        $resRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['accounts'])) {
            return [
                'valid'   => true,
                'message' => 'Google Business active' . (!empty($locName) ? " ({$locName})" : '')
            ];
        }

        $errorMsg = $res['error']['message'] ?? "Google token expired or invalid (HTTP {$httpCode}). Please reconnect.";
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
            return ['success' => false, 'error' => 'Invalid Google Business token credentials.'];
        }

        $token = $decoded['access_token'] ?? '';
        $accountName = $decoded['account_name'] ?? '';
        $locationName = $decoded['location_name'] ?? '';

        if (empty($token) || empty($accountName) || empty($locationName)) {
            return ['success' => false, 'error' => 'Missing account or location information. Please reconnect.'];
        }

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanSummary = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Google Local Posts v4 endpoint
        $url = "https://mybusiness.googleapis.com/v4/{$accountName}/{$locationName}/localPosts";

        $postPayload = [
            'languageCode' => 'en-US',
            'summary'      => mb_substr($cleanSummary, 0, 1500),
            'topicType'    => 'STANDARD'
        ];

        // Attach Call to Action button if source link exists
        if (!empty($sourceLink)) {
            $postPayload['callToAction'] = [
                'actionType' => 'LEARN_MORE',
                'url'        => $sourceLink
            ];
        }

        // Attach photo media if present
        if (!empty($mediaUrls[0])) {
            $postPayload['media'] = [
                [
                    'mediaFormat' => 'PHOTO',
                    'sourceUrl'   => $mediaUrls[0]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        $resultRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($resultRaw, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300 && !empty($res['name'])) {
            return ['success' => true, 'post_id' => $res['name']];
        }

        return [
            'success' => false,
            'error'   => $res['error']['message'] ?? "Google API Error (HTTP {$httpCode})"
        ];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Create a Project in Google Cloud</h6>
            <p class="text-muted small mb-0">
                Go to the <a href="https://console.cloud.google.com/" target="_blank" class="fw-bold">Google Cloud Console</a> and create a new project (e.g. <code>My Social Manager Global</code>).
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Enable Required Business APIs</h6>
            <p class="text-muted small mb-1">
                In <strong>APIs & Services &rarr; Library</strong>, search and enable both:
            </p>
            <ul class="small mb-0">
                <li><code>My Business Account Management API</code></li>
                <li><code>My Business Business Information API</code></li>
            </ul>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 3: Configure the OAuth Consent Screen</h6>
            <p class="text-muted small mb-1">
                In <strong>APIs & Services &rarr; OAuth consent screen</strong>:
            </p>
            <ul class="small mb-1">
                <li>Choose <strong>External</strong> and fill in the required App name and Support email.</li>
                <li>Under <strong>Scopes</strong>, add: <code>https://www.googleapis.com/auth/business.manage</code></li>
                <li>Under <strong>Test Users</strong>, add your Google account emails while the app is in Testing mode.</li>
            </ul>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 4: Create OAuth 2.0 Client ID</h6>
            <p class="text-muted small mb-1">
                Go to <strong>Credentials &rarr; Create Credentials &rarr; OAuth client ID</strong>:
            </p>
            <ul class="small mb-2">
                <li>Select <strong>Application type: Web application</strong>.</li>
                <li>Set the <strong>Authorized redirect URI</strong> to:</li>
            </ul>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminGbpRedirectInput" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminGbpRedirectInput\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 5: Save System Config</h6>
            <p class="text-muted small mb-0">
                Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> into the plugin form on this page, toggle <strong>Active</strong>, and click <strong>Save Config</strong>.
            </p>
        </div>
        ';
    }
}