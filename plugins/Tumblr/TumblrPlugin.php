<?php
// plugins/Tumblr/TumblrPlugin.php
require_once __DIR__ . '/../../app/Models/PluginSetting.php';
require_once __DIR__ . '/../../app/Core/Config.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';
require_once __DIR__ . '/../../app/Core/Database.php';

class TumblrPlugin implements SocialPluginInterface {
    
    public function getId(): string {
        return 'tumblr';
    }

    public function getName(): string {
        return 'Tumblr';
    }

    public function getColor(): string {
        return '#36465D';
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => 'Tumblr Consumer Key (Client ID)',
                'placeholder' => 'Enter your Consumer Key'
            ],
            'client_secret' => [
                'type' => 'password',
                'label' => 'Tumblr Consumer Secret (Client Secret)',
                'placeholder' => 'Enter your Consumer Secret'
            ]
        ];
    }

    private function getCallbackUrl(): string {
        return Config::APP_URL . '/plugin/callback';
    }

    public function getAuthUrl(): string {
        return '/accounts/manual-setup?platform=' . $this->getId();
    }

    public function getManualSetupHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';
        
        return '
        <div class="alert alert-info small mb-4">
            <strong>BYOK (Bring Your Own Key) Setup:</strong><br>
            If you prefer to use the system default app, leave these fields blank.<br><br>
            1. Go to <a href="https://www.tumblr.com/oauth/apps" target="_blank">Tumblr API Apps</a> and register a new application.<br>
            2. Set the <strong>Default Callback URL</strong> to:<br>
               <code>' . $callbackUrl . '</code><br>
            3. Tumblr uses OAuth 2.0. Ensure you request the <code>basic</code>, <code>write</code>, and <code>offline_access</code> scopes.
        </div>
        
        <input type="hidden" name="byok_submit" value="1">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Custom Consumer Key</label>
            <input type="text" name="custom_client_id" class="form-control" placeholder="Leave blank to use System App">
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Custom Consumer Secret</label>
            <input type="password" name="custom_client_secret" class="form-control" placeholder="Leave blank to use System App">
        </div>
        ';
    }

    public function handleCallback(array $requestData): array {
        // STEP 0: Selection from multiple blogs
        if (isset($_POST['selected_blog_name']) && isset($_SESSION['temp_tumblr_blogs'])) {
            $selectedBlogName = $_POST['selected_blog_name'];
            $blogs = $_SESSION['temp_tumblr_blogs'];
            $byok = $_SESSION['temp_tumblr_byok'] ?? [];
            $tokenData = $_SESSION['temp_tumblr_token_data'] ?? [];
            
            $blogTitle = 'Tumblr Blog';
            $blogHostname = $selectedBlogName;
            foreach ($blogs as $b) {
                if ($b['name'] === $selectedBlogName) {
                    $blogTitle = $b['title'] ?: $b['name'];
                    if (!empty($b['name'])) {
                        $blogHostname = str_contains($b['name'], '.') ? $b['name'] : $b['name'] . '.tumblr.com';
                    }
                    break;
                }
            }
            
            $finalTokenData = [
                'token' => $tokenData['access_token'] ?? '',
                'refresh_token' => $tokenData['refresh_token'] ?? '',
                'blog_name' => $blogHostname
            ];

            if (!empty($byok['is_custom'])) {
                $finalTokenData['client_id'] = $byok['client_id'];
                $finalTokenData['client_secret'] = $byok['client_secret'];
            }

            $finalToken = json_encode($finalTokenData);
            $expiresIn = $tokenData['expires_in'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;

            unset($_SESSION['temp_tumblr_blogs'], $_SESSION['temp_tumblr_byok'], $_SESSION['temp_tumblr_token_data'], $_SESSION['byok_' . $this->getId()]);
            
            return [
                'access_token' => $finalToken,
                'platform_user_id' => $blogHostname,
                'platform_name' => $blogTitle . ' (Tumblr)',
                'expires_in' => $expiresIn,
                'refresh_token' => $refreshToken
            ];
        }

        // STEP 1: BYOK submission and OAuth redirect
        if (isset($requestData['byok_submit'])) {
            $clientId = trim($requestData['custom_client_id'] ?? '');
            $clientSecret = trim($requestData['custom_client_secret'] ?? '');
            $isCustom = true;

            if (empty($clientId) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientId = $settings['client_id'] ?? '';
                $clientSecret = $settings['client_secret'] ?? '';
                $isCustom = false;
            }

            if (empty($clientId)) {
                throw new Exception("Client ID is missing. Admin configuration required or custom keys must be provided.");
            }

            $_SESSION['byok_' . $this->getId()] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_custom' => $isCustom
            ];

            $redirectUri = urlencode($this->getCallbackUrl());
            $scopes = urlencode('basic write offline_access');
            $state = urlencode($this->getId());
            
            $oauthUrl = "https://www.tumblr.com/oauth2/authorize?client_id={$clientId}&response_type=code&scope={$scopes}&state={$state}";
            header("Location: " . $oauthUrl);
            exit;
        }

        // STEP 2: OAuth return callback
        if (isset($requestData['error'])) {
            throw new Exception("Tumblr Error: " . ($requestData['error_description'] ?? 'Unknown error'));
        }

        $code = $requestData['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Authorization code missing.");
        }

        $byok = $_SESSION['byok_' . $this->getId()] ?? [];
        $clientId = $byok['client_id'] ?? '';
        $clientSecret = $byok['client_secret'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            $settings = PluginSetting::getForPlugin($this->getId());
            $clientId = $settings['client_id'] ?? '';
            $clientSecret = $settings['client_secret'] ?? '';
        }

        $redirectUri = $this->getCallbackUrl();

        $tokenUrl = "https://api.tumblr.com/v2/oauth2/token";
        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenRes = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($tokenRes['error'])) {
            throw new Exception("Tumblr Token Error: " . ($tokenRes['error_description'] ?? $tokenRes['error']));
        }

        $accessToken = $tokenRes['access_token'];
        $expiresIn = $tokenRes['expires_in'] ?? null;
        $refreshToken = $tokenRes['refresh_token'] ?? null;

        $meUrl = "https://api.tumblr.com/v2/user/info";
        $ch2 = curl_init($meUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        $meRes = json_decode(curl_exec($ch2), true);
        curl_close($ch2);

        if (!isset($meRes['response']['user']['blogs']) || empty($meRes['response']['user']['blogs'])) {
            throw new Exception("No Tumblr blogs found on this account. You need at least one blog to post.");
        }

        $blogs = $meRes['response']['user']['blogs'];

        if (count($blogs) > 1) {
            $_SESSION['temp_tumblr_blogs'] = $blogs;
            $_SESSION['temp_tumblr_byok'] = $byok;
            $_SESSION['temp_tumblr_token_data'] = $tokenRes;
            
            $html = '<div style="font-family: system-ui, sans-serif; max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">';
            $html .= '<h3 style="margin-top: 0; color: #36465D;">Select Tumblr Blog</h3>';
            $html .= '<p style="color: #555; font-size: 14px;">Multiple blogs found. Select which one to connect:</p>';
            $html .= '<form method="POST" action="">';
            foreach ($blogs as $b) {
                $displayName = !empty($b['title']) ? $b['title'] . ' (@' . $b['name'] . ')' : '@' . $b['name'];
                $html .= '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 5px; cursor: pointer;">';
                $html .= '<label style="cursor: pointer; display: block; font-weight: 500;">';
                $html .= '<input type="radio" name="selected_blog_name" value="'.$b['name'].'" required style="margin-right: 10px;"> ' . htmlspecialchars($displayName);
                $html .= '</label></div>';
            }
            $html .= '<button type="submit" style="margin-top: 15px; width: 100%; padding: 12px; background: #36465D; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Connect Blog</button>';
            $html .= '</form></div>';
            echo $html;
            exit;
        }

        $blog = $blogs[0];
        $blogName = $blog['name'];
        $blogHostname = str_contains($blogName, '.') ? $blogName : $blogName . '.tumblr.com';
        $blogTitle = $blog['title'] ?: $blogName;

        $finalTokenData = [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'blog_name' => $blogHostname
        ];

        if (!empty($byok['is_custom'])) {
            $finalTokenData['client_id'] = $clientId;
            $finalTokenData['client_secret'] = $clientSecret;
        }

        $finalToken = json_encode($finalTokenData);

        unset($_SESSION['byok_' . $this->getId()]);

        return [
            'access_token' => $finalToken,
            'platform_user_id' => $blogHostname,
            'platform_name' => $blogTitle . ' (Tumblr)',
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Helper to retrieve a valid access token, automatically refreshing it if expired
     */
    private function getValidAccessToken(string $storedAccessToken): array {
        $decoded = json_decode($storedAccessToken, true);
        if (!is_array($decoded)) {
            return ['token' => $storedAccessToken, 'blog_name' => null];
        }

        $realToken = $decoded['token'] ?? '';
        $refreshToken = $decoded['refresh_token'] ?? '';
        $blogName = $decoded['blog_name'] ?? null;
        $clientId = $decoded['client_id'] ?? null;
        $clientSecret = $decoded['client_secret'] ?? null;

        // First test if current access token is working
        $ch = curl_init("https://api.tumblr.com/v2/user/info");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . trim($realToken)]);
        $res = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Token is still valid
        if ($httpCode === 200 && !empty($res['response']['user'])) {
            return ['token' => $realToken, 'blog_name' => $blogName];
        }

        // If expired (401) and we have a refresh token, perform auto-refresh
        if (!empty($refreshToken)) {
            if (empty($clientId) || empty($clientSecret)) {
                $settings = PluginSetting::getForPlugin($this->getId());
                $clientId = $settings['client_id'] ?? '';
                $clientSecret = $settings['client_secret'] ?? '';
            }

            if (!empty($clientId) && !empty($clientSecret)) {
                $postData = http_build_query([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret
                ]);

                $chRef = curl_init("https://api.tumblr.com/v2/oauth2/token");
                curl_setopt($chRef, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chRef, CURLOPT_POST, true);
                curl_setopt($chRef, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($chRef, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                $refRes = json_decode(curl_exec($chRef), true);
                curl_close($chRef);

                if (!empty($refRes['access_token'])) {
                    $newAccessToken = $refRes['access_token'];
                    $newRefreshToken = $refRes['refresh_token'] ?? $refreshToken;
                    $expiresIn = (int)($refRes['expires_in'] ?? 3600);
                    $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

                    $decoded['token'] = $newAccessToken;
                    $decoded['refresh_token'] = $newRefreshToken;
                    $updatedJson = json_encode($decoded);

                    // Update token in database for this account
                    try {
                        $db = Database::getInstance();
                        $upStmt = $db->prepare("
                            UPDATE social_accounts 
                            SET access_token = ?, refresh_token = ?, token_expires_at = ? 
                            WHERE access_token = ?
                        ");
                        $upStmt->execute([$updatedJson, $newRefreshToken, $newExpiresAt, $storedAccessToken]);
                    } catch (Exception $e) {
                        error_log("Tumblr auto-refresh DB update failed: " . $e->getMessage());
                    }

                    return [
                        'token' => $newAccessToken,
                        'blog_name' => $blogName,
                        'expires_at' => $newExpiresAt
                    ];
                }
            }
        }

        return ['token' => $realToken, 'blog_name' => $blogName];
    }

    /**
     * Live verification of Tumblr access token
     */
    public function checkTokenStatus(string $accessToken): array {
        $tokenInfo = $this->getValidAccessToken($accessToken);
        $realToken = $tokenInfo['token'];
        $blogName = $tokenInfo['blog_name'] ?: 'Tumblr Blog';

        $blogsUrl = "https://api.tumblr.com/v2/user/info";
        $ch = curl_init($blogsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($realToken),
            'Accept: application/json'
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($responseRaw, true) ?? [];

        if ($httpCode === 200 && !empty($res['response']['user'])) {
            $status = [
                'valid' => true,
                'message' => "Tumblr active ({$blogName})"
            ];
            if (isset($tokenInfo['expires_at'])) {
                $status['expires_at'] = $tokenInfo['expires_at'];
            }
            return $status;
        }

        $errorMsg = $res['meta']['msg'] ?? "Tumblr authorization expired or revoked (HTTP {$httpCode}). Please reconnect.";
        return [
            'valid' => false,
            'message' => $errorMsg
        ];
    }

    /**
     * Scrape OpenGraph metadata to enrich Tumblr link preview blocks
     */
    private function fetchUrlMetadata(string $url): array {
        $meta = [
            'title' => '',
            'description' => '',
            'site_name' => '',
            'image' => ''
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
        // Fetch up to 256KB to keep memory overhead minimal
        curl_setopt($ch, CURLOPT_RANGE, '0-262143');
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$html || ($httpCode >= 400 && $httpCode !== 206)) {
            return $meta;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);

        // Extract title
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            $meta['title'] = trim($ogTitle->item(0)->nodeValue);
        } else {
            $pageTitle = $xpath->query('//title');
            if ($pageTitle->length > 0) {
                $meta['title'] = trim($pageTitle->item(0)->nodeValue);
            }
        }

        // Extract description
        $ogDesc = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogDesc->length > 0) {
            $meta['description'] = trim($ogDesc->item(0)->nodeValue);
        } else {
            $metaDesc = $xpath->query('//meta[@name="description"]/@content');
            if ($metaDesc->length > 0) {
                $meta['description'] = trim($metaDesc->item(0)->nodeValue);
            }
        }

        // Extract site name
        $ogSite = $xpath->query('//meta[@property="og:site_name"]/@content');
        if ($ogSite->length > 0) {
            $meta['site_name'] = trim($ogSite->item(0)->nodeValue);
        } else {
            $host = parse_url($url, PHP_URL_HOST);
            $meta['site_name'] = $host ? preg_replace('/^www\./', '', $host) : '';
        }

        // Extract preview image
        $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
        if ($ogImage->length > 0) {
            $imgUrl = trim($ogImage->item(0)->nodeValue);
            if (str_starts_with($imgUrl, '//')) {
                $imgUrl = 'https:' . $imgUrl;
            } elseif (str_starts_with($imgUrl, '/')) {
                $parsed = parse_url($url);
                $imgUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $imgUrl;
            }
            $meta['image'] = $imgUrl;
        }

        return $meta;
    }

    public function publishPost(string $content, array $mediaUrls, string $accessToken, ?string $sourceLink = null): array {
        $tokenInfo = $this->getValidAccessToken($accessToken);
        $realToken = $tokenInfo['token'];
        $blogIdentifier = $tokenInfo['blog_name'];

        if ($blogIdentifier && !str_contains($blogIdentifier, '.')) {
            $blogIdentifier .= '.tumblr.com';
        }

        if (!$blogIdentifier) {
            $blogsUrl = "https://api.tumblr.com/v2/user/info";
            $ch = curl_init($blogsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . trim($realToken),
                'Accept: application/json'
            ]);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (isset($res['meta']['status']) && $res['meta']['status'] === 401) {
                return [
                    'success' => false, 
                    'error' => 'Tumblr Authorization Expired (401). Please reconnect your Tumblr account.'
                ];
            }
            
            $blogName = $res['response']['user']['blogs'][0]['name'] ?? null;
            if (!$blogName) {
                return ['success' => false, 'error' => 'Could not find a valid Tumblr blog for this user.'];
            }
            $blogIdentifier = str_contains($blogName, '.') ? $blogName : $blogName . '.tumblr.com';
        }

        $postUrl = "https://api.tumblr.com/v2/blog/{$blogIdentifier}/posts";

        $formattedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $formattedContent = preg_replace('/<\/p>/i', "\n\n", $formattedContent);
        $plainText = trim(strip_tags(html_entity_decode($formattedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Identify primary link for the preview card
        $primaryLink = !empty($sourceLink) ? trim($sourceLink) : null;
        if (!$primaryLink && empty($mediaUrls)) {
            if (preg_match('/https?:\/\/[^\s<>"\'\)]+/u', $plainText, $matchedUrl)) {
                $primaryLink = $matchedUrl[0];
            }
        }

        $fullBody = $plainText;
        if (!empty($sourceLink) && strpos($fullBody, $sourceLink) === false) {
            $fullBody .= ($fullBody !== '' ? "\n\n" : '') . $sourceLink;
        }

        $contentBlocks = [];

        // 1. Add image blocks if any media URLs are attached
        if (!empty($mediaUrls)) {
            foreach ($mediaUrls as $url) {
                $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mimeType = 'image/jpeg';
                if ($ext === 'png') {
                    $mimeType = 'image/png';
                } elseif ($ext === 'gif') {
                    $mimeType = 'image/gif';
                } elseif ($ext === 'webp') {
                    $mimeType = 'image/webp';
                }

                $contentBlocks[] = [
                    'type' => 'image',
                    'media' => [
                        [
                            'url' => $url,
                            'type' => $mimeType
                        ]
                    ]
                ];
            }
        }

        // 2. Add text block with active hyperlink formatting
        if (!empty($fullBody)) {
            $textBlock = [
                'type' => 'text',
                'text' => $fullBody
            ];

            // Build NPF hyperlink formatting for all detected URLs
            $formatting = [];
            if (preg_match_all('/https?:\/\/[^\s<>"\'\)]+/u', $fullBody, $urlMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($urlMatches[0] as $match) {
                    $rawUrl = $match[0];
                    $bytePos = $match[1];
                    $startChar = mb_strlen(substr($fullBody, 0, $bytePos), 'UTF-8');
                    $endChar = $startChar + mb_strlen($rawUrl, 'UTF-8');

                    $formatting[] = [
                        'start' => $startChar,
                        'end' => $endChar,
                        'type' => 'link',
                        'url' => $rawUrl
                    ];
                }
            }

            if (!empty($formatting)) {
                $textBlock['formatting'] = $formatting;
            }

            $contentBlocks[] = $textBlock;
        }

        // 3. Add link block for the rich preview card
        if (!empty($primaryLink)) {
            $linkMeta = $this->fetchUrlMetadata($primaryLink);

            $linkBlock = [
                'type' => 'link',
                'url' => $primaryLink
            ];

            if (!empty($linkMeta['title'])) {
                $linkBlock['title'] = $linkMeta['title'];
            }
            if (!empty($linkMeta['description'])) {
                $linkBlock['description'] = $linkMeta['description'];
            }
            if (!empty($linkMeta['site_name'])) {
                $linkBlock['site_name'] = $linkMeta['site_name'];
            }

            // Include image thumbnail poster if available
            if (!empty($linkMeta['image'])) {
                $imgExt = strtolower(pathinfo(parse_url($linkMeta['image'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                $posterMime = 'image/jpeg';
                if ($imgExt === 'png') {
                    $posterMime = 'image/png';
                } elseif ($imgExt === 'webp') {
                    $posterMime = 'image/webp';
                } elseif ($imgExt === 'gif') {
                    $posterMime = 'image/gif';
                }

                $linkBlock['poster'] = [
                    [
                        'type' => $posterMime,
                        'url' => $linkMeta['image']
                    ]
                ];
            }

            $contentBlocks[] = $linkBlock;
        }

        if (empty($contentBlocks)) {
            $contentBlocks[] = [
                'type' => 'text',
                'text' => 'New post'
            ];
        }

        $postPayload = [
            'content' => $contentBlocks
        ];

        $jsonPayload = json_encode($postPayload);

        $ch = curl_init($postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($realToken),
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $result = curl_exec($ch);
        $response = json_decode($result, true);
        curl_close($ch);

        if (!isset($response['meta']['status']) || ($response['meta']['status'] !== 201 && $response['meta']['status'] !== 200)) {
            $status = $response['meta']['status'] ?? '400';
            $errorMsg = $response['meta']['msg'] ?? 'Bad Request';
            if (isset($response['errors'][0]['detail'])) {
                $errorMsg .= ': ' . $response['errors'][0]['detail'];
            }
            
            return ['success' => false, 'error' => "Tumblr API Status ({$status}): " . $errorMsg];
        }

        return ['success' => true, 'post_id' => $response['response']['id'] ?? ''];
    }

    public function getAdminInstructionsHtml(): string {
        $callbackUrl = Config::APP_URL . '/plugin/callback';

        return '
        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 1: Register Tumblr Application</h6>
            <p class="text-muted small mb-0">
                Go to <a href="https://www.tumblr.com/oauth/apps" target="_blank" class="fw-bold">Tumblr API Apps</a> and click <strong>Register application</strong>.
            </p>
        </div>

        <hr class="my-3 text-muted">

        <div class="mb-3">
            <h6 class="fw-bold text-primary mb-1">Step 2: Set Default Callback URL</h6>
            <p class="text-muted small mb-1">
                In the application registration form, set the <strong>Default Callback URL</strong> to:
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control font-monospace" id="adminTumblrRedirect" value="' . htmlspecialchars($callbackUrl) . '" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'adminTumblrRedirect\').value); this.innerText=\'Copied!\';">Copy URI</button>
            </div>
        </div>

        <hr class="my-3 text-muted">

        <div>
            <h6 class="fw-bold text-primary mb-1">Step 3: Copy OAuth Consumer Credentials</h6>
            <p class="text-muted small mb-0">
                Copy your <strong>OAuth Consumer Key</strong> (Client ID) and <strong>Secret Key</strong> (Client Secret) and save them below.
            </p>
        </div>
        ';
    }
}