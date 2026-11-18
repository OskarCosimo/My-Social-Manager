<?php
// cron/rss_worker.php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

require_once BASE_PATH . 'app/Core/Config.php';
require_once BASE_PATH . 'app/Models/RssFeed.php';
require_once BASE_PATH . 'app/Models/UsageManager.php';
require_once BASE_PATH . 'app/Models/MailingList.php';
require_once BASE_PATH . 'app/Core/Mailer.php';
require_once BASE_PATH . 'app/Core/Database.php';
require_once BASE_PATH . 'app/Services/AiContentService.php';

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
        'http' => [
            'timeout'    => 15, 
            'user_agent' => 'Mozilla/5.0 (compatible; MySocialManager/1.0; +https://myetv.tv)',
            'header'     => "Cache-Control: no-cache\r\nPragma: no-cache\r\n"
        ]
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

    // Optional AI captioning configuration
    $aiEnabled     = !empty($feed['ai_enabled']);
    $aiPrompt      = trim((string)($feed['ai_prompt'] ?? ''));
    $aiDetailLevel = (string)($feed['ai_detail_level'] ?? 'medium');
    $aiMaxTokens   = (int)($feed['ai_max_tokens'] ?? 350);
    $aiMaxChars    = max(0, (int)($feed['ai_max_chars'] ?? 0));

    // Determine fixed baseline cutoff for "only new items" (separate from last_checked)
    $onlyNewCutoffTs = 0;
    if ($onlyNewItems) {
        if (!empty($feed['only_new_after'])) {
            $onlyNewCutoffTs = strtotime($feed['only_new_after']);
        } elseif (!empty($feed['last_checked'])) {
            // Migrate legacy feed once so future checks don't keep moving the cutoff
            RssFeed::setOnlyNewAfter((int)$feed['id'], $feed['last_checked']);
            $onlyNewCutoffTs = strtotime($feed['last_checked']);
        } else {
            echo " - 'Only new items' active on first check. Ignoring current items and initializing cutoff timestamp.\n";
            RssFeed::setOnlyNewAfter((int)$feed['id'], gmdate('Y-m-d H:i:s'));
            RssFeed::updateLastChecked($feed['id']);
            continue;
        }
    }

    $addedPosts = 0;
    $currentPublishTime = time();

    $parsedItems = [];
    foreach ($rawItems as $item) {
        // Clean title entities and encoding
        $title = isset($item->title) ? html_entity_decode(trim((string)$item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
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

        // Parse pubDate / updated timestamp
        $itemPubDate = null;
        if (isset($item->pubDate)) {
            $itemPubDate = strtotime((string)$item->pubDate);
        } elseif (isset($item->updated)) {
            $itemPubDate = strtotime((string)$item->updated);
        }

        // Filter out old items published before the feed's fixed only_new_after cutoff
        if ($onlyNewItems && $onlyNewCutoffTs > 0 && $itemPubDate && $itemPubDate <= $onlyNewCutoffTs) {
            continue;
        }

        // Extract feed description / summary / content
        $description = '';
        if (isset($item->description)) {
            $description = (string)$item->description;
        } elseif (isset($item->summary)) {
            $description = (string)$item->summary;
        }

        // Look up content:encoded namespace if available and description is empty
        if (empty($description)) {
            $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
            if (isset($contentNs->encoded)) {
                $description = (string)$contentNs->encoded;
            }
        }

        // Clean description for social networks and plain text fallback
        $cleanDescription = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanDescription = preg_replace('/\s+/', ' ', trim($cleanDescription));

        // Extract media attachment from RSS enclosure or Media RSS namespace
        $mediaUrl = null;
        if (isset($item->enclosure) && isset($item->enclosure['url'])) {
            $encType = (string)($item->enclosure['type'] ?? '');
            if (empty($encType) || str_starts_with($encType, 'image/')) {
                $mediaUrl = trim((string)$item->enclosure['url']);
            }
        }
        if (!$mediaUrl) {
            $mediaNs = $item->children('http://search.yahoo.com/mrss/');
            if (isset($mediaNs->content) && isset($mediaNs->content->attributes()->url)) {
                $mediaUrl = trim((string)$mediaNs->content->attributes()->url);
            } elseif (isset($mediaNs->thumbnail) && isset($mediaNs->thumbnail->attributes()->url)) {
                $mediaUrl = trim((string)$mediaNs->thumbnail->attributes()->url);
            }
        }

        if (!empty($title) && !empty($link)) {
            $parsedItems[] = [
                'title'       => $title, 
                'link'        => $link,
                'description' => $cleanDescription,
                'media_url'   => $mediaUrl,
                'pub_date'    => $itemPubDate
            ];
        }
    }

    if (empty($parsedItems)) {
        echo " - No qualifying items to publish.\n";
        RssFeed::updateLastChecked($feed['id']);
        continue;
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

        // Pre-filter eligible targets to avoid unnecessary AI generation on duplicate items
        $eligibleTargets = [];
        foreach ($targetAccounts as $target) {
            if (is_string($target) && str_starts_with($target, 'ml_')) {
                $listId = (int)substr($target, 3);
                $mlSourceIdentifier = "mailing_list:{$listId}";

                if (!$randomRepostEnabled) {
                    $chkStmt = $db->prepare("
                        SELECT id FROM posts 
                        WHERE user_id = ? 
                          AND source_link = ? 
                          AND is_rss = 1 
                          AND (content LIKE ? OR content LIKE ?)
                    ");
                    $chkStmt->execute([
                        $feed['user_id'], 
                        $mlSourceIdentifier, 
                        "%" . $item['link'] . "%",
                        "%" . addcslashes($item['link'], '/') . "%"
                    ]);
                    if ($chkStmt->fetch()) {
                        continue;
                    }
                }
                $eligibleTargets[] = ['type' => 'ml', 'id' => $listId, 'source_id' => $mlSourceIdentifier];
            } else {
                $accountId = (int)$target;
                if (!$randomRepostEnabled) {
                    $stmt = $db->prepare("SELECT id FROM posts WHERE social_account_id = ? AND (source_link = ? OR content LIKE ?)");
                    $searchString = "%" . $item['link'] . "%";
                    $stmt->execute([$accountId, $item['link'], $searchString]);
                    if ($stmt->fetch()) {
                        continue;
                    }
                }
                $eligibleTargets[] = ['type' => 'social', 'id' => $accountId];
            }
        }

        if (empty($eligibleTargets)) {
            continue;
        }

        // Generate post content (either AI-enhanced or standard RSS snippet)
        if ($aiEnabled) {
            if (!UsageManager::canGenerateAi((int)$feed['user_id'])) {
                echo " - Skipped AI generation: User ID {$feed['user_id']} has insufficient AI credits.\n";
                break;
            }

            $aiRes = AiContentService::generateCaption([
                'user_prompt'       => $aiPrompt,
                'item_title'        => $item['title'],
                'item_description'  => $item['description'],
                'item_link'         => $item['link'],
                'detail_level'      => $aiDetailLevel,
                'max_tokens'        => $aiMaxTokens,
                'max_chars'         => $aiMaxChars,
                'include_link'      => true,
                'temperature'       => 0.75,
                'fetch_web_context' => true
            ]);

            if (!$aiRes['success'] || empty($aiRes['text'])) {
                echo " - AI generation failed for item [{$item['title']}]: " . ($aiRes['error'] ?? 'Empty output') . "\n";
                continue;
            }

            UsageManager::incrementAiUsage((int)$feed['user_id'], 1);

            $socialPostContent = $aiRes['text'];
            $mailingListDesc   = $aiRes['caption'];
        } else {
            $descSnippet = !empty($item['description']) ? mb_substr($item['description'], 0, 300) . (mb_strlen($item['description']) > 300 ? '...' : '') . "\n\n" : '';
            $socialPostContent = $item['title'] . "\n\n" . $descSnippet . $item['link'];
            $mailingListDesc   = $item['description'];
        }

        // Build rich email payload stored as structured JSON for mailing lists
        $mailingListPayload = json_encode([
            'title'       => $item['title'],
            'description' => $mailingListDesc,
            'link'        => $item['link']
        ], JSON_UNESCAPED_UNICODE);

        $mediaUrlsJson = !empty($item['media_url']) ? json_encode([$item['media_url']]) : null;
        $scheduledDate = date('Y-m-d H:i:s', $currentPublishTime);
        $dbSourceLink  = mb_substr($item['link'], 0, 255);

        foreach ($eligibleTargets as $targetInfo) {
            if ($targetInfo['type'] === 'ml') {
                $insertStmt = $db->prepare("
                    INSERT INTO posts (user_id, social_account_id, content, media_urls, status, scheduled_for, scheduled_at, source_link, is_rss) 
                    VALUES (?, NULL, ?, ?, 'scheduled', ?, ?, ?, 1)
                ");
                $insertStmt->execute([
                    $feed['user_id'],
                    $mailingListPayload,
                    $mediaUrlsJson,
                    $scheduledDate,
                    $scheduledDate,
                    $targetInfo['source_id']
                ]);
                $addedPosts++;
            } else {
                $insertStmt = $db->prepare("
                    INSERT INTO posts (user_id, social_account_id, content, media_urls, status, scheduled_for, scheduled_at, source_link, is_rss) 
                    VALUES (?, ?, ?, ?, 'scheduled', ?, ?, ?, 1)
                ");
                $insertStmt->execute([
                    $feed['user_id'], 
                    $targetInfo['id'], 
                    $socialPostContent,
                    $mediaUrlsJson,
                    $scheduledDate, 
                    $scheduledDate, 
                    $dbSourceLink
                ]);
                $addedPosts++;
            }
        }

        // Advance schedule time for the next item based on feed interval
        $currentPublishTime += ($intervalHours * 3600);
    }

    echo " - Added $addedPosts new RSS posts to the queue.\n";
    RssFeed::updateLastChecked($feed['id']);
    echo " - Feed ID {$feed['id']} successfully checked and updated.\n\n";
}

echo "[END] RSS Worker finished successfully at " . date('Y-m-d H:i:s') . "\n";
