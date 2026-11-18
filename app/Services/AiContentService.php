<?php
// app/Services/AiContentService.php

require_once __DIR__ . '/../Core/Config.php';

class AiContentService {

    /**
     * Return default token budgets and instructions for each reasoning/detail level.
     */
    public static function getDetailLevelConfig(string $level): array {
        switch ($level) {
            case 'light':
                return [
                    'tokens'          => 150,
                    'suggested_chars' => 280,
                    'instruction'     => 'Keep the post concise, punchy, and brief (1-3 short sentences). Focus only on the main hook and core takeaway.'
                ];
            case 'detailed':
                return [
                    'tokens'          => 750,
                    'suggested_chars' => 1500,
                    'instruction'     => 'Write a rich, detailed, and insightful post. Explain the topic thoroughly, highlight key details, context, and target audience recommendations where relevant, while remaining engaging for social media.'
                ];
            case 'medium':
            default:
                return [
                    'tokens'          => 350,
                    'suggested_chars' => 700,
                    'instruction'     => 'Write a balanced, engaging post with a clear hook, a helpful explanation of the link/topic, and a brief call to action.'
                ];
        }
    }

    /**
     * Sanitize AI-generated content for plain-text social media posting.
     */
    public static function cleanAiSocialPost(string $text): string {
        // Strip vision and multimodal tokens (e.g. <image|>, <image>, <|...|>)
        $text = preg_replace('/<image[^>]*>/i', '', $text);
        $text = preg_replace('/<\|[^>]*\|>/i', '', $text);
        $text = preg_replace('/<[^>]+>/', '', $text);

        // Remove markdown asterisks (**bold** -> bold, *italic* -> italic)
        $text = preg_replace('/\*\*(.*?)\*\*/s', '$1', $text);
        $text = preg_replace('/\*([^\*]+)\*/s', '$1', $text);
        $text = str_replace(['**', '***'], '', $text);

        // Remove markdown headers (# Title -> Title)
        $text = preg_replace('/^#+\s+/m', '', $text);

        // Strip leading/trailing quotes and normalize horizontal whitespace
        $text = trim($text, " \t\n\r\0\x0B\"'");
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Fetch title, meta description, and readable main text from an external URL
     * so the AI can accurately explain the target page content.
     */
    public static function fetchPageContext(string $url, int $maxBodyChars = 2500): array {
        $context = [
            'title'       => '',
            'description' => '',
            'excerpt'     => '',
            'fetched'     => false
        ];

        $url = trim($url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $context;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $context;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MySocialManager/1.0; +https://socialmanager.myetv.tv)');
        // Limit download size to 256KB to protect memory and speed
        curl_setopt($ch, CURLOPT_RANGE, '0-262143');
        $html = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!$html || ($httpCode >= 400 && $httpCode !== 206)) {
            return $context;
        }

        if (!empty($contentType) && !str_contains(strtolower($contentType), 'text/html') && !str_contains(strtolower($contentType), 'application/xhtml')) {
            return $context;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);

        // Extract title
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle && $ogTitle->length > 0) {
            $context['title'] = trim((string)$ogTitle->item(0)->nodeValue);
        } else {
            $titleNodes = $xpath->query('//title');
            if ($titleNodes && $titleNodes->length > 0) {
                $context['title'] = trim((string)$titleNodes->item(0)->nodeValue);
            }
        }

        // Extract description
        $ogDesc = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogDesc && $ogDesc->length > 0) {
            $context['description'] = trim((string)$ogDesc->item(0)->nodeValue);
        } else {
            $metaDesc = $xpath->query('//meta[@name="description"]/@content');
            if ($metaDesc && $metaDesc->length > 0) {
                $context['description'] = trim((string)$metaDesc->item(0)->nodeValue);
            }
        }

