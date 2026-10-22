<?php
// plugins/WhatsApp/WhatsAppPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class WhatsAppPlugin implements SocialPluginInterface {

    private const API_VERSION = 'v19.0';

    public function getId(): string {
        return 'whatsapp';
    }

    public function getName(): string {
        return 'WhatsApp Business';
    }

    /**
     * Get official brand color hex code for WhatsApp
     */
    public function getColor(): string {
        return '#25D366';
    }

    public function getSettingsFields(): array {
        return [
            'system_access_token' => [
                'type' => 'password',
                'label' => 'System Access Token (Meta App)',
                'placeholder' => 'Enter Meta Permanent Access Token'
            ]
        ];
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    public function getManualSetupHtml(): string {
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK (Bring Your Own Key) Setup for WhatsApp Business Cloud API:</strong><br>
            1. Go to <a href="https://developers.facebook.com/" target="_blank">Meta for Developers</a> and open your Business App.<br>
            2. Add <strong>WhatsApp Cloud API</strong> product.<br>
            3. Copy your <strong>Phone Number ID</strong> and <strong>Permanent System Access Token</strong>.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Phone Number ID</label>
            <input type="text" name="phone_number_id" class="form-control" placeholder="e.g. 104928374829102" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Recipient Phone Number (Default Channel/Group/User)</label>
            <input type="text" name="recipient_number" class="form-control" placeholder="e.g. 393400000000 (with country code)" required>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Permanent Access Token (BYOK)</label>
            <input type="password" name="custom_access_token" class="form-control" placeholder="Leave blank to use System App token">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (!isset($requestData['byok_submit'])) {
            throw new Exception("Invalid submission.");
        }

        $phoneNumberId = trim($requestData['phone_number_id'] ?? '');
        $recipientNumber = trim($requestData['recipient_number'] ?? '');
        $accessToken = trim($requestData['custom_access_token'] ?? '');
        $isCustom = true;

        if (empty($accessToken)) {
            $settings = PluginSetting::getForPlugin($this->getId());
            $accessToken = $settings['system_access_token'] ?? '';
            $isCustom = false;
        }

        if (empty($phoneNumberId) || empty($accessToken) || empty($recipientNumber)) {
            throw new Exception("Phone Number ID, Recipient Number, and Access Token are required.");
        }

        // Test credentials by requesting Phone Number Profile from Graph API
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$phoneNumberId}?access_token=" . urlencode($accessToken);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($result, true) ?? [];
        if (isset($res['error'])) {
            throw new Exception("WhatsApp API Error: " . ($res['error']['message'] ?? 'Verification failed'));
        }

        $displayName = $res['display_phone_number'] ?? ($res['verified_name'] ?? ('WA_' . $phoneNumberId));

        $finalToken = json_encode([
            'access_token' => $accessToken,
            'phone_number_id' => $phoneNumberId,
            'recipient_number' => preg_replace('/[^0-9]/', '', $recipientNumber),
            'is_custom' => $isCustom
        ]);

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $phoneNumberId,
            'platform_name' => $displayName . " (" . $recipientNumber . ")"
        ];
    }

    /**
     * Live verification of WhatsApp Phone Number ID and Access Token
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        if (!is_array($decoded)) {
            return ['valid' => false, 'message' => 'Malformed WhatsApp token data.'];
        }

        $token = $decoded['access_token'] ?? '';
        $phoneNumberId = $decoded['phone_number_id'] ?? '';

        if (empty($token) || empty($phoneNumberId)) {
            return ['valid' => false, 'message' => 'Missing token or Phone Number ID.'];
        }

        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$phoneNumberId}?access_token=" . urlencode($token);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($result, true) ?? [];

        if ($httpCode === 200 && !empty($res['id'])) {
            $displayName = $res['display_phone_number'] ?? ($res['verified_name'] ?? $phoneNumberId);
            return [
                'valid' => true,
                'message' => "WhatsApp active ({$displayName})"
            ];
        }

        $errorMsg = $res['error']['message'] ?? "WhatsApp API token expired or invalid (HTTP {$httpCode})";
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
            return ['success' => false, 'error' => 'Invalid WhatsApp credentials.'];
        }

        $token = $decoded['access_token'] ?? '';
        $phoneNumberId = $decoded['phone_number_id'] ?? '';
        $recipientNumber = $decoded['recipient_number'] ?? '';

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanMessage = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if (!empty($sourceLink)) {
            $cleanMessage .= "\n\n" . $sourceLink;
        }

        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$phoneNumberId}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientNumber
        ];

        // Attach image or send plain text
        if (!empty($mediaUrls[0])) {
            $payload['type'] = 'image';
            $payload['image'] = [
                'link' => $mediaUrls[0],
                'caption' => $cleanMessage
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = [
                'preview_url' => !empty($sourceLink),
                'body' => $cleanMessage
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if (isset($response['error'])) {
            return ['success' => false, 'error' => $response['error']['message']];
        }

        return ['success' => true, 'post_id' => $response['messages'][0]['id'] ?? 'wa_' . time()];
    }

    /**
     * Instructions displayed to the administrator in the Admin Plugins Config tab
     */
    public function getAdminInstructionsHtml(): string {
        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Set up WhatsApp Cloud API</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://developers.facebook.com/" target="_blank" class="fw-bold">Meta for Developers</a>, open your Business App, and add the <strong>WhatsApp</strong> product.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Create a Permanent System User Token</h6>
            <p class="text-muted small mb-1">
                Do not use 24-hour temporary tokens! To generate a permanent token:
            </p>
            <ol class="small mb-0">
                <li>Go to <a href="https://business.facebook.com/settings" target="_blank" class="fw-bold">Meta Business Suite &rarr; Business Settings</a>.</li>
                <li>Navigate to <strong>Users &rarr; System Users</strong> and click <strong>Add</strong> (Admin role).</li>
                <li>Click <strong>Generate New Token</strong>, select your App, and enable permissions: <code>whatsapp_business_messaging</code>, <code>whatsapp_business_management</code>.</li>
                <li>Set expiration to <strong>Never</strong>.</li>
            </ol>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Save System Token</h6>
            <p class="text-muted small mb-0">
                Copy the generated permanent token and paste it into the <strong>System Access Token</strong> field below. Users will still specify their own Phone Number ID in their account setup.
            </p>
        </div>
        ';
    }
}