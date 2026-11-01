// cron/publisher_worker.php
<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

require_once BASE_PATH . 'app/Core/Config.php';
require_once BASE_PATH . 'app/Core/Database.php';
require_once BASE_PATH . 'app/Core/SocialPluginInterface.php';
require_once BASE_PATH . 'app/Models/UsageManager.php';
require_once BASE_PATH . 'app/Models/MailingList.php';
require_once BASE_PATH . 'app/Core/Mailer.php';
require_once BASE_PATH . 'app/Services/MediaFallbackService.php';

// Allow execution either via CLI or via internal PHP cron runner
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CRON_EXEC')) {
    die("This script can only be run from the command line or internal runner.");
}

date_default_timezone_set('UTC');

echo "[START] Publisher Worker started at " . date('Y-m-d H:i:s') . "\n";

$db = Database::getInstance();

// Use LEFT JOIN to support posts with NULL social_account_id (Mailing List campaigns)
$stmt = $db->prepare("
    SELECT p.*, s.plugin_id AS network_type, s.access_token, s.refresh_token, s.platform_user_id, s.platform_name
    FROM posts p
    LEFT JOIN social_accounts s ON p.social_account_id = s.id
    WHERE p.status = 'scheduled' 
      AND p.scheduled_for <= NOW()
    ORDER BY p.scheduled_for ASC
    LIMIT 50
");
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($posts)) {
    echo "No scheduled posts to publish.\n";
    return;
}

echo "Found " . count($posts) . " posts to process.\n";

