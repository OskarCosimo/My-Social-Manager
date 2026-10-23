<?php
// cron/ai_campaign_worker.php
/**
 * AI Campaign Worker - Runs via CLI/Cronjob
 * Processes active campaigns, calls MLX-LM/Ollama/Gemini, and schedules posts.
 */

// Prevent timeout for long-running generation batches
set_time_limit(0);

require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/AiCampaign.php';
require_once __DIR__ . '/../app/Models/UsageManager.php';

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
    $startTime = $settings['start_time'] ?? '09:00';
    $isUniqueContent = !empty($settings['unique_posts']);

    // Check how many posts have already been generated for this campaign
    $stmt = $db->prepare("SELECT COUNT(DISTINCT scheduled_for) as generated_count FROM posts WHERE campaign_id = :campaign_id");
    $stmt->execute(['campaign_id' => $campaign['id']]);
    $generatedCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['generated_count'] ?? 0);

    if ($generatedCount >= $targetTotalPosts) {
        echo "Campaign {$campaign['id']} reached its target ({$generatedCount}/{$targetTotalPosts}). Marking as completed.\n";
        AiCampaign::updateStatus($campaign['id'], 'completed');
        continue;
    }

    // Prepare the insert statement matching the exact table schema
    $insertStmt = $db->prepare("
        INSERT INTO posts (user_id, campaign_id, social_account_id, content, media_url, status, scheduled_for) 
        VALUES (?, ?, ?, ?, NULL, 'scheduled', ?)
    ");

    // Loop through and generate all remaining posts for this campaign
    while ($generatedCount < $targetTotalPosts) {
        
        // Check if user has AI credits left
        if (!UsageManager::canGenerateAi($userId)) {
            echo "User {$userId} has exhausted AI credits. Pausing campaign {$campaign['id']}.\n";
            AiCampaign::updateStatus($campaign['id'], 'paused');
            break;
        }

        // Calculate schedule date for current post index
        $daysToAdd = floor($generatedCount / $postsPerDay);
        $scheduledDate = date('Y-m-d H:i:s', strtotime("+$daysToAdd days $startTime"));

        // Build unique prompt instructions if enabled
        $uniqueInstructions = "";
        if ($isUniqueContent) {
            $prevStmt = $db->prepare("
                SELECT DISTINCT content 
                FROM posts 
                WHERE campaign_id = :campaign_id 
                ORDER BY id DESC 
                LIMIT 5
            ");
            $prevStmt->execute(['campaign_id' => $campaign['id']]);
            $previousPosts = $prevStmt->fetchAll(PDO::FETCH_COLUMN);

            $postNumber = $generatedCount + 1;
            $uniqueInstructions .= "\n\nThis is post #{$postNumber} of a {$targetTotalPosts}-post series.";
            $uniqueInstructions .= " IMPORTANT: Write a completely UNIQUE and fresh post with a different angle, hook, and phrasing.";
            
            if (!empty($previousPosts)) {
                $uniqueInstructions .= " Do NOT repeat or closely mirror the following recent posts from this campaign:\n";
                foreach ($previousPosts as $prev) {
                    $snippet = trim(preg_replace('/\s+/', ' ', substr($prev, 0, 140)));
                    $uniqueInstructions .= "- " . $snippet . "...\n";
                }
            }
        }

        $aiPrompt = $campaign['prompt'] . $uniqueInstructions . "\n\nIMPORTANT: Write ONLY the social media post text. Do not include introductory notes, explanations, or enclosing quotes.";
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

        if (empty($generatedText)) {
            echo "AI returned empty text for campaign {$campaign['id']}. Stopping batch.\n";
            break;
        }

        // Insert post for each selected social account
        foreach ($campaign['selected_accounts'] as $accountId) {
            $insertStmt->execute([
                $userId,
                $campaign['id'],
                (int)$accountId,
                $generatedText,
                $scheduledDate
            ]);
        }

        // Increment user AI usage
        UsageManager::incrementAiUsage($userId, 1);
        $generatedCount++;

        echo "Generated post {$generatedCount}/{$targetTotalPosts} for Campaign {$campaign['id']} scheduled at {$scheduledDate}.\n";
    }

    // If target reached, mark campaign as completed
    if ($generatedCount >= $targetTotalPosts) {
        AiCampaign::updateStatus($campaign['id'], 'completed');
        echo "Campaign {$campaign['id']} completed successfully.\n";
    }
}

echo "[END] AI Campaign Worker finished.\n";