        // Remove non-content elements before extracting readable text
        $removeNodes = $xpath->query('//script | //style | //noscript | //nav | //footer | //header | //svg | //iframe');
        if ($removeNodes) {
            foreach ($removeNodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Prefer <main> or <article> when available
        $mainNodes = $xpath->query('//main | //article');
        if ($mainNodes && $mainNodes->length > 0) {
            $rawText = $mainNodes->item(0)->textContent;
        } else {
            $bodyNodes = $xpath->query('//body');
            $rawText = ($bodyNodes && $bodyNodes->length > 0) ? $bodyNodes->item(0)->textContent : '';
        }

        $cleanText = html_entity_decode((string)$rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanText = preg_replace('/\s+/u', ' ', trim($cleanText));

        if (!empty($cleanText)) {
            $context['excerpt'] = mb_substr($cleanText, 0, $maxBodyChars, 'UTF-8');
        }

        $context['fetched'] = true;
        return $context;
    }

    /**
     * Fetch and parse an external RSS or Atom feed into normalized items.
     */
    public static function fetchRssItems(string $feedUrl, int $limit = 100): array {
        $feedUrl = trim($feedUrl);
        if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            return [];
        }

        $ch = curl_init($feedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MySocialManager/1.0; +https://socialmanager.myetv.tv)');
        $rssData = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$rssData || $httpCode >= 400) {
            return [];
        }

        $xml = @simplexml_load_string($rssData);
        if (!$xml) {
            return [];
        }

        $rawItems = [];
        if (isset($xml->channel->item)) {
            $rawItems = $xml->channel->item;
        } elseif (isset($xml->entry)) {
            $rawItems = $xml->entry;
        }

        if (empty($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $item) {
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

            $description = '';
            if (isset($item->description)) {
                $description = (string)$item->description;
            } elseif (isset($item->summary)) {
                $description = (string)$item->summary;
            }

            if (empty($description)) {
                $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
                if (isset($contentNs->encoded)) {
                    $description = (string)$contentNs->encoded;
                }
            }

            $cleanDesc = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanDesc = preg_replace('/\s+/u', ' ', trim($cleanDesc));

            if (!empty($title) && !empty($link)) {
                $items[] = [
                    'title'       => $title,
                    'link'        => $link,
                    'description' => $cleanDesc
                ];
            }

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * Generate a social post caption using the configured AI provider,
     * optionally analyzing an external link/item and enforcing token & character limits.
     */
    public static function generateCaption(array $params): array {
        $userPrompt       = trim((string)($params['user_prompt'] ?? ''));
        $itemTitle        = trim((string)($params['item_title'] ?? ''));
        $itemDescription  = trim((string)($params['item_description'] ?? ''));
        $itemLink         = trim((string)($params['item_link'] ?? ''));
        $detailLevel      = (string)($params['detail_level'] ?? 'medium');
        $maxTokens        = (int)($params['max_tokens'] ?? 0);
        $maxChars         = max(0, (int)($params['max_chars'] ?? 0));
        $includeLink      = isset($params['include_link']) ? (bool)$params['include_link'] : true;
        $temperature      = (float)($params['temperature'] ?? 0.75);
        $extraInstruction = trim((string)($params['extra_instruction'] ?? ''));
        $fetchWebContext  = isset($params['fetch_web_context']) ? (bool)$params['fetch_web_context'] : true;

        $levelConfig = self::getDetailLevelConfig($detailLevel);
        if ($maxTokens <= 0) {
            $maxTokens = $levelConfig['tokens'];
        }
        $maxTokens = max(50, min(4096, $maxTokens));

        // Fetch live webpage context if an external link is provided
        $pageContext = ['title' => '', 'description' => '', 'excerpt' => '', 'fetched' => false];
        if ($fetchWebContext && !empty($itemLink) && filter_var($itemLink, FILTER_VALIDATE_URL)) {
            $pageContext = self::fetchPageContext($itemLink);
        }

        // Compute available text character budget excluding appended URL
        $reservedLinkChars = ($includeLink && !empty($itemLink)) ? (mb_strlen($itemLink, 'UTF-8') + 2) : 0;
        $maxCaptionChars = 0;
        if ($maxChars > 0) {
            $maxCaptionChars = max(40, $maxChars - $reservedLinkChars);
        }

        // Build structured prompt
        $promptParts = [];

        if (!empty($userPrompt)) {
            $promptParts[] = "USER INSTRUCTIONS:\n" . $userPrompt;
        } else {
            $promptParts[] = "USER INSTRUCTIONS:\nWrite an engaging and informative social media caption describing the shared link/topic to captivate readers.";
        }

        $contextLines = [];
        if (!empty($itemTitle)) {
            $contextLines[] = "Title: " . $itemTitle;
        }
        if (!empty($itemLink)) {
            $contextLines[] = "URL: " . $itemLink;
        }
        if (!empty($itemDescription)) {
            $contextLines[] = "Feed Summary: " . mb_substr($itemDescription, 0, 800, 'UTF-8');
        }
        if (!empty($pageContext['title']) && $pageContext['title'] !== $itemTitle) {
            $contextLines[] = "Page Title: " . $pageContext['title'];
        }
        if (!empty($pageContext['description'])) {
            $contextLines[] = "Page Meta Description: " . mb_substr($pageContext['description'], 0, 600, 'UTF-8');
        }
        if (!empty($pageContext['excerpt'])) {
            $contextLines[] = "Page Content Excerpt:\n" . $pageContext['excerpt'];
        }

        if (!empty($contextLines)) {
            $promptParts[] = "ITEM / LINK SOURCE CONTEXT:\n" . implode("\n", $contextLines);
        }

        if (!empty($extraInstruction)) {
            $promptParts[] = $extraInstruction;
        }

        $rules = [
            "DETAIL LEVEL ({$detailLevel}): " . $levelConfig['instruction'],
            "Output ONLY the social media post caption text without conversational filler or quotation marks.",
            "Use PLAIN TEXT only. Do NOT use markdown syntax (no **bold**, no *italics*, no # headers, no bullet lists with *).",
            "Do NOT include image tags or special tokens like <image> or <image|>.",
            "Emojis and relevant hashtags are allowed."
        ];

        if ($includeLink && !empty($itemLink)) {
            $rules[] = "Do NOT write the raw URL inside your caption because the system will append the link automatically at the end.";
        }

        if ($maxCaptionChars > 0) {
            $rules[] = "STRICT LENGTH LIMIT: Your generated caption MUST be strictly under {$maxCaptionChars} characters (including spaces and hashtags).";
        }

        $promptParts[] = "CRITICAL RULES FOR SOCIAL POST:\n- " . implode("\n- ", $rules);

        $fullPrompt = mb_convert_encoding(implode("\n\n", $promptParts), 'UTF-8', 'UTF-8');

        $rawResponse = self::callProvider($fullPrompt, $maxTokens, $temperature);
        if (!$rawResponse['success']) {
            return $rawResponse;
        }

        $caption = self::cleanAiSocialPost($rawResponse['text']);

        // Remove accidental raw link duplication if we will append it cleanly
        if ($includeLink && !empty($itemLink)) {
            $caption = trim(str_replace($itemLink, '', $caption));
        }

        // Enforce strict character limit on caption if requested
        if ($maxCaptionChars > 0 && mb_strlen($caption, 'UTF-8') > $maxCaptionChars) {
            $caption = self::truncateAtBoundary($caption, $maxCaptionChars);
        }

        $finalText = $caption;
        if ($includeLink && !empty($itemLink)) {
            $finalText = ($caption !== '' ? $caption . "\n\n" : '') . $itemLink;
        }

        return [
            'success' => true,
            'caption' => $caption,
            'text'    => $finalText
        ];
    }

    /**
     * Safely truncate text at sentence or word boundary within a max character limit.
     */
    public static function truncateAtBoundary(string $text, int $maxLength): string {
        if ($maxLength <= 0 || mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        $slice = mb_substr($text, 0, max(1, $maxLength - 3), 'UTF-8');

        // Prefer ending on a complete sentence if at least 60% of budget is preserved
        $lastSentence = max(
            (int)mb_strrpos($slice, '.', 0, 'UTF-8'),
            (int)mb_strrpos($slice, '!', 0, 'UTF-8'),
            (int)mb_strrpos($slice, '?', 0, 'UTF-8')
        );

        if ($lastSentence > (int)($maxLength * 0.6)) {
            return trim(mb_substr($slice, 0, $lastSentence + 1, 'UTF-8'));
        }

        $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > (int)($maxLength * 0.5)) {
            $slice = mb_substr($slice, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($slice, " \t\n\r\0\x0B.,;:-") . '...';
    }

    /**
     * Execute request against the configured AI provider (openai-chat, ollama, gemini).
     */
    public static function callProvider(string $prompt, int $maxTokens = 512, float $temperature = 0.7): array {
        $provider = Config::AI_PROVIDER;

        if ($provider === 'openai-chat') {
            $endpoint = Config::OPENAI_ENDPOINT;
            $model    = Config::OPENAI_MODEL;

            if (empty($endpoint) || empty($model)) {
                return ['success' => false, 'error' => 'OpenAI Chat provider is not configured: missing endpoint or model.'];
            }

            $requestPayload = [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'stream'      => false,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens
            ];

            $payload = json_encode(
                $requestPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($payload === false) {
                return ['success' => false, 'error' => 'JSON encode error: ' . json_last_error_msg()];
            }

            $headers = [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'Expect:'
            ];
            if (!empty(Config::OPENAI_API_KEY)) {
                $headers[] = 'Authorization: Bearer ' . Config::OPENAI_API_KEY;
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
                if ($text === '') {
                    return ['success' => false, 'error' => 'AI provider returned empty content.'];
                }
                return ['success' => true, 'text' => $text];
            }

            return ['success' => false, 'error' => "OpenAI Chat API failed (HTTP {$httpCode}): {$curlErr}"];
        }

        if ($provider === 'ollama') {
            $requestPayload = [
                'model'   => Config::OLLAMA_MODEL,
                'prompt'  => $prompt,
                'stream'  => false,
                'options' => [
                    'num_ctx'     => 4096,
                    'num_predict' => $maxTokens,
                    'temperature' => $temperature
                ]
            ];

            $payload = json_encode(
                $requestPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            $headers = [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'Expect:'
            ];

            $ch = curl_init(Config::OLLAMA_ENDPOINT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = trim((string)($data['response'] ?? ''));
                if ($text === '') {
                    return ['success' => false, 'error' => 'Ollama returned empty content.'];
                }
                return ['success' => true, 'text' => $text];
            }

            return ['success' => false, 'error' => "Ollama API failed (HTTP {$httpCode}): {$curlErr}"];
        }

        if ($provider === 'gemini') {
            $geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . Config::GEMINI_API_KEY;

            $requestPayload = [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature'     => $temperature,
                    'maxOutputTokens' => $maxTokens
                ]
            ];

            $payload = json_encode(
                $requestPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            $headers = [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'Expect:'
            ];

            $ch = curl_init($geminiEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = trim((string)($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
                if ($text === '') {
                    return ['success' => false, 'error' => 'Gemini returned empty content.'];
                }
                return ['success' => true, 'text' => $text];
            }

            return ['success' => false, 'error' => "Gemini API failed (HTTP {$httpCode}): {$curlErr}"];
        }

        return ['success' => false, 'error' => "Invalid AI provider configured: {$provider}"];
    }
}
