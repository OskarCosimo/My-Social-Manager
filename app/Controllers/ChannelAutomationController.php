<?php
// app/Controllers/ChannelAutomationController.php

require_once __DIR__ . '/../Models/ChannelAutomation.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/Group.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Core/Database.php';

class ChannelAutomationController {

    private const ALLOWED_SOURCE_PLUGINS = [
        'youtube', 
        'facebook', 
        'instagram', 
        'linkedin_pages', 
        'tumblr', 
        'wordpress',
        'twitch',
        'medium',
        'myetv',
        'threads',
        'devto'
    ];

    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        $automations = ChannelAutomation::getUserAutomations($userId);
        $connectedAccounts = SocialAccount::getUserAccounts($userId);

        $groupModel = new \App\Models\Group($db);
        $groups = $groupModel->getUserGroups($userId);

        $limits = UsageManager::getUserLimits($userId);

        require_once __DIR__ . '/../Views/channel_automations/index.php';
    }

    /**
     * Add a new channel automation
     */
    public function add(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];

        if (!UsageManager::canAddChannelAutomation($userId)) {
            $_SESSION['error'] = "You have reached the maximum number of Channel Automations allowed by your plan.";
            header('Location: /channel-automations');
            exit;
        }

        $sourceType = $_POST['source_type'] ?? 'connected_account';
        $targetAccounts = $_POST['target_accounts'] ?? [];

        $intervalHours       = max(1, min(24, (int)($_POST['post_interval_hours'] ?? 1)));
        $onlyNewItems        = isset($_POST['only_new_items']) ? 1 : 0;
        $randomRepostEnabled = isset($_POST['random_repost_enabled']) ? 1 : 0;
        $randomRepostLimit   = max(10, min(100, (int)($_POST['random_repost_limit'] ?? 10)));

        $sourceAccountId = null;
        $sourceIdentifier = null;
        $sourceTitle = '';

        if ($sourceType === 'youtube_url') {
            $rawUrl = trim($_POST['youtube_url'] ?? '');
            if (empty($rawUrl)) {
                $_SESSION['error'] = "Please provide a valid YouTube channel URL or handle.";
                header('Location: /channel-automations');
                exit;
            }

            $channelId = $this->resolveYoutubeChannelId($rawUrl);
            if (!$channelId) {
                $_SESSION['error'] = "Could not resolve a valid YouTube Channel ID from the provided URL. Please check the link.";
                header('Location: /channel-automations');
                exit;
            }

            $sourceIdentifier = $channelId;
            $sourceTitle = "YouTube: " . htmlspecialchars($rawUrl);
        } else {
            $sourceAccountId = (int)($_POST['source_account_id'] ?? 0);
            $account = SocialAccount::getAccountById($sourceAccountId, $userId);

            if (!$account) {
                $_SESSION['error'] = "Selected source account not found or unauthorized.";
                header('Location: /channel-automations');
                exit;
            }

            if (!in_array($account['plugin_id'], self::ALLOWED_SOURCE_PLUGINS, true)) {
                $_SESSION['error'] = "The selected platform does not support automated content monitoring.";
                header('Location: /channel-automations');
                exit;
            }

            $sourceTitle = ucfirst(str_replace('_', ' ', $account['plugin_id'])) . " - " . $account['platform_name'];
            $sourceIdentifier = (string)$account['platform_user_id'];

            // ANTI-LOOP: Filter out the source account from the target accounts list
            $targetAccounts = array_filter($targetAccounts, function($id) use ($sourceAccountId) {
                return (int)$id !== $sourceAccountId;
            });
        }

        if (empty($targetAccounts) || !is_array($targetAccounts)) {
            $_SESSION['error'] = "You must select at least one target account that is different from the source.";
            header('Location: /channel-automations');
            exit;
        }

        ChannelAutomation::add(
            $userId,
            $sourceType,
            $sourceAccountId,
            $sourceIdentifier,
            $sourceTitle,
            array_values($targetAccounts),
            $intervalHours,
            $onlyNewItems,
            $randomRepostEnabled,
            $randomRepostLimit
        );

        $_SESSION['success'] = "Channel automation added successfully! Anti-loop guards are active.";
        header('Location: /channel-automations');
        exit;
    }

    public function update(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $id = (int)($_POST['automation_id'] ?? 0);
        $targetAccounts = $_POST['target_accounts'] ?? [];

        $intervalHours       = max(1, min(24, (int)($_POST['post_interval_hours'] ?? 1)));
        $onlyNewItems        = isset($_POST['only_new_items']) ? 1 : 0;
        $randomRepostEnabled = isset($_POST['random_repost_enabled']) ? 1 : 0;
        $randomRepostLimit   = max(10, min(100, (int)($_POST['random_repost_limit'] ?? 10)));

        $existing = ChannelAutomation::getById($id, $userId);
        if (!$existing) {
            $_SESSION['error'] = "Automation rule not found.";
            header('Location: /channel-automations');
            exit;
        }

        if (!empty($existing['source_account_id'])) {
            $srcId = (int)$existing['source_account_id'];
            $targetAccounts = array_filter($targetAccounts, function($accId) use ($srcId) {
                return (int)$accId !== $srcId;
            });
        }

        if (empty($targetAccounts) || !is_array($targetAccounts)) {
            $_SESSION['error'] = "You must select at least one target account different from the source.";
            header('Location: /channel-automations');
            exit;
        }

        ChannelAutomation::update(
            $id,
            $userId,
            array_values($targetAccounts),
            $intervalHours,
            $onlyNewItems,
            $randomRepostEnabled,
            $randomRepostLimit
        );

        $_SESSION['success'] = "Channel automation updated successfully.";
        header('Location: /channel-automations');
        exit;
    }

    public function delete(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $id = (int)($_POST['automation_id'] ?? 0);

        if ($id > 0 && ChannelAutomation::delete($id, $userId)) {
            $_SESSION['success'] = "Channel automation deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete channel automation.";
        }

        header('Location: /channel-automations');
        exit;
    }

    /**
     * Robust resolution of YouTube Channel ID (handles handles, custom URLs, and direct IDs)
     */
    private function resolveYoutubeChannelId(string $input): ?string {
        $input = trim($input);

        // 1. Direct Channel ID match (starts with UC and is 24 characters)
        if (preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $input)) {
            return $input;
        }

        // 2. Direct channel URL match (/channel/UC...)
        if (preg_match('/(?:youtube\.com\/channel\/)(UC[a-zA-Z0-9_-]{22})/i', $input, $matches)) {
            return $matches[1];
        }

        // 3. Normalize input to valid YouTube URL
        $url = $input;
        if (str_starts_with($url, '@')) {
            $url = 'https://www.youtube.com/' . $url;
        } elseif (!preg_match('~^https?://~i', $url)) {
            $url = 'https://www.youtube.com/@' . ltrim($url, '/');
        }

        // 4. Use cURL with European Cookie Consent bypass
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cookie: SOCS=CAESEwgDEgk0ODE3Nzk3MjQaAmVuIAEaBgiA_LyaBg;' // Bypasses Google European Cookie Consent screen
        ]);
        
        $html = curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        // Check if redirect ended directly on a /channel/UC... URL
        if ($finalUrl && preg_match('/(?:youtube\.com\/channel\/)(UC[a-zA-Z0-9_-]{22})/i', $finalUrl, $m)) {
            return $m[1];
        }

        if (!empty($html)) {
            // Pattern A: Canonical link tag
            if (preg_match('/<link\s+rel="canonical"\s+href="https:\/\/www\.youtube\.com\/channel\/(UC[a-zA-Z0-9_-]{22})"/i', $html, $m)) {
                return $m[1];
            }
            // Pattern B: Meta itemprop channelId or identifier
            if (preg_match('/<meta\s+itemprop="(?:channelId|identifier)"\s+content="(UC[a-zA-Z0-9_-]{22})"/i', $html, $m)) {
                return $m[1];
            }
            // Pattern C: JSON attributes (browseId, externalId, channelId)
            if (preg_match('/"(?:browseId|externalId|channelId)":"(UC[a-zA-Z0-9_-]{22})"/i', $html, $m)) {
                return $m[1];
            }
            // Pattern D: Any /channel/UC reference
            if (preg_match('/\/channel\/(UC[a-zA-Z0-9_-]{22})["\/]/i', $html, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}