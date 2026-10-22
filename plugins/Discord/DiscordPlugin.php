<?php
// plugins/Discord/DiscordPlugin.php

require_once __DIR__ . '/../../app/Core/Config.php';

class DiscordPlugin implements SocialPluginInterface {
    
    public function getId(): string {
        return 'discord';
    }

    public function getName(): string {
        return 'Discord Webhook';
    }

    /**
     * Get official brand color hex code for Discord
     */
    public function getColor(): string {
        return '#5865F2';
    }

    public function getSettingsFields(): array {
        return [];
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=discord';
    }

    public function handleCallback(array $requestData): array {
        $webhookUrl = trim($requestData['webhook_url'] ?? '');

        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("A valid Discord Webhook URL is required.");
        }

        // Test webhook and retrieve details
        $status = $this->checkTokenStatus($webhookUrl);
        if (!$status['valid']) {
            throw new Exception("Invalid Webhook URL: " . ($status['message'] ?? 'Discord rejected the URL.'));
        }

        $platformName = $status['name'] ?? 'Discord Webhook';

        return [
            'access_token' => $webhookUrl,
            'platform_user_id' => 'discord_' . md5($webhookUrl),
            'platform_name' => $platformName
        ];
    }

    public function getManualSetupHtml(): string {
        return '
        <div class="alert alert-info small mb-4">
            <strong>How to get your Webhook:</strong> Go to your Discord Server &rarr; Channel Settings &rarr; Integrations &rarr; Webhooks &rarr; New Webhook &rarr; Copy Webhook URL.
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Discord Webhook URL</label>
            <input type="url" name="webhook_url" class="form-control" placeholder="https://discord.com/api/webhooks/..." required>
        </div>
        ';
    }

    /**
     * Live verification of Discord Webhook URL
     */
    public function checkTokenStatus(string $webhookUrl): array {
        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'message' => 'Invalid Webhook URL structure.'];
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && !empty($data['id'])) {
            $name = !empty($data['name']) ? $data['name'] : 'Discord Channel';
            return [
                'valid' => true,
                'message' => "Webhook is active ({$name})",
                'name' => $name
            ];
        }

        $errorMsg = $data['message'] ?? "Discord API returned status {$httpCode}";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    public function publishPost(string $content, array $mediaUrls, string $webhookUrl, ?string $sourceLink = null): array {
        if (empty($webhookUrl)) {
            return ['success' => false, 'error' => 'Webhook URL is missing.'];
        }

        $plainContent = trim(html_entity_decode(strip_tags($content)));
        $fullContent = $plainContent;

        if (!empty($sourceLink)) {
            if (strpos($fullContent, $sourceLink) === false) {
                $fullContent .= "\n\n" . $sourceLink;
            }
        }

        $payloadData = [
            'content' => $fullContent
        ];

        // Attach native Discord Embed for media or external links
        if (!empty($mediaUrls[0]) || !empty($sourceLink)) {
            $embed = [
                'title'       => mb_substr($plainContent, 0, 100) ?: 'New Post',
                'description' => mb_substr($plainContent, 0, 300),
                'color'       => 5814783
            ];

            if (!empty($sourceLink)) {
                $embed['url'] = $sourceLink;
            }

            if (!empty($mediaUrls[0])) {
                $embed['image'] = ['url' => $mediaUrls[0]];
            }

            $payloadData['embeds'] = [$embed];
        }

        $payload = json_encode($payloadData);

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $resultRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 204 && $httpCode !== 200) {
            $response = json_decode($resultRaw, true);
            return ['success' => false, 'error' => $response['message'] ?? 'API Error ' . $httpCode];
        }

        return ['success' => true, 'post_id' => 'discord_' . time()];
    }
}