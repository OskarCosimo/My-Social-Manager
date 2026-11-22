<?php
// cron/ai_campaign_worker.php
/**
 * AI Campaign Worker - Runs via CLI/Cronjob or Internal CronRunner
 * Processes active campaigns (prompt-only or custom link/topic lists with per-item instructions),
 * calls AiContentService, and schedules posts.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

// Allow execution either via CLI or via internal PHP cron runner
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CRON_EXEC')) {
    die("This script can only be run from the command line or internal runner.");
}

// Prevent timeout for batch processing
set_time_limit(0);

require_once BASE_PATH . 'app/Core/Config.php';
require_once BASE_PATH . 'app/Core/Database.php';
require_once BASE_PATH . 'app/Models/AiCampaign.php';
require_once BASE_PATH . 'app/Models/UsageManager.php';
require_once BASE_PATH . 'app/Models/SystemSetting.php';
require_once BASE_PATH . 'app/Services/AiContentService.php';

echo "[START] AI Campaign Worker started at " . date('Y-m-d H:i:s') . "\n";

$db = Database::getInstance();
$activeCampaigns = AiCampaign::getActiveCampaigns();

if (empty($activeCampaigns)) {
    echo "No active campaigns found. Exiting.\n";
    return;
}

foreach ($activeCampaigns as $campaign) {
    echo "Processing Campaign ID: {$campaign['id']} for User: {$campaign['user_id']}...\n";

    $userId              = (int)$campaign['user_id'];
    $settings            = $campaign['schedule_settings'];
    $targetTotalPosts    = max(1, (int)($settings['total_posts'] ?? 1));
    $postsPerDay         = max(1, (int)($settings['posts_per_day'] ?? 1));
    $startTime           = !empty($settings['start_time']) ? $settings['start_time'] : '09:00';
    $endTime             = !empty($settings['end_time']) ? $settings['end_time'] : '18:00';
    $isUniqueContent     = !empty($settings['unique_posts']);
    $targetMailingLists  = $settings['target_mailing_lists'] ?? [];

    // Source & AI detail configuration
    $sourceMode        = $settings['source_mode'] ?? 'prompt_only';
    $linksItems        = $settings['links_items'] ?? [];
    if (empty($linksItems) && !empty($settings['links_list'])) {
        $linksItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$settings['links_list']))));
    }
    $selectionMode     = $settings['selection_mode'] ?? 'sequential';
    $includeSourceLink = isset($settings['include_source_link']) ? !empty($settings['include_source_link']) : true;
    $detailLevel       = $settings['ai_detail_level'] ?? 'medium';
    $maxTokens         = (int)($settings['ai_max_tokens'] ?? 0);
    $maxChars          = max(0, (int)($settings['ai_max_chars'] ?? 0));

    // Retrieve user timezone with system fallback
    $userTzString = 'UTC';
    try {
        $tzStmt = $db->prepare("SELECT timezone FROM users WHERE id = ?");
        $tzStmt->execute([$userId]);
        $fetchedTz = $tzStmt->fetchColumn();
        if (!empty($fetchedTz)) {
            $userTzString = $fetchedTz;
        }
    } catch (Exception $e) {
        // Fallback if column is missing
    }

    if ($userTzString === 'UTC') {
        $sysTz = SystemSetting::get('timezone');
        if (!empty($sysTz)) {
            $userTzString = $sysTz;
        }
    }

    try {
        $userTz = new DateTimeZone($userTzString);
    } catch (Exception $e) {
        $userTz = new DateTimeZone('UTC');
    }

    // Convert start and end times to seconds for random calculation
    $startSec = strtotime("1970-01-01 " . $startTime);
    $endSec   = strtotime("1970-01-01 " . $endTime);
    if (!$startSec || !$endSec || $endSec <= $startSec) {
        $startSec = strtotime("1970-01-01 09:00:00");
        $endSec   = strtotime("1970-01-01 18:00:00");
    }

    // Check count of already scheduled/generated posts
    $stmt = $db->prepare("SELECT COUNT(DISTINCT scheduled_for) AS generated_count FROM posts WHERE campaign_id = :campaign_id");
    $stmt->execute(['campaign_id' => $campaign['id']]);
    $generatedCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['generated_count'] ?? 0);

    if ($generatedCount >= $targetTotalPosts) {
        echo "Campaign {$campaign['id']} reached its target ({$generatedCount}/{$targetTotalPosts}). Marking as completed.\n";
        AiCampaign::updateStatus($campaign['id'], 'completed');
        continue;
    }

    // Prepare source pool if using custom links/topics list
    $sourcePool = [];
    if ($sourceMode === 'links_list') {
        foreach ($linksItems as $entry) {
            if (is_array($entry)) {
                $target      = trim((string)($entry['target'] ?? ''));
                $instruction = trim((string)($entry['instruction'] ?? ''));
            } else {
                $target      = trim((string)$entry);
                $instruction = '';
            }

            if ($target === '') {
                continue;
            }

            if (filter_var($target, FILTER_VALIDATE_URL)) {
                $sourcePool[] = [
                    'title'       => '',
                    'link'        => $target,
                    'description' => '',
                    'instruction' => $instruction
                ];
            } else {
                $sourcePool[] = [
                    'title'       => $target,
                    'link'        => '',
                    'description' => '',
                    'instruction' => $instruction
                ];
            }
        }

        if (empty($sourcePool)) {
            echo " - Links/Topics list is empty for Campaign {$campaign['id']}. Skipping.\n";
            continue;
        }
    }

    // Track links already used by this campaign to avoid repetitions
    $usedLinks = [];
    if (!empty($sourcePool)) {
        $usedStmt = $db->prepare("SELECT DISTINCT source_link FROM posts WHERE campaign_id = ? AND source_link IS NOT NULL");
        $usedStmt->execute([$campaign['id']]);
        $usedLinks = $usedStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    $usedPoolKeys = [];

    // Prepare SQL insert matching the database schema
    $insertStmt = $db->prepare("
        INSERT INTO posts (user_id, campaign_id, social_account_id, content, media_url, status, scheduled_for, source_link) 
        VALUES (?, ?, ?, ?, NULL, 'scheduled', ?, ?)
    ");

    // Loop until all planned posts for this campaign are generated
    while ($generatedCount < $targetTotalPosts) {
        
        // Quota check
        if (!UsageManager::canGenerateAi($userId)) {
            echo "User {$userId} has exhausted AI credits. Pausing campaign {$campaign['id']}.\n";
            AiCampaign::updateStatus($campaign['id'], 'paused');
            break;
        }

        // Calculate schedule date: generate a random time between start_time and end_time
        $randomSec = rand($startSec, $endSec);
        $randomTimeStr = date('H:i:s', $randomSec);

        $daysToAdd = floor($generatedCount / $postsPerDay);
        $targetDayStr = date('Y-m-d', strtotime("+$daysToAdd days"));
        $localDateTimeStr = $targetDayStr . ' ' . $randomTimeStr;

        // Convert local time to UTC
        $dateObj = new DateTime($localDateTimeStr, $userTz);
        $nowUtc = new DateTime('now', new DateTimeZone('UTC'));

        // If today's random time has already passed in UTC, push to tomorrow
        $tempUtcCheck = clone $dateObj;
        $tempUtcCheck->setTimezone(new DateTimeZone('UTC'));
        if ($tempUtcCheck <= $nowUtc && $daysToAdd === 0) {
            $daysToAdd = 1;
            $targetDayStr = date('Y-m-d', strtotime("+$daysToAdd days"));
            $localDateTimeStr = $targetDayStr . ' ' . $randomTimeStr;
            $dateObj = new DateTime($localDateTimeStr, $userTz);
        }

        $dateObj->setTimezone(new DateTimeZone('UTC'));
        $scheduledDate = $dateObj->format('Y-m-d H:i:s');

        // Select item when using custom links/topics list
        $selectedItem = ['title' => '', 'link' => '', 'description' => '', 'instruction' => ''];
        if (!empty($sourcePool)) {
            $poolCount = count($sourcePool);

            if ($selectionMode === 'sequential') {
                $idx = $generatedCount % $poolCount;
                $selectedItem = $sourcePool[$idx];
            } else {
                // Random selection avoiding already used items first
                $availableIndices = [];
                foreach ($sourcePool as $idx => $candidate) {
                    $candidateLink = $candidate['link'] ?? '';
                    $truncatedLink = $candidateLink !== '' ? mb_substr($candidateLink, 0, 255) : '';
                    $alreadyUsedDb = ($truncatedLink !== '' && in_array($truncatedLink, $usedLinks, true));
                    $alreadyUsedRun = in_array($idx, $usedPoolKeys, true);

                    if (!$alreadyUsedDb && !$alreadyUsedRun) {
                        $availableIndices[] = $idx;
                    }
                }

                // Reset pool cycle if all items were already used once
                if (empty($availableIndices)) {
                    $usedPoolKeys = [];
                    $availableIndices = array_keys($sourcePool);
                }

                $pickedIdx = $availableIndices[array_rand($availableIndices)];
                $usedPoolKeys[] = $pickedIdx;
                $selectedItem = $sourcePool[$pickedIdx];
            }
        }

        // Build extra instructions combining item-specific note and uniqueness rules
        $extraInstructionsParts = [];

        if (!empty($selectedItem['instruction'])) {
            $extraInstructionsParts[] = "SPECIFIC INSTRUCTION FOR THIS ITEM:\n" . $selectedItem['instruction'];
        }

        if ($isUniqueContent) {
            $prevStmt = $db->prepare("
                SELECT content 
                FROM posts 
                WHERE campaign_id = :campaign_id 
                ORDER BY id DESC 
                LIMIT 10
            ");
            $prevStmt->execute(['campaign_id' => $campaign['id']]);
            $rawPrevious = $prevStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $previousPosts = array_slice(array_unique($rawPrevious), 0, 5);

            $postNumber = $generatedCount + 1;
            $uniqueText = "This is post #{$postNumber} of a {$targetTotalPosts}-post series. Make this post completely UNIQUE and different in style, angle, and hooks.";
            
            if (!empty($previousPosts)) {
                $uniqueText .= "\nDo NOT repeat or copy any of these previous posts from this same campaign:\n";
                foreach ($previousPosts as $prev) {
                    $snippet = trim(preg_replace('/\s+/', ' ', substr($prev, 0, 140)));
                    $uniqueText .= "- " . $snippet . "...\n";
                }
            }
            $extraInstructionsParts[] = $uniqueText;
        }

        $combinedExtraInstruction = implode("\n\n", $extraInstructionsParts);
        $itemLink = trim((string)($selectedItem['link'] ?? ''));
        $shouldAppendLink = ($includeSourceLink && $itemLink !== '');

        $aiResult = AiContentService::generateCaption([
            'user_prompt'       => $campaign['prompt'],
            'item_title'        => $selectedItem['title'] ?? '',
            'item_description'  => $selectedItem['description'] ?? '',
            'item_link'         => $itemLink,
            'detail_level'      => $detailLevel,
            'max_tokens'        => $maxTokens,
            'max_chars'         => $maxChars,
            'include_link'      => $shouldAppendLink,
            'temperature'       => $isUniqueContent ? 0.85 : 0.7,
            'extra_instruction' => $combinedExtraInstruction,
            'fetch_web_context' => ($itemLink !== '')
        ]);

        if (!$aiResult['success']) {
            echo "AI generation failed for campaign {$campaign['id']}: " . ($aiResult['error'] ?? 'Unknown error') . "\n";
            break;
        }

        $finalSocialText = $aiResult['text'] ?? '';
        $captionOnly     = $aiResult['caption'] ?? $finalSocialText;

        if (empty($finalSocialText)) {
            echo "AI returned empty text for campaign {$campaign['id']}. Stopping batch.\n";
            break;
        }

        // Safely bound source_link to 255 chars for DB column compatibility
        $dbSourceLink = $itemLink !== '' ? mb_substr($itemLink, 0, 255) : null;
        if ($dbSourceLink !== null) {
            $usedLinks[] = $dbSourceLink;
        }

        // Insert into database for each selected social account
        if (!empty($campaign['selected_accounts'])) {
            foreach ($campaign['selected_accounts'] as $accountId) {
                $insertStmt->execute([
                    $userId,
                    $campaign['id'],
                    (int)$accountId,
                    $finalSocialText,
                    $scheduledDate,
                    $dbSourceLink
                ]);
            }
        }

        // Insert into database for each selected mailing list
        if (!empty($targetMailingLists)) {
            $mlContent = $captionOnly;
            if (!empty($selectedItem['title']) || $itemLink !== '') {
                $mlContent = json_encode([
                    'title'       => !empty($selectedItem['title']) ? $selectedItem['title'] : 'Update',
                    'description' => $captionOnly,
                    'link'        => $itemLink
                ], JSON_UNESCAPED_UNICODE);
            }

            foreach ($targetMailingLists as $mlId) {
                $insertStmt->execute([
                    $userId,
                    $campaign['id'],
                    null,
                    $mlContent,
                    $scheduledDate,
                    'mailing_list:' . (int)$mlId
                ]);
            }
        }

        // Increment user AI credits usage
        UsageManager::incrementAiUsage($userId, 1);
        $generatedCount++;

        echo "Generated post {$generatedCount}/{$targetTotalPosts} for Campaign {$campaign['id']} scheduled for {$scheduledDate} UTC (Local: {$localDateTimeStr} {$userTzString}).\n";
    }

    // Mark completed if total reached
    if ($generatedCount >= $targetTotalPosts) {
        AiCampaign::updateStatus($campaign['id'], 'completed');
        echo "Campaign {$campaign['id']} completed successfully.\n";
    }
}

echo "[END] AI Campaign Worker finished.\n";
