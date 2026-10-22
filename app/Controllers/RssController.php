<?php
// app/Controllers/RssController.php

require_once __DIR__ . '/../Models/RssFeed.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/Group.php';
require_once __DIR__ . '/../Models/UsageManager.php';

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

        // Fetch dynamic plan limits directly from DB
        $limits = UsageManager::getUserLimits($userId);

        require_once __DIR__ . '/../Views/rss/index.php';
    }

    /**
     * Handle the addition of a new RSS feed with advanced settings
     */
    public function add(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $feedUrl = filter_var($_POST['feed_url'] ?? '', FILTER_SANITIZE_URL);
        $targetAccounts = $_POST['target_accounts'] ?? [];

        $intervalHours       = max(1, min(24, (int)($_POST['post_interval_hours'] ?? 1)));
        $onlyNewItems        = isset($_POST['only_new_items']) ? 1 : 0;
        $randomRepostEnabled = isset($_POST['random_repost_enabled']) ? 1 : 0;
        $randomRepostLimit   = max(10, min(100, (int)($_POST['random_repost_limit'] ?? 10)));

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
        } elseif (empty($targetAccounts)) {
            $_SESSION['error'] = "You must select at least one social account.";
        } else {
            RssFeed::add(
                $userId, 
                $feedUrl, 
                $targetAccounts, 
                $intervalHours, 
                $onlyNewItems, 
                $randomRepostEnabled, 
                $randomRepostLimit
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

        $intervalHours       = max(1, min(24, (int)($_POST['post_interval_hours'] ?? 1)));
        $onlyNewItems        = isset($_POST['only_new_items']) ? 1 : 0;
        $randomRepostEnabled = isset($_POST['random_repost_enabled']) ? 1 : 0;
        $randomRepostLimit   = max(10, min(100, (int)($_POST['random_repost_limit'] ?? 10)));

        $feed = RssFeed::getById($feedId, $userId);

        if (!$feed) {
            $_SESSION['error'] = "RSS Feed not found or unauthorized.";
        } elseif (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['error'] = "Invalid RSS Feed URL.";
        } elseif (empty($targetAccounts)) {
            $_SESSION['error'] = "You must select at least one social account.";
        } else {
            RssFeed::update(
                $feedId, 
                $userId, 
                $feedUrl, 
                $targetAccounts, 
                $intervalHours, 
                $onlyNewItems, 
                $randomRepostEnabled, 
                $randomRepostLimit
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