<?php
// cron/rss_worker.php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

require_once BASE_PATH . 'app/Core/Config.php';
require_once BASE_PATH . 'app/Models/RssFeed.php';
require_once BASE_PATH . 'app/Models/UsageManager.php';
require_once BASE_PATH . 'app/Core/Database.php';

// Allow execution either via CLI or via internal PHP cron runner
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CRON_EXEC')) {
    die("This script can only be run from the command line or internal runner.");
}

// Synchronize timezone with publisher worker and database UTC
date_default_timezone_set('UTC');

echo "[START] RSS Worker started at " . date('Y-m-d H:i:s') . "\n";

$feeds = RssFeed::getAllForCron();

if (empty($feeds)) {
    echo "No feeds to process.\n";
    return;
}

$db = Database::getInstance();

foreach ($feeds as $feed) {
    echo "Processing feed ID [{$feed['id']}]: " . $feed['feed_url'] . "\n";

    $intervalHours = max(1, (int)($feed['post_interval_hours'] ?? 1));

    // Check if the configured interval has elapsed since the last check
    if (!empty($feed['last_checked'])) {
        $lastCheckedTimestamp = strtotime($feed['last_checked']);
        $intervalSeconds = $intervalHours * 3600;
        $nextAllowedRun = $lastCheckedTimestamp + $intervalSeconds;

        if (time() < $nextAllowedRun) {
            echo " - Skipped: Interval of {$intervalHours}h not yet elapsed. Next run eligible after: " . date('Y-m-d H:i:s', $nextAllowedRun) . "\n\n";
            continue;
        }
    }

    if (!UsageManager::canPublishRssPost($feed['user_id'])) {
        echo " - Skipped: User ID {$feed['user_id']} reached RSS posts quota.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
    }

    $xmlContext = stream_context_create([
        'http' => ['timeout' => 15, 'user_agent' => 'MySocialManager/1.0']
    ]);
    
    $rssData = @file_get_contents($feed['feed_url'], false, $xmlContext);
    
    if (!$rssData) {
        echo " - Error: Could not fetch URL content.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
    }

    $xml = @simplexml_load_string($rssData);
    
    if (!$xml) {
        echo " - Error: Invalid XML/Atom format.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
    }

    $rawItems = [];
    if (isset($xml->channel->item)) {
        $rawItems = $xml->channel->item;
    } elseif (isset($xml->entry)) {
        $rawItems = $xml->entry;
    }

    if (empty($rawItems)) {
        echo " - Warning: No items found in feed.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
    }

    $targetAccounts = json_decode($feed['target_accounts'], true);
    if (!is_array($targetAccounts) || empty($targetAccounts)) {
        echo " - Error: Invalid target accounts configuration.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
    }

    $onlyNewItems        = !empty($feed['only_new_items']);
    $randomRepostEnabled = !empty($feed['random_repost_enabled']);
    $randomRepostLimit   = max(10, (int)($feed['random_repost_limit'] ?? 10));
    $isFirstCheck        = empty($feed['last_checked']);

    $addedPosts = 0;
    $currentPublishTime = time();

    if ($onlyNewItems && $isFirstCheck) {
        echo " - 'Only new items' active on first check. Ignoring current items.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
    }

    $parsedItems = [];
    foreach ($rawItems as $item) {
        $title = isset($item->title) ? trim((string)$item->title) : '';
        $link = '';
        if (isset($item->link)) {
            if (isset($item->link['href'])) {
                $link = trim((string)$item->link['href']);
            } else {
                $link = trim((string)$item->link);
            }
        }
        if (empty($link) && isset($item->id)) {
            $link = trim((string)$item->id);
        }

        if (!empty($title) && !empty($link)) {
            $parsedItems[] = ['title' => $title, 'link' => $link];
        }
    }

    $itemsToProcess = $parsedItems;
    if ($randomRepostEnabled && !empty($parsedItems)) {
        $recentItems = array_slice($parsedItems, 0, $randomRepostLimit);
        $randomItem = $recentItems[array_rand($recentItems)];
        $itemsToProcess = [$randomItem];
    }

    foreach ($itemsToProcess as $item) {
        if (!UsageManager::canPublishRssPost($feed['user_id'])) {
            echo " - RSS Quota limit reached during processing.\n";
            break;
        }

        $postContent = $item['title'] . "\n\n" . $item['link'];
        
        foreach ($targetAccounts as $accountId) {
            if (!$randomRepostEnabled) {
                $stmt = $db->prepare("SELECT id FROM posts WHERE social_account_id = ? AND (source_link = ? OR content LIKE ?)");
                $searchString = "%" . $item['link'] . "%";
                $stmt->execute([$accountId, $item['link'], $searchString]);
                if ($stmt->fetch()) {
                    continue;
                }
            }

            $scheduledDate = date('Y-m-d H:i:s', $currentPublishTime);

            $insertStmt = $db->prepare("
                INSERT INTO posts (user_id, social_account_id, content, status, scheduled_for, scheduled_at, source_link, is_rss) 
                VALUES (?, ?, ?, 'scheduled', ?, ?, ?, 1)
            ");
            $insertStmt->execute([
                $feed['user_id'], 
                $accountId, 
                $postContent, 
                $scheduledDate, 
                $scheduledDate, 
                $item['link']
            ]);
            
            $addedPosts++;
        }

        $currentPublishTime += ($intervalHours * 3600);
    }

    echo " - Added $addedPosts new RSS posts to the queue.\n";
    RssFeed::updateLastChecked($feed['id']);
    echo " - Feed ID {$feed['id']} successfully checked and updated.\n\n";
}

echo "[END] RSS Worker finished successfully at " . date('Y-m-d H:i:s') . "\n";