foreach ($posts as $post) {

    // 1. Handle Mailing List Post
    if (empty($post['social_account_id']) && !empty($post['source_link']) && str_starts_with($post['source_link'], 'mailing_list:')) {
        $listId = (int)str_replace('mailing_list:', '', $post['source_link']);
        echo "Processing post ID [{$post['id']}] for Mailing List [{$listId}]\n";

        try {
            $subscribers = MailingList::getActiveSubscribers($listId);
            $listInfo = MailingList::getById($listId, (int)$post['user_id']);

            if (!empty($subscribers)) {
                if (!UsageManager::canSendEmails((int)$post['user_id'], count($subscribers))) {
                    $failStmt = $db->prepare("UPDATE posts SET status = 'failed', error_log = 'Email quota exceeded' WHERE id = ?");
                    $failStmt->execute([$post['id']]);
                    echo " - Error: Monthly email limit exceeded for user ID {$post['user_id']}.\n";
                    continue;
                }

                // Check if content is structured JSON from RSS worker
                $postData = json_decode($post['content'], true);
                if (is_array($postData) && isset($postData['title'])) {
                    $itemTitle = $postData['title'];
                    $itemDesc = $postData['description'] ?? '';
                    $itemLink = $postData['link'] ?? '';
                } else {
                    $itemTitle = 'Update';
                    $itemDesc = $post['content'];
                    $itemLink = '';
                }

                $listName = $listInfo ? $listInfo['name'] : 'Mailing List';
                $subject = $listName . ': ' . $itemTitle;

                // Build rich HTML email body
                $htmlBody = "<div style='font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e8ed; border-radius: 8px;'>";
                $htmlBody .= "<h2 style='color: #111; margin-top: 0;'>" . htmlspecialchars($itemTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</h2>";

                // Enclosure or media image attachment
                $mediaUrls = !empty($post['media_urls']) ? json_decode($post['media_urls'], true) : [];
                if (!empty($mediaUrls) && is_array($mediaUrls) && !empty($mediaUrls[0])) {
                    $htmlBody .= "<p style='margin: 15px 0;'><img src='" . htmlspecialchars($mediaUrls[0], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "' style='max-width: 100%; height: auto; border-radius: 6px; display: block;' /></p>";
                }

                // Article description
                if (!empty($itemDesc)) {
                    $htmlBody .= "<div style='margin: 15px 0; font-size: 15px; color: #444;'>" . nl2br(htmlspecialchars($itemDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "</div>";
                }

                // Call to action button / direct link
                if (!empty($itemLink)) {
                    $htmlBody .= "<p style='margin-top: 25px;'><a href='" . htmlspecialchars($itemLink, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "' target='_blank' rel='noopener noreferrer' style='background-color: #0d6efd; color: #ffffff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>Read Article</a></p>";
                }

                $htmlBody .= "</div>";

                $sentCount = 0;
                foreach ($subscribers as $subscriberEmail) {
                    if (Mailer::send($subscriberEmail, $subject, $htmlBody)) {
                        $sentCount++;
                    }
                }

                if ($sentCount > 0) {
                    UsageManager::incrementEmailUsage((int)$post['user_id'], $sentCount);
                }
            }

            $doneStmt = $db->prepare("UPDATE posts SET status = 'published', published_at = NOW(), error_log = NULL WHERE id = ?");
            $doneStmt->execute([$post['id']]);

            // Track RSS quota usage
            if (!empty($post['is_rss']) && (int)$post['is_rss'] === 1) {
                UsageManager::incrementRssPostUsage($post['user_id']);
            }

            echo " - Success: Post ID {$post['id']} sent to Mailing List #{$listId} successfully.\n";

        } catch (Exception $e) {
            $failStmt = $db->prepare("UPDATE posts SET status = 'failed', error_log = ? WHERE id = ?");
            $failStmt->execute([$e->getMessage(), $post['id']]);
            echo " - Exception: " . $e->getMessage() . "\n";
        }

        continue;
    }

    // 2. Handle Social Account Post
    echo "Processing post ID [{$post['id']}] for network plugin [{$post['network_type']}]\n";

    try {
        $rawNetwork = strtolower(trim($post['network_type'] ?? ''));
        if (empty($rawNetwork)) {
            throw new Exception("Missing network plugin type for post ID {$post['id']}");
        }

        $pascalNetwork = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $rawNetwork)));
        $pluginClassName = $pascalNetwork . 'Plugin';
        
        $possiblePaths = [
            BASE_PATH . "plugins/{$pascalNetwork}/{$pluginClassName}.php",
            BASE_PATH . "plugins/" . ucfirst($rawNetwork) . "/{$pluginClassName}.php",
            BASE_PATH . "plugins/{$rawNetwork}/{$pluginClassName}.php",
            BASE_PATH . "plugins/" . strtolower($pascalNetwork) . "/{$pluginClassName}.php"
        ];

        $pluginFilePath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $pluginFilePath = $path;
                break;
            }
        }

        $publishedSuccessfully = false;
        $errorResponse = '';

        if ($pluginFilePath) {
            require_once $pluginFilePath;

            if (class_exists($pluginClassName)) {
                $pluginInstance = new $pluginClassName();

                $mediaUrlsArray = [];
                if (!empty($post['media_urls'])) {
                    $decoded = json_decode($post['media_urls'], true);
                    if (is_array($decoded)) {
                        $mediaUrlsArray = $decoded;
                    }
                }
                if (empty($mediaUrlsArray) && !empty($post['media_url'])) {
                    $mediaUrlsArray = [$post['media_url']];
                }

                // Automatic fallback for image-mandated networks (e.g. Instagram, Pinterest)
                if (empty($mediaUrlsArray) && MediaFallbackService::isMediaRequired($rawNetwork)) {
                    $fallbackMedia = MediaFallbackService::resolvePostMedia($post, $rawNetwork);
                    if (!empty($fallbackMedia)) {
                        $mediaUrlsArray = $fallbackMedia;
                        // Persist fallback image into the posts record
                        $updStmt = $db->prepare("UPDATE posts SET media_urls = ? WHERE id = ?");
                        $updStmt->execute([json_encode($mediaUrlsArray), $post['id']]);
                        echo " - Applied fallback media attachment for post #{$post['id']}: {$mediaUrlsArray[0]}\n";
                    }
                }

                $cleanContent = $post['content'];
                $sourceLink = $post['source_link'] ?? null;
                $result = null;

                if (method_exists($pluginInstance, 'publishPostWithLink')) {
                    $result = $pluginInstance->publishPostWithLink(
                        $cleanContent, 
                        $mediaUrlsArray, 
                        $post['access_token'], 
                        $sourceLink
                    );
                } elseif (method_exists($pluginInstance, 'publishPost')) {
                    $result = $pluginInstance->publishPost(
                        $cleanContent, 
                        $mediaUrlsArray, 
                        $post['access_token'],
                        $sourceLink
                    );
                } else {
                    $publishMethod = null;
                    foreach (['publish', 'send', 'post', 'share', 'submit'] as $method) {
                        if (method_exists($pluginInstance, $method)) {
                            $publishMethod = $method;
                            break;
                        }
                    }

                    if ($publishMethod) {
                        $result = $pluginInstance->$publishMethod($post);
                    } else {
                        throw new Exception("No valid publish method found in class {$pluginClassName}");
                    }
                }

                if (is_array($result)) {
                    if (isset($result['success']) && $result['success'] === true) {
                        $publishedSuccessfully = true;
                    } else {
                        $errorResponse = $result['error'] ?? 'Unknown error returned by plugin method.';
                    }
                } elseif ($result === true) {
                    $publishedSuccessfully = true;
                } else {
                    $errorResponse = 'Plugin method returned unsuccessful response.';
                }
            } else {
                throw new Exception("Plugin class {$pluginClassName} not found in file {$pluginFilePath}");
            }
        } else {
            throw new Exception("Plugin file not found for network: {$rawNetwork}");
        }

        if ($publishedSuccessfully) {
            $doneStmt = $db->prepare("UPDATE posts SET status = 'published', published_at = NOW(), error_log = NULL WHERE id = ?");
            $doneStmt->execute([$post['id']]);

            // Track usage quota based on origin
            if (!empty($post['is_channel_automation']) && (int)$post['is_channel_automation'] === 1) {
                UsageManager::incrementChannelPostUsage($post['user_id']);
            } elseif (!empty($post['is_rss']) && (int)$post['is_rss'] === 1) {
                UsageManager::incrementRssPostUsage($post['user_id']);
            }

            echo " - Success: Post ID {$post['id']} published successfully.\n";
        } else {
            $failStmt = $db->prepare("UPDATE posts SET status = 'failed', error_log = ? WHERE id = ?");
            $failStmt->execute([$errorResponse, $post['id']]);
            echo " - Error: {$errorResponse}\n";
        }

    } catch (Exception $e) {
        $failStmt = $db->prepare("UPDATE posts SET status = 'failed', error_log = ? WHERE id = ?");
        $failStmt->execute([$e->getMessage(), $post['id']]);
        echo " - Exception: " . $e->getMessage() . "\n";
    }
}

echo "[END] Publisher Worker finished successfully at " . date('Y-m-d H:i:s') . "\n";
