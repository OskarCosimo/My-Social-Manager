<?php
// plugins/Slack/SlackPlugin.php

require_once __DIR__ . '/../../app/Core/Config.php';

class SlackPlugin implements SocialPluginInterface {

    public function getId(): string {
        return 'slack';
    }

    public function getName(): string {
        return 'Slack (Incoming Webhook)';
    }

    /**
     * Get official brand color hex code for Slack
     */
    public function getColor(): string {
        return '#4A154B';
    }

    public function getSettingsFields(): array {
        return [];
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    public function getManualSetupHtml(): string {
        return '
        <div class="alert alert-info small mb-4">
            <strong>How to create a Slack Incoming Webhook:</strong><br><br>
            1. Go to the <a href="https://api.slack.com/apps" target="_blank">Slack API App Directory</a> and create or select an App.<br>
            2. Enable <strong>Incoming Webhooks</strong> and click <strong>Add New Webhook to Workspace</strong>.<br>
            3. Select the target channel and copy the <strong>Webhook URL</strong>.<br>
            4. Paste the URL below.
        </div>

        <input type="hidden" name="slack_submit" value="1">

        <div class="mb-3">
            <label class="form-label fw-bold">Slack Webhook URL</label>
            <input type="url" name="webhook_url" class="form-control" placeholder="https://hooks.slack.com/services/T0000/B0000/XXXX" required>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Channel Name / Label (Optional)</label>
            <input type="text" name="channel_name" class="form-control" placeholder="e.g. #announcements or #social-feed">
            <div class="form-text">For your personal reference in the dashboard.</div>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (!isset($requestData['slack_submit'])) {
            throw new Exception("Invalid submission.");
        }

        $webhookUrl = trim($requestData['webhook_url'] ?? '');
        $channelName = trim($requestData['channel_name'] ?? '');

        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("A valid Slack Webhook URL is required.");
        }

        if (!str_starts_with($webhookUrl, 'https://hooks.slack.com/')) {
            throw new Exception("URL must start with https://hooks.slack.com/");
        }

        // Test webhook connection live
        $status = $this->checkTokenStatus($webhookUrl);
        if (!$status['valid']) {
            throw new Exception("Slack Webhook rejected: " . $status['message']);
        }

        $displayName = !empty($channelName) ? $channelName : 'Slack Channel';

        $bundledData = json_encode([
            'webhook_url'  => $webhookUrl,
            'channel_name' => $displayName
        ]);

        return [
            'access_token'     => $bundledData,
            'platform_user_id' => 'slack_' . md5($webhookUrl),
            'platform_name'    => "{$displayName} (Slack)"
        ];
    }

    /**
     * Live verification of Slack Webhook.
     * Sending empty JSON {} tests the webhook validity without publishing a notification to the channel.
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $webhookUrl = (is_array($decoded) && isset($decoded['webhook_url'])) ? $decoded['webhook_url'] : $accessToken;
        $channelName = (is_array($decoded) && isset($decoded['channel_name'])) ? $decoded['channel_name'] : 'Slack';

        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'message' => 'Invalid Slack Webhook URL structure.'];
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $resultRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Slack returns "no_text" on empty payload when the webhook is active and valid
        if ($resultRaw === 'no_text' || $httpCode === 200) {
            return [
                'valid'   => true,
                'message' => "Slack webhook active ({$channelName})"
            ];
        }

        if ($httpCode === 404 || str_contains($resultRaw, '404_not_found')) {
            return [
                'valid'   => false,
                'message' => 'Webhook URL not found or removed from Slack workspace.'
            ];
        }

        return [
            'valid'   => false,
            'message' => 'Slack error: ' . ($resultRaw ?: "HTTP {$httpCode}")
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken): array {
        return $this->publishPostWithLink($content, $mediaUrls, $accessToken, null);
    }

    public function publishPostWithLink(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $decoded = json_decode($accessToken, true);
        $webhookUrl = (is_array($decoded) && isset($decoded['webhook_url'])) ? $decoded['webhook_url'] : $accessToken;

        if (empty($webhookUrl)) {
            return ['success' => false, 'error' => 'Slack Webhook URL is missing.'];
        }

        // Clean HTML tags and preserve line breaks
        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $cleanMessage = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $fullText = $cleanMessage;
        if (!empty($sourceLink)) {
            if (strpos($fullText, $sourceLink) === false) {
                $fullText .= "\n\n" . $sourceLink;
            }
        }

        $payload = [
            'text' => $fullText
        ];

        // Attach native image preview if available
        if (!empty($mediaUrls[0])) {
            $payload['attachments'] = [
                [
                    'fallback'  => 'Image attachment',
                    'image_url' => $mediaUrls[0],
                    'title'     => mb_substr($cleanMessage, 0, 80) ?: 'Attached Media'
                ]
            ];
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $resultRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $resultRaw === 'ok') {
            return ['success' => true, 'post_id' => 'slack_' . time()];
        }

        return [
            'success' => false,
            'error'   => 'Slack API Error: ' . ($resultRaw ?: "HTTP {$httpCode}")
        ];
    }
}