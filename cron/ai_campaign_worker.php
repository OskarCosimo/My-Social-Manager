<?php
// cron/ai_campaign_worker.php
/**
 * AI Campaign Worker - Runs via CLI/Cronjob
 * Processes active campaigns, calls MLX-LM/Ollama/Gemini, and schedules posts.
 */

// Prevent timeout for batch processing
set_time_limit(0);

require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/AiCampaign.php';
require_once __DIR__ . '/../app/Models/UsageManager.php';
require_once __DIR__ . '/../app/Models/SystemSetting.php';

/**
 * Sanitize AI generated content for social media:
 * Removes markdown bold/italics, vision model tags (like <image|>), and header syntax.
 */
function cleanAiSocialPost(string $text): string {
    // 1. Strip vision and multimodal tokens (e.g. <image|>, <image>, <|...|>)
    $text = preg_replace('/<image[^>]*>/i', '', $text);
    $text = preg_replace('/<\|[^>]*\|>/i', '', $text);
    $text = preg_replace('/<[^>]+>/', '', $text);

    // 2. Remove markdown asterisks (**bold** -> bold, *italic* -> italic)
    $text = preg_replace('/\*\*(.*?)\*\*/s', '$1', $text);
    $text = preg_replace('/\*([^\*]+)\*/s', '$1', $text);
    $text = str_replace(['**', '***'], '', $text);

    // 3. Remove markdown headers (# Title -> Title)
    $text = preg_replace('/^#+\s+/m', '', $text);

    // 4. Strip leading/trailing quotes and extra whitespace
    $text = trim($text, " \t\n\r\0\x0B\"'");
    $text = preg_replace('/[ \t]+/', ' ', $text);

    return trim($text);
}

echo "[START] AI Campaign Worker started at " . date('Y-m-d H:i:s') . "\n";

$db = Database::getInstance();
$activeCampaigns = AiCampaign::getActiveCampaigns();

if (empty($activeCampaigns)) {
    echo "No active campaigns found. Exiting.\n";
    exit;
}

foreach ($activeCampaigns as $campaign) {
    echo "Processing Campaign ID: {$campaign['id']} for User: {$campaign['user_id']}...\n";

    $userId = (int)$campaign['user_id'];
    $settings = $campaign['schedule_settings'];
    $targetTotalPosts = max(1, (int)($settings['total_posts'] ?? 1));
    $postsPerDay = max(1, (int)($settings['posts_per_day'] ?? 1));
    $startTime = !empty($settings['start_time']) ? $settings['start_time'] : '09:00';
    $endTime = !empty($settings['end_time']) ? $settings['end_time'] : '18:00';
    $isUniqueContent = !empty($settings['unique_posts']);

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

    // Check count of already scheduled posts
    $stmt = $db->prepare("SELECT COUNT(DISTINCT scheduled_for) as generated_count FROM posts WHERE campaign_id = :campaign_id");
    $stmt->execute(['campaign_id' => $campaign['id']]);
    $generatedCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['generated_count'] ?? 0);

    if ($generatedCount >= $targetTotalPosts) {
        echo "Campaign {$campaign['id']} reached its target ({$generatedCount}/{$targetTotalPosts}). Marking as completed.\n";
        AiCampaign::updateStatus($campaign['id'], 'completed');
        continue;
    }

    // Prepare SQL insert matching the database schema
    $insertStmt = $db->prepare("
        INSERT INTO posts (user_id, campaign_id, social_account_id, content, media_url, status, scheduled_for) 
        VALUES (?, ?, ?, ?, NULL, 'scheduled', ?)
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

        // Build uniqueness prompt if enabled
        $uniqueInstructions = "";
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
            $uniqueInstructions .= "\n\nThis is post #{$postNumber} of a {$targetTotalPosts}-post series.";
            $uniqueInstructions .= " Make this post completely UNIQUE and different in style, angle, and hooks.";
            
            if (!empty($previousPosts)) {
                $uniqueInstructions .= "\nDo NOT repeat or copy any of these previous posts from this same campaign:\n";
                foreach ($previousPosts as $prev) {
                    $snippet = trim(preg_replace('/\s+/', ' ', substr($prev, 0, 140)));
                    $uniqueInstructions .= "- " . $snippet . "...\n";
                }
            }
        }

        // Enforce strict social formatting rules (no markdown, no image tokens)
        $aiPrompt = $campaign['prompt'] . $uniqueInstructions . "\n\n"
            . "CRITICAL RULES FOR SOCIAL POST:" . "\n"
            . "- Output ONLY the social media post text." . "\n"
            . "- Use PLAIN TEXT only. Do NOT use markdown syntax (do NOT use bold asterisks **text**, headers #, or bullet points)." . "\n"
            . "- Do NOT include image tags, placeholders like <image>, <image|>, or special tokens." . "\n"
            . "- Emojis and hashtags are allowed.";

        $generatedText = '';
        $temperature = $isUniqueContent ? 0.85 : 0.7;

        // Call configured AI provider
        if (Config::AI_PROVIDER === 'openai-chat') {
            $payload = json_encode([
                'model'       => Config::OPENAI_MODEL,
                'messages'    => [
                    ['role' => 'user', 'content' => $aiPrompt]
                ],
                'stream'      => false,
                'temperature' => $temperature,
                'max_tokens'  => 1024
            ]);

            $headers = ['Content-Type: application/json'];
            if (!empty(Config::OPENAI_API_KEY)) {
                $headers[] = 'Authorization: Bearer ' . Config::OPENAI_API_KEY;
            }

            $ch = curl_init(Config::OPENAI_ENDPOINT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $responseData = json_decode($response, true);
                $generatedText = trim($responseData['choices'][0]['message']['content'] ?? '');
            } else {
                echo "OpenAI Chat API failed for campaign {$campaign['id']}. HTTP: $httpCode\n";
                break;
            }

        } elseif (Config::AI_PROVIDER === 'ollama') {
            $payload = json_encode([
                'model'   => Config::OLLAMA_MODEL,
                'prompt'  => $aiPrompt,
                'stream'  => false,
                'options' => [
                    'num_ctx'     => 2048,
                    'temperature' => $temperature
                ]
            ]);

            $ch = curl_init(Config::OLLAMA_ENDPOINT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $responseData = json_decode($response, true);
                $generatedText = trim($responseData['response'] ?? '');
            } else {
                echo "Ollama API failed for campaign {$campaign['id']}. HTTP: $httpCode\n";
                break;
            }

        } elseif (Config::AI_PROVIDER === 'gemini') {
            $geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . Config::GEMINI_API_KEY;
            
            $payload = json_encode([
                'contents' => [
                    ['parts' => [['text' => $aiPrompt]]]
                ],
                'generationConfig' => [
                    'temperature' => $temperature
                ]
            ]);

            $ch = curl_init($geminiEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $responseData = json_decode($response, true);
                $generatedText = trim($responseData['candidates'][0]['content']['parts'][0]['text'] ?? '');
            } else {
                echo "Gemini API failed for campaign {$campaign['id']}. HTTP: $httpCode\n";
                break;
            }
        }

        // Sanitize and clean the post text
        $cleanedPost = cleanAiSocialPost($generatedText);

        if (empty($cleanedPost)) {
            echo "AI returned empty text for campaign {$campaign['id']}. Stopping batch.\n";
            break;
        }

        // Insert into database for each selected social account
        foreach ($campaign['selected_accounts'] as $accountId) {
            $insertStmt->execute([
                $userId,
                $campaign['id'],
                (int)$accountId,
                $cleanedPost,
                $scheduledDate
            ]);
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
