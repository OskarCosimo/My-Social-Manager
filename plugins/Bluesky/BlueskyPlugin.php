<?php
// plugins/Bluesky/BlueskyPlugin.php

require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';

class BlueskyPlugin implements SocialPluginInterface {
    
    private string $baseUrl = 'https://bsky.social/xrpc';

    public function getId(): string {
        return 'bluesky';
    }

    public function getName(): string {
        return 'Bluesky';
    }

    /**
     * Get official brand color hex code for Bluesky
     */
    public function getColor(): string {
        return '#0285FF';
    }

    public function getSettingsFields(): array {
        return [];
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    public function getManualSetupHtml(): string {
        return '
        <div class="alert alert-info small mb-4">
            <strong>Connect Bluesky:</strong><br>
            Bluesky uses App Passwords instead of standard OAuth. Generate an App Password in your <strong>Bluesky Settings > Advanced > App Passwords</strong>.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Bluesky Handle</label>
            <input type="text" name="handle" class="form-control" placeholder="e.g., yourname.bsky.social" required>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">App Password</label>
            <input type="password" name="app_password" class="form-control" placeholder="xxxx-xxxx-xxxx-xxxx" required>
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        $rawHandle = trim($requestData['handle'] ?? '');
        $appPassword = trim($requestData['app_password'] ?? '');

        $handle = $rawHandle;
        if (str_starts_with($rawHandle, 'http')) {
            $parts = explode('/', rtrim($rawHandle, '/'));
            $handle = end($parts);
        }
        $handle = ltrim($handle, '@');

        if (empty($handle) || empty($appPassword)) {
            throw new Exception("Handle and App Password are required.");
        }

        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User session not found.");
        }

        if (!UsageManager::canAddAccount($_SESSION['user_id'])) {
            throw new Exception("You have reached the maximum number of social accounts allowed for your plan.");
        }

        $session = $this->createSession($handle, $appPassword);

        $finalToken = json_encode([
            'token' => $appPassword,
            'did' => $session['did'],
            'handle' => $session['handle']
        ]);

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $session['did'],
            'platform_name' => $session['handle']
        ];
    }

    /**
     * Live verification of stored Bluesky credentials
     */
    public function checkTokenStatus(string $accessToken): array {
        try {
            $session = json_decode($accessToken, true);
            $rawToken = $session['token'] ?? '';
            $handle = $session['handle'] ?? '';

            if (empty($handle) || empty($rawToken)) {
                return ['valid' => false, 'message' => 'Incomplete Bluesky credentials stored.'];
            }

            $authSession = $this->createSession($handle, $rawToken);

            if (!empty($authSession['accessJwt'])) {
                return [
                    'valid' => true,
                    'message' => 'Bluesky connection is active (@' . ($authSession['handle'] ?? $handle) . ')'
                ];
            }

            return ['valid' => false, 'message' => 'Unable to authenticate with Bluesky.'];
        } catch (Exception $e) {
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }

    public function publishPost(string $content, array $mediaUrls, string $sessionData, ?string $sourceLink = null): array {
        try {
            $session = json_decode($sessionData, true);
            $rawToken = $session['token'] ?? '';
            $handle = $session['handle'] ?? '';
            
            if (empty($handle) || empty($rawToken)) {
                return ['success' => false, 'error' => 'Invalid session data. Please reconnect Bluesky.'];
            }

            $authSession = $this->createSession($handle, $rawToken);
            $accessJwt = $authSession['accessJwt'];
            $did = $authSession['did'];

            $plainContent = trim(html_entity_decode(strip_tags($content)));
            $fullText = $plainContent;

            if (!empty($sourceLink) && empty($mediaUrls)) {
                if (strpos($fullText, $sourceLink) === false) {
                    $fullText .= "\n\n" . $sourceLink;
                }
            }

            $maxGraphemes = 300;
            if (function_exists('grapheme_strlen') && grapheme_strlen($fullText) > $maxGraphemes) {
                $fullText = grapheme_substr($fullText, 0, $maxGraphemes - 3) . '...';
            } elseif (mb_strlen($fullText) > $maxGraphemes) {
                $fullText = mb_substr($fullText, 0, $maxGraphemes - 3) . '...';
            }

            $facets = $this->parseFacets($fullText);

            $record = [
                'text'      => $fullText,
                'createdAt' => date(DATE_ATOM)
            ];

            if (!empty($facets)) {
                $record['facets'] = $facets;
            }

            // 1. If images are present, upload image blobs natively to Bluesky AT Protocol
            if (!empty($mediaUrls)) {
                $imagesEmbed = [];
                foreach (array_slice($mediaUrls, 0, 4) as $mediaUrl) {
                    $blob = $this->uploadBlobToBluesky($accessJwt, $mediaUrl);
                    if ($blob) {
                        $imagesEmbed[] = [
                            'alt' => mb_substr($plainContent, 0, 100) ?: 'Image',
                            'image' => $blob
                        ];
                    }
                }

                if (!empty($imagesEmbed)) {
                    $record['embed'] = [
                        '$type' => 'app.bsky.embed.images',
                        'images' => $imagesEmbed
                    ];
                }
            } 
            // 2. Otherwise, fall back to link preview card
            elseif (!empty($sourceLink)) {
                $record['embed'] = [
                    '$type' => 'app.bsky.embed.external',
                    'external' => [
                        'uri'         => $sourceLink,
                        'title'       => mb_substr($plainContent, 0, 80) ?: 'Link',
                        'description' => mb_substr($plainContent, 0, 200)
                    ]
                ];
            }

            $endpoint = $this->baseUrl . '/com.atproto.repo.createRecord';
            $payload = json_encode([
                'repo'       => $did,
                'collection' => 'app.bsky.feed.post',
                'record'     => $record
            ]);

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessJwt
            ]);

            $responseRaw = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($responseRaw, true);

            if (isset($response['error'])) {
                return ['success' => false, 'error' => $response['message'] ?? 'Failed to publish to Bluesky'];
            }

            return ['success' => true, 'post_id' => $response['uri'] ?? ''];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function uploadBlobToBluesky(string $jwt, string $mediaUrl): ?array {
        $fileData = @file_get_contents($mediaUrl);
        if (!$fileData) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $fileData) ?: 'image/jpeg';
        finfo_close($finfo);

        $endpoint = $this->baseUrl . '/com.atproto.repo.uploadBlob';
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $mimeType,
            'Authorization: Bearer ' . $jwt
        ]);

        $resultRaw = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($resultRaw, true);
        return $response['blob'] ?? null;
    }

    private function parseFacets(string $text): array {
        $facets = [];
        $pattern = '/https?:\/\/[^\s]+/';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $url = $match[0];
                $utf8Offset = $match[1];

                $byteStart = strlen(substr($text, 0, $utf8Offset));
                $byteEnd = $byteStart + strlen($url);

                $facets[] = [
                    'index' => [
                        'byteStart' => $byteStart,
                        'byteEnd'   => $byteEnd
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#link',
                            'uri'   => $url
                        ]
                    ]
                ];
            }
        }

        return $facets;
    }

    private function createSession(string $identifier, string $password): array {
        $endpoint = $this->baseUrl . '/com.atproto.server.createSession';
        $payload = json_encode([
            'identifier' => $identifier,
            'password' => $password
        ]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if (isset($response['error'])) {
            throw new Exception($response['message'] ?? 'Invalid Handle or App Password');
        }

        return $response;
    }
}