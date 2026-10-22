<?php
// app/Controllers/AiController.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Models/UsageManager.php'; // Required for quota enforcement

class AiController {

    /**
     * Endpoint to handle AI generation requests via AJAX/Fetch
     */
    public function generate(): void {
        header('Content-Type: application/json');

        // Security check
        if (!isset($_SESSION['user_id'])) {
            // Return standard JSON error format expected by frontend JS
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];

        // --- USAGE ENFORCEMENT ---
        // Block the request if the user is out of AI credits
        if (!UsageManager::canGenerateAi($userId)) {
            echo json_encode(['success' => false, 'error' => 'Monthly AI credit limit reached. Please upgrade your plan.']);
            exit;
        }

        // Read JSON payload from Javascript fetch request
        $input = json_decode(file_get_contents('php://input'), true);
        $prompt = trim($input['prompt'] ?? '');

        if (empty($prompt)) {
            echo json_encode(['success' => false, 'error' => 'Prompt is required.']);
            exit;
        }

        $systemPrompt = "You are a professional social media manager. Write an engaging post based on the user's prompt. IMPORTANT: Only return the final post content. Do not include conversational text like 'Here is the post', quotes, or explanations.";
        $fullPrompt = $systemPrompt . "\n\nUser Request: " . $prompt;

        try {
            if (Config::AI_PROVIDER === 'openai-chat') {
                $generatedText = $this->callOpenAiChat($fullPrompt);
            } elseif (Config::AI_PROVIDER === 'ollama') {
                $generatedText = $this->callOllama($fullPrompt);
            } elseif (Config::AI_PROVIDER === 'gemini') {
                $generatedText = $this->callGemini($fullPrompt);
            } else {
                throw new Exception("Invalid AI provider configured.");
            }

            if (empty($generatedText)) {
                throw new Exception("The AI returned an empty response.");
            }

            // Increment usage after a successful generation
            UsageManager::incrementAiUsage($userId, 1);

            echo json_encode(['success' => true, 'text' => nl2br(htmlspecialchars($generatedText))]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        
        exit;
    }

    /**
     * Call OpenAI-Compatible Chat Completions API (MLX-LM, LocalAI, vLLM, OpenAI)
     */
    private function callOpenAiChat(string $prompt): string {
        $endpoint = Config::OPENAI_ENDPOINT;
        $model    = Config::OPENAI_MODEL;

        if (empty($endpoint) || empty($model)) {
            throw new Exception("OpenAI Chat provider is not properly configured: missing endpoint or model.");
        }

        $payload = json_encode([
            'model'       => $model,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $prompt
                ]
            ],
            'stream'      => false,
            'temperature' => 0.7,
            'max_tokens'  => 1024
        ]);

        $headers = ['Content-Type: application/json'];
        if (!empty(Config::OPENAI_API_KEY)) {
            $headers[] = 'Authorization: Bearer ' . Config::OPENAI_API_KEY;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $responseRaw = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error       = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("OpenAI Chat connection error: " . $error);
        }

        if ($httpCode >= 400 || !$responseRaw) {
            throw new Exception("OpenAI Chat API returned HTTP Code {$httpCode}. Verify your endpoint and model name.");
        }

        $result = json_decode($responseRaw, true);
        return trim($result['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Call Local Ollama API
     */
    private function callOllama(string $prompt): string {
        $payload = json_encode([
            'model'  => Config::OLLAMA_MODEL,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'num_ctx' => 2048
            ]
        ]);

        $ch = curl_init(Config::OLLAMA_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Ollama connection error: " . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception("Ollama API returned HTTP Code {$httpCode}. Verify your model name exists.");
        }

        $result = json_decode($responseRaw, true);
        return trim($result['response'] ?? '');
    }

    /**
     * Call Google Gemini API (Placeholder implementation)
     */
    private function callGemini(string $prompt): string {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . Config::GEMINI_API_KEY;
        
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Gemini connection error: " . $error);
        }

        if ($httpCode >= 400) {
            $errData = json_decode($responseRaw, true);
            $errMsg = $errData['error']['message'] ?? 'Unknown API Error';
            throw new Exception("Gemini API Error ({$httpCode}): " . $errMsg);
        }

        $result = json_decode($responseRaw, true);
        return trim($result['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }
}