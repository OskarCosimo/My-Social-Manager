<?php
// app/Controllers/RssController.php

require_once __DIR__ . '/../Models/RssFeed.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/Group.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Models/MailingList.php';
require_once __DIR__ . '/../Services/AiContentService.php';

class RssController {

    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        
        $feeds = RssFeed::getUserFeeds($userId);
        $connectedAccounts = SocialAccount::getUserAccounts($userId);

        $db = Database::getInstance(); 
        $groupModel = new \App\Models\Group($db);
        $groups = $groupModel->getUserGroups($userId);

        // Fetch user mailing lists
        $mailingLists = MailingList::getUserLists($userId);

        // Fetch dynamic plan limits directly from DB
        $limits = UsageManager::getUserLimits($userId);

        require_once __DIR__ . '/../Views/rss/index.php';
    }

    /**
     * Extract and normalize AI caption settings from POST payload
     */
    private function extractAiSettings(array $post): array {
        $aiEnabled = isset($post['ai_enabled']) ? 1 : 0;
        $aiPrompt = !empty($post['ai_prompt']) ? trim((string)$post['ai_prompt']) : null;

        $detailLevel = $post['ai_detail_level'] ?? 'medium';
        if (!in_array($detailLevel, ['light', 'medium', 'detailed'], true)) {
            $detailLevel = 'medium';
        }

        $defaults = AiContentService::getDetailLevelConfig($detailLevel);
        $maxTokens = (int)($post['ai_max_tokens'] ?? $defaults['tokens']);
        if ($maxTokens <= 0) {
            $maxTokens = $defaults['tokens'];
        }
        $maxTokens = max(50, min(4096, $maxTokens));

        $maxChars = max(0, (int)($post['ai_max_chars'] ?? 0));

        return [
            'ai_enabled'      => $aiEnabled,
            'ai_prompt'       => $aiPrompt,
            'ai_detail_level' => $detailLevel,
            'ai_max_tokens'   => $maxTokens,
            'ai_max_chars'    => $maxChars
        ];
    }

    /**
     * Handle the addition of a new RSS feed with advanced and AI settings
     */
    public function add(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $feedUrl = filter_var($_POST['feed_url'] ?? '', FILTER_SANITIZE_URL);
        $targetAccounts = $_POST['target_accounts'] ?? [];
        $targetMailingLists = $_POST['target_mailing_lists'] ?? [];

        $intervalHours       = max(1, min(24, (int)($_POST['post_interval_hours'] ?? 1)));
        $onlyNewItems        = isset($_POST['only_new_items']) ? 1 : 0;
        $randomRepostEnabled = isset($_POST['random_repost_enabled']) ? 1 : 0;
        $randomRepostLimit   = max(10, min(100, (int)($_POST['random_repost_limit'] ?? 10)));

        $aiSettings = $this->extractAiSettings($_POST);

        // Merge social accounts and mailing lists into target_accounts
        $mergedTargets = [];
        foreach ($targetAccounts as $accId) {
            if (!empty($accId)) {
                $mergedTargets[] = (int)$accId;
            }
        }
        foreach ($targetMailingLists as $mlId) {
            if (!empty($mlId)) {
                $mergedTargets[] = 'ml_' . (int)$mlId;
            }
        }

        // Check user's current number of feeds
        $existingFeeds = RssFeed::getUserFeeds($userId);
        $currentFeedCount = count($existingFeeds);

        // Fetch user limits
        $limits = UsageManager::getUserLimits($userId);
        $limit = (int)($limits['max_rss_feeds'] ?? 5);

        // Enforce the RSS feed limit
        if ($limit > 0 && $currentFeedCount >= $limit) {
            $_SESSION['error'] = "You have reached the maximum limit of RSS feeds ({$limit}) allowed by your current plan. Please upgrade your subscription.";
            header('Location: /rss');
            exit;
        }

        if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['error'] = "Invalid RSS Feed URL.";
        } elseif (empty($mergedTargets)) {
            $_SESSION['error'] = "You must select at least one social account or mailing list.";
        } else {
            RssFeed::add(
                $userId, 
                $feedUrl, 
                $mergedTargets, 
                $intervalHours, 
                $onlyNewItems, 
                $randomRepostEnabled, 
                $randomRepostLimit,
                $aiSettings['ai_enabled'],
                $aiSettings['ai_prompt'],
                $aiSettings['ai_detail_level'],
                $aiSettings['ai_max_tokens'],
                $aiSettings['ai_max_chars']
            );
            $_SESSION['success'] = "RSS Feed added successfully! The system will check it periodically.";
        }

        header('Location: /rss');
        exit;
    }

    /**
     * Handle updating an existing RSS feed
     */
    public function update(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $feedId = (int)($_POST['feed_id'] ?? 0);
        $feedUrl = filter_var($_POST['feed_url'] ?? '', FILTER_SANITIZE_URL);
        $targetAccounts = $_POST['target_accounts'] ?? [];
        $targetMailingLists = $_POST['target_mailing_lists'] ?? [];

        $intervalHours       = max(1, min(24, (int)($_POST['post_interval_hours'] ?? 1)));
        $onlyNewItems        = isset($_POST['only_new_items']) ? 1 : 0;
        $randomRepostEnabled = isset($_POST['random_repost_enabled']) ? 1 : 0;
        $randomRepostLimit   = max(10, min(100, (int)($_POST['random_repost_limit'] ?? 10)));

        $aiSettings = $this->extractAiSettings($_POST);

        // Merge social accounts and mailing lists
        $mergedTargets = [];
        foreach ($targetAccounts as $accId) {
            if (!empty($accId)) {
                $mergedTargets[] = (int)$accId;
            }
        }
        foreach ($targetMailingLists as $mlId) {
            if (!empty($mlId)) {
                $mergedTargets[] = 'ml_' . (int)$mlId;
            }
        }

        $feed = RssFeed::getById($feedId, $userId);

        if (!$feed) {
            $_SESSION['error'] = "RSS Feed not found or unauthorized.";
        } elseif (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['error'] = "Invalid RSS Feed URL.";
        } elseif (empty($mergedTargets)) {
            $_SESSION['error'] = "You must select at least one social account or mailing list.";
        } else {
            RssFeed::update(
                $feedId, 
                $userId, 
                $feedUrl, 
                $mergedTargets, 
                $intervalHours, 
                $onlyNewItems, 
                $randomRepostEnabled, 
                $randomRepostLimit,
                $aiSettings['ai_enabled'],
                $aiSettings['ai_prompt'],
                $aiSettings['ai_detail_level'],
                $aiSettings['ai_max_tokens'],
                $aiSettings['ai_max_chars']
            );
            $_SESSION['success'] = "RSS Feed updated successfully!";
        }

        header('Location: /rss');
        exit;
    }

    public function delete(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $feedId = (int)($_POST['feed_id'] ?? 0);

        if ($feedId > 0) {
            if (RssFeed::delete($feedId, $userId)) {
                $_SESSION['success'] = "Feed deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting the feed.";
            }
        } else {
            $_SESSION['error'] = "Invalid feed ID.";
        }

        header('Location: /rss');
        exit;
    }
}
