<?php
// plugins/Telegram/TelegramPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';

class TelegramPlugin implements SocialPluginInterface {
    
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    public function getId(): string {
        return 'telegram';
    }

    public function getName(): string {
        return 'Telegram (Channel/Group)';
    }

    /**
     * Get official brand color hex code for Telegram
     */
    public function getColor(): string {
        return '#24A1DE';
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
            <strong>How to connect Telegram:</strong><br><br>
            1. Open Telegram and search for <strong>@BotFather</strong>.<br>
            2. Send <code>/newbot</code> and follow the instructions to create your bot.<br>
            3. Copy the <strong>HTTP API Token</strong> provided by BotFather.<br>
            4. Open your Telegram Channel or Group, go to Settings &rarr; Administrators, and <strong>add your new bot as an Admin</strong> with permission to post messages.<br>
            5. For public channels, your Chat ID is the username (e.g., <code>@mychannel</code>). For private groups, you can get the Chat ID by forwarding a message to <strong>@RawDataBot</strong>.
        </div>
        
        <input type="hidden" name="telegram_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Bot Token</label>
            <input type="password" name="bot_token" class="form-control" placeholder="e.g., 123456789:ABCdefGHIjklmNOPqrsTUVwxyz" required>
            <div class="form-text">The HTTP API Token given by @BotFather.</div>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Target Chat ID or Username</label>
            <input type="text" name="chat_id" class="form-control" placeholder="e.g., @mychannel or -1001234567890" required>
            <div class="form-text">Where should the bot post? Must include the @ for public channels or the - for private groups.</div>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        if (!isset($requestData['telegram_submit'])) {
            throw new Exception("Invalid form submission.");
        }

        $botToken = trim($requestData['bot_token'] ?? '');
        $chatId = trim($requestData['chat_id'] ?? '');

        if (empty($botToken) || empty($chatId)) {
            throw new Exception("Both Bot Token and Chat ID are required.");
        }

        $verifyUrl = self::API_BASE_URL . $botToken . '/getMe';
        
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $verifyRaw = curl_exec($ch);
        curl_close($ch);

        $verifyData = json_decode($verifyRaw, true);

        if (empty($verifyData['ok']) || $verifyData['ok'] !== true) {
            throw new Exception("Invalid Bot Token. Telegram API rejected it.");
        }

        $botName = $verifyData['result']['first_name'] ?? 'Telegram Bot';

        // Check if target chat/channel is reachable
        $chatUrl = self::API_BASE_URL . $botToken . '/getChat?chat_id=' . urlencode($chatId);
        $ch2 = curl_init($chatUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $chatRaw = curl_exec($ch2);
        curl_close($ch2);
        $chatData = json_decode($chatRaw, true);

        $chatTitle = (!empty($chatData['ok']) && !empty($chatData['result']['title'])) ? $chatData['result']['title'] : $chatId;

        $bundledCredentials = json_encode([
            'bot_token' => $botToken,
            'chat_id' => $chatId
        ]);

        return [
            'access_token' => $bundledCredentials,
            'platform_user_id' => $verifyData['result']['id'] . '_' . md5($chatId),
            'platform_name' => "{$botName} ➔ {$chatTitle}"
        ];
    }

    /**
     * Live verification of Telegram Bot Token and Chat accessibility
     */
    public function checkTokenStatus(string $accessToken): array {
        $decoded = json_decode($accessToken, true);
        $token = (is_array($decoded) && isset($decoded['bot_token'])) ? $decoded['bot_token'] : $accessToken;
        $chatId = (is_array($decoded) && isset($decoded['chat_id'])) ? $decoded['chat_id'] : null;

        if (empty($token)) {
            return ['valid' => false, 'message' => 'Telegram Bot Token is missing.'];
        }

        // 1. Check Bot validity
        $ch = curl_init(self::API_BASE_URL . $token . '/getMe');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = json_decode(curl_exec($ch), true) ?? [];
        curl_close($ch);

        if (empty($res['ok']) || $res['ok'] !== true) {
            return [
                'valid' => false,
                'message' => $res['description'] ?? 'Invalid or revoked Bot Token.'
            ];
        }

        $botUsername = $res['result']['username'] ?? 'Bot';

        // 2. Check Chat validity if chatId is present
        if (!empty($chatId)) {
            $chChat = curl_init(self::API_BASE_URL . $token . '/getChat?chat_id=' . urlencode($chatId));
            curl_setopt($chChat, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chChat, CURLOPT_TIMEOUT, 10);
            $chatRes = json_decode(curl_exec($chChat), true) ?? [];
            curl_close($chChat);

            if (empty($chatRes['ok']) || $chatRes['ok'] !== true) {
                return [
                    'valid' => false,
                    'message' => "Bot (@{$botUsername}) cannot access chat {$chatId}: " . ($chatRes['description'] ?? 'Check admin permissions.')
                ];
            }

            $chatTitle = $chatRes['result']['title'] ?? $chatId;
            return [
                'valid' => true,
                'message' => "Active: @{$botUsername} ➔ {$chatTitle}"
            ];
        }

        return [
            'valid' => true,
            'message' => "Bot active (@{$botUsername})"
        ];
    }

    /**
     * Publish a post to Telegram.
     * Standardized parameters: 1: $content, 2: $mediaUrls, 3: $accessToken (JSON string), 4: $sourceLink
     */
    public function publishPost(string $content, array $mediaUrls = [], string $accessToken = '', ?string $sourceLink = null): array
    {
        // Decode the bundled credentials stored in $accessToken
        $decoded = json_decode($accessToken, true);
        $token = (is_array($decoded) && isset($decoded['bot_token'])) ? $decoded['bot_token'] : $accessToken;
        $targetChatId = (is_array($decoded) && isset($decoded['chat_id'])) ? $decoded['chat_id'] : null;

        if (empty($token) || empty($targetChatId)) {
            return [
                'success' => false,
                'error'   => 'Telegram Bot Token or Chat ID is missing.'
            ];
        }

        // Clean HTML tags for plain text content
        $plainContent = trim(html_entity_decode(strip_tags($content)));

        // Re-append source link to the text body if present
        $fullText = $plainContent;
        if (!empty($sourceLink)) {
            if (strpos($fullText, $sourceLink) === false) {
                $fullText .= "\n\n" . $sourceLink;
            }
        }

        // Handle media posting (Photo vs Text)
        if (!empty($mediaUrls)) {
            $apiUrl = "https://api.telegram.org/bot{$token}/sendPhoto";
            $payload = [
                'chat_id' => $targetChatId,
                'photo'   => $mediaUrls[0],
                'caption' => mb_substr($fullText, 0, 1024) // Telegram caption max length
            ];
        } else {
            $apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";
            $payload = [
                'chat_id'    => $targetChatId,
                'text'       => $fullText,
                'disable_web_page_preview' => false
            ];
        }

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'error'   => 'cURL Error: ' . $curlError
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['ok']) && $responseData['ok']) {
            return [
                'success'  => true,
                'post_id'  => $responseData['result']['message_id'] ?? ('telegram_' . time()),
                'response' => $responseData
            ];
        }

        return [
            'success' => false,
            'error'   => $responseData['description'] ?? 'Unknown error from Telegram API (HTTP code ' . $httpCode . ')'
        ];
    }
}