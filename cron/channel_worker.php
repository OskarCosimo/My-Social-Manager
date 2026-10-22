<?php
// cron/channel_worker.php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

require_once BASE_PATH . 'app/Core/Config.php';
require_once BASE_PATH . 'app/Core/Database.php';
require_once BASE_PATH . 'app/Models/ChannelAutomation.php';
require_once BASE_PATH . 'app/Models/UsageManager.php';

// Allow execution either via CLI or via internal PHP cron runner
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CRON_EXEC')) {
    die("This script can only be run from the command line or internal runner.");
}

date_default_timezone_set('UTC');

/**
 * Safe fetch for official YouTube RSS feed (Tier 1)
 */
if (!function_exists('fetchSafeYoutubeFeed')) {
    function fetchSafeYoutubeFeed(string $feedUrl, &$outHttpCode = null): ?string {
        $ch = curl_init($feedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: MySocialManager/1.0 (Feed Reader; +https://socialmanager.myetv.tv)',
            'Accept: application/atom+xml,application/xml,text/xml;q=0.9,*/*;q=0.8'
        ]);
        
        $xmlData = curl_exec($ch);
        $outHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($outHttpCode !== 200 || empty($xmlData)) {
            return null;
        }

        return $xmlData;
    }
}

/**
 * Direct channel parser (Tier 3 fallback when official RSS is 404 or down)
 */
if (!function_exists('fetchYoutubeVideosDirect')) {
    function fetchYoutubeVideosDirect(string $channelIdentifier, string $sourceTitle = ''): array {
        $target = $channelIdentifier;
        if (empty($target) || !str_starts_with($target, 'UC')) {
            $cleanTitle = preg_replace('/^YouTube:\s*/i', '', $sourceTitle);
            if (!empty($cleanTitle)) {
                $target = $cleanTitle;
            }
        }

        $url = str_starts_with($target, 'UC')
            ? "https://www.youtube.com/channel/{$target}"
            : (str_starts_with($target, '@') ? "https://www.youtube.com/{$target}" : "https://www.youtube.com/@" . ltrim($target, '/'));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cookie: SOCS=CAESEwgDEgk0ODE3Nzk3MjQaAmVuIAEaBgiA_LyaBg;'
        ]);
        
        $html = curl_exec($ch);
        curl_close($ch);

        if (empty($html)) {
            return [];
        }

        preg_match_all('/"videoId":"([a-zA-Z0-9_-]{11})"/i', $html, $matches);
        $videoIds = array_unique($matches[1] ?? []);

        if (empty($videoIds)) {
            return [];
        }

        $items = [];
        $recentIds = array_slice($videoIds, 0, 15);

        foreach ($recentIds as $vId) {
            $videoUrl = "https://www.youtube.com/watch?v={$vId}";
            $title = "YouTube Video";

            // Get exact video title via official public oEmbed
            $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($videoUrl) . "&format=json";
            $chOe = curl_init($oembedUrl);
            curl_setopt($chOe, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chOe, CURLOPT_TIMEOUT, 5);
            $oeRes = curl_exec($chOe);
            curl_close($chOe);

            if ($oeRes) {
                $oeData = json_decode($oeRes, true);
                if (!empty($oeData['title'])) {
                    $title = $oeData['title'];
                }
            }

            $items[] = [
                'remote_id' => $vId,
                'title'     => trim($title),
                'link'      => $videoUrl
            ];
        }

        return $items;
    }
}

/**
 * Helper to resolve YouTube channel ID from official RSS link or canonical tag
 */
if (!function_exists('resolveYoutubeChannelIdWorker')) {
    function resolveYoutubeChannelIdWorker(string $input): ?string {
        $input = trim($input);

        if (preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $input)) {
            return $input;
        }

        if (preg_match('/(?:youtube\.com\/channel\/)(UC[a-zA-Z0-9_-]{22})/i', $input, $matches)) {
            return $matches[1];
        }

        $url = $input;
        if (str_starts_with($url, '@')) {
            $url = 'https://www.youtube.com/' . $url;
        } elseif (!preg_match('~^https?://~i', $url)) {
            $url = 'https://www.youtube.com/@' . ltrim($url, '/');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cookie: SOCS=CAESEwgDEgk0ODE3Nzk3MjQaAmVuIAEaBgiA_LyaBg;'
        ]);
        
        $html = curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($finalUrl && preg_match('/(?:youtube\.com\/channel\/)(UC[a-zA-Z0-9_-]{22})/i', $finalUrl, $m)) {
            return $m[1];
        }

        if (!empty($html)) {
            if (preg_match('/feeds\/videos\.xml\?channel_id=(UC[a-zA-Z0-9_-]{22})/i', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/<link\s+rel="canonical"\s+href="https:\/\/www\.youtube\.com\/channel\/(UC[a-zA-Z0-9_-]{22})"/i', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/"channelMetadataRenderer":\{[^}]*"externalId":"(UC[a-zA-Z0-9_-]{22})"/i', $html, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}

/**
 * Master Hybrid Fetcher for YouTube (Tier 1: RSS -> Tier 2: OAuth API -> Tier 3: Direct Scraper)
 */
if (!function_exists('fetchYoutubeHybrid')) {
    function fetchYoutubeHybrid(array $auto, PDO $db): array {
        $channelId = $auto['source_identifier'];
        if (empty($channelId) && !empty($auto['source_platform_user_id'])) {
            $channelId = $auto['source_platform_user_id'];
        }

        // Auto-heal non-UC identifiers
        if (!empty($channelId) && !preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $channelId)) {
            $resolvedId = resolveYoutubeChannelIdWorker($channelId);
            if ($resolvedId) {
                $channelId = $resolvedId;
                $upStmt = $db->prepare("UPDATE channel_automations SET source_identifier = ? WHERE id = ?");
                $upStmt->execute([$channelId, $auto['id']]);
            }
        }

        if (empty($channelId)) {
            return [];
        }

        // --- TIER 1: Official RSS Feed (Preferred, zero bot detection footprint) ---
        $feedUrl = "https://www.youtube.com/feeds/videos.xml?channel_id=" . urlencode($channelId);
        $httpCode = 0;
        $xmlData = fetchSafeYoutubeFeed($feedUrl, $httpCode);

        if ($httpCode === 200 && !empty($xmlData)) {
            $xml = @simplexml_load_string($xmlData);
            if ($xml && isset($xml->entry)) {
                $rssItems = [];
                foreach ($xml->entry as $entry) {
                    $title = trim((string)$entry->title);
                    $remoteId = (string)($entry->children('yt', true)->videoId ?? '');
                    if (empty($remoteId)) {
                        $remoteId = (string)$entry->id;
                    }

                    $link = '';
                    foreach ($entry->link as $l) {
                        if ((string)$l['rel'] === 'alternate') {
                            $link = (string)$l['href'];
                            break;
                        }
                    }
                    if (empty($link) && isset($entry->link['href'])) {
                        $link = (string)$entry->link['href'];
                    }
                    if (empty($link) && !empty($remoteId)) {
                        $link = 'https://www.youtube.com/watch?v=' . $remoteId;
                    }

                    if (!empty($title) && !empty($link)) {
                        $rssItems[] = [
                            'remote_id' => $remoteId,
                            'title'     => $title, 
                            'link'      => $link
                        ];
                    }
                }

                if (!empty($rssItems)) {
                    echo "   * [TIER 1 - RSS] Successfully fetched " . count($rssItems) . " videos via official feed.\n";
                    return $rssItems;
                }
            }
        }

        // --- TIER 2: Official Google Data API (For connected OAuth accounts) ---
        if (($auto['source_plugin_id'] ?? '') === 'youtube' && !empty($auto['source_access_token'])) {
            $tokenData = json_decode($auto['source_access_token'], true);
            $accessToken = is_array($tokenData) ? ($tokenData['token'] ?? '') : $auto['source_access_token'];

            if (!empty($accessToken)) {
                $uploadsPlaylistId = 'UU' . substr($channelId, 2);
                $apiUrl = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId={$uploadsPlaylistId}&maxResults=15";
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}", "Accept: application/json"]);
                $apiRaw = curl_exec($ch);
                $apiCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $apiData = json_decode($apiRaw, true) ?? [];
                if ($apiCode === 200 && !empty($apiData['items'])) {
                    $apiItems = [];
                    foreach ($apiData['items'] as $item) {
                        $vId = $item['snippet']['resourceId']['videoId'] ?? '';
                        $title = $item['snippet']['title'] ?? '';
                        if (!empty($vId)) {
                            $apiItems[] = [
                                'remote_id' => $vId,
                                'title'     => trim($title),
                                'link'      => "https://www.youtube.com/watch?v={$vId}"
                            ];
                        }
                    }
                    if (!empty($apiItems)) {
                        echo "   * [TIER 2 - API] Successfully fetched " . count($apiItems) . " videos via Google OAuth API.\n";
                        return $apiItems;
                    }
                }
            }
        }

        // --- TIER 3: Direct Web Scraper Fallback (Only when RSS is 404 or down) ---
        echo "   * [TIER 3 - FALLBACK] Official RSS returned {$httpCode}. Falling back to direct page parsing...\n";
        $directItems = fetchYoutubeVideosDirect($channelId, $auto['source_title'] ?? '');
        echo "   * [TIER 3 - FALLBACK] Retrieved " . count($directItems) . " videos/shorts directly.\n";

        return $directItems;
    }
}

echo "[START] Channel Automations Worker started at " . gmdate('Y-m-d H:i:s') . " UTC\n";

$automations = ChannelAutomation::getAllForCron();

if (empty($automations)) {
    echo "No channel automations to process.\n";
    return;
}

$db = Database::getInstance();

foreach ($automations as $auto) {
    echo "Processing automation ID [{$auto['id']}]: {$auto['source_title']}\n";

    $intervalHours = max(1, (int)($auto['post_interval_hours'] ?? 1));

    // Verify elapsed interval with detailed countdown
    if (!empty($auto['last_checked'])) {
        $lastCheckedTimestamp = strtotime($auto['last_checked'] . ' UTC');
        $intervalSeconds = $intervalHours * 3600;
        $nextAllowedRun = $lastCheckedTimestamp + $intervalSeconds;
        $currentTime = time();

        if ($currentTime < $nextAllowedRun) {
            $minutesRemaining = ceil(($nextAllowedRun - $currentTime) / 60);
            echo " - Skipped: Interval of {$intervalHours}h not yet elapsed.\n";
            echo "   * DB last_checked: " . $auto['last_checked'] . "\n";
            echo "   * Next run after:  " . gmdate('Y-m-d H:i:s', $nextAllowedRun) . " UTC ({$minutesRemaining} minutes remaining)\n\n";
            continue;
        }
    }

    if (!UsageManager::canPublishChannelPost($auto['user_id'])) {
        echo " - Skipped: User ID {$auto['user_id']} reached dedicated channel automation post quota.\n";
        ChannelAutomation::updateLastChecked($auto['id']);
        continue;
    }

    $targetAccounts = json_decode($auto['target_accounts'], true);
    if (!is_array($targetAccounts) || empty($targetAccounts)) {
        echo " - Error: Invalid target accounts.\n";
        ChannelAutomation::updateLastChecked($auto['id']);
        continue;
    }

    $items = [];

    // 1. YouTube Source using 3-Tier Resilient Hybrid Engine
    if ($auto['source_type'] === 'youtube_url' || ($auto['source_plugin_id'] ?? '') === 'youtube') {
        $items = fetchYoutubeHybrid($auto, $db);
    } 
    // 2. Facebook Page Source
    elseif (($auto['source_plugin_id'] ?? '') === 'facebook') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $pageAccessToken = $tokenData['token'] ?? $auto['source_access_token'];
        $pageId = $tokenData['page_id'] ?? ($auto['source_platform_user_id'] ?? 'me');

        $fbUrl = "https://graph.facebook.com/v19.0/{$pageId}/posts?fields=id,message,permalink_url,created_time&limit=15&access_token=" . urlencode($pageAccessToken);
        $ch = curl_init($fbUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!empty($res['data'])) {
            foreach ($res['data'] as $post) {
                $msg = trim($post['message'] ?? '');
                $link = $post['permalink_url'] ?? '';
                if (!empty($link)) {
                    $items[] = [
                        'remote_id' => (string)($post['id'] ?? ''),
                        'title'     => !empty($msg) ? mb_substr($msg, 0, 100) . '...' : 'New Facebook Post',
                        'link'      => $link
                    ];
                }
            }
        }
    }
    // 3. Instagram Business Source
    elseif (($auto['source_plugin_id'] ?? '') === 'instagram') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $igToken = $tokenData['token'] ?? $auto['source_access_token'];
        $igId = $tokenData['ig_id'] ?? ($auto['source_platform_user_id'] ?? '');

        if (!empty($igId)) {
            $igUrl = "https://graph.facebook.com/v19.0/{$igId}/media?fields=id,caption,permalink,timestamp&limit=15&access_token=" . urlencode($igToken);
            $ch = curl_init($igUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!empty($res['data'])) {
                foreach ($res['data'] as $media) {
                    $caption = trim($media['caption'] ?? '');
                    $link = $media['permalink'] ?? '';
                    if (!empty($link)) {
                        $items[] = [
                            'remote_id' => (string)($media['id'] ?? ''),
                            'title'     => !empty($caption) ? mb_substr($caption, 0, 100) . '...' : 'New Instagram Post',
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }
    // 4. LinkedIn (Pages) Source
    elseif (($auto['source_plugin_id'] ?? '') === 'linkedin_pages') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $liToken = $tokenData['token'] ?? $auto['source_access_token'];
        $orgUrn = $tokenData['org_urn'] ?? ($auto['source_platform_user_id'] ?? '');

        if (!empty($orgUrn)) {
            $encodedAuthor = urlencode($orgUrn);
            $liUrl = "https://api.linkedin.com/rest/posts?author={$encodedAuthor}&q=author&count=10";
            $ch = curl_init($liUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $chResponse = curl_exec($ch);
            curl_close($ch);

            $liRes = json_decode($chResponse, true);

            if (!empty($liRes['elements'])) {
                foreach ($liRes['elements'] as $element) {
                    $commentary = trim($element['commentary'] ?? '');
                    $postId = $element['id'] ?? '';
                    if (!empty($postId)) {
                        $link = "https://www.linkedin.com/feed/update/" . urlencode($postId);
                        $items[] = [
                            'remote_id' => (string)$postId,
                            'title'     => !empty($commentary) ? mb_substr($commentary, 0, 100) . '...' : 'New LinkedIn Post',
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }
    // 5. Tumblr Blog Source
    elseif (($auto['source_plugin_id'] ?? '') === 'tumblr') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $tumblrToken = $tokenData['token'] ?? $auto['source_access_token'];
        $blogName = $tokenData['blog_name'] ?? ($auto['source_platform_user_id'] ?? '');

        if (!empty($blogName)) {
            if (!str_contains($blogName, '.')) {
                $blogName .= '.tumblr.com';
            }

            $tumblrUrl = "https://api.tumblr.com/v2/blog/{$blogName}/posts?limit=10";
            $ch = curl_init($tumblrUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . trim($tumblrToken),
                'Accept: application/json'
            ]);
            $tumblrRes = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!empty($tumblrRes['response']['posts'])) {
                foreach ($tumblrRes['response']['posts'] as $post) {
                    $link = $post['post_url'] ?? ($post['short_url'] ?? '');
                    $summary = trim($post['summary'] ?? ($post['slug'] ?? 'New Tumblr Post'));
                    if (!empty($link)) {
                        $items[] = [
                            'remote_id' => (string)($post['id'] ?? ''),
                            'title'     => mb_substr($summary, 0, 100),
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }
    // 6. WordPress REST API Source
    elseif (($auto['source_plugin_id'] ?? '') === 'wordpress') {
        $wpData = json_decode($auto['source_access_token'], true);
        $siteUrl = rtrim($wpData['site_url'] ?? '', '/');
        $username = $wpData['username'] ?? '';
        $appPassword = $wpData['app_password'] ?? '';

        if (!empty($siteUrl)) {
            $wpApiUrl = "{$siteUrl}/wp-json/wp/v2/posts?per_page=10&status=publish";
            $ch = curl_init($wpApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            
            $headers = ['Accept: application/json'];
            if (!empty($username) && !empty($appPassword)) {
                $headers[] = 'Authorization: Basic ' . base64_encode("{$username}:{$appPassword}");
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $wpRes = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (is_array($wpRes)) {
                foreach ($wpRes as $post) {
                    $link = $post['link'] ?? '';
                    $titleRendered = $post['title']['rendered'] ?? 'New WordPress Post';
                    $cleanTitle = trim(html_entity_decode(strip_tags($titleRendered), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if (!empty($link)) {
                        $items[] = [
                            'remote_id' => (string)($post['id'] ?? ''),
                            'title'     => mb_substr($cleanTitle, 0, 100),
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }
    // 7. Twitch Channel Source (VODs & Videos)
    elseif (($auto['source_plugin_id'] ?? '') === 'twitch') {
        require_once BASE_PATH . 'plugins/Twitch/TwitchPlugin.php';
        $twitchPlugin = new TwitchPlugin();
        $tokenInfo = $twitchPlugin->getValidAccessToken($auto['source_access_token']);
        $token = $tokenInfo['access_token'] ?? '';
        $clientId = $tokenInfo['client_id'] ?? '';
        $broadcasterId = $tokenInfo['broadcaster_id'] ?? ($auto['source_platform_user_id'] ?? '');

        if (!empty($token) && !empty($clientId) && !empty($broadcasterId)) {
            $twitchUrl = "https://api.twitch.tv/helix/videos?user_id=" . urlencode($broadcasterId) . "&first=10&type=all";
            $ch = curl_init($twitchUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Client-Id: ' . $clientId,
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]);
            $twitchRes = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!empty($twitchRes['data'])) {
                foreach ($twitchRes['data'] as $video) {
                    $link = $video['url'] ?? '';
                    $title = trim($video['title'] ?? 'New Twitch Video');
                    if (!empty($link)) {
                        $items[] = [
                            'remote_id' => (string)($video['id'] ?? ''),
                            'title'     => mb_substr($title, 0, 100),
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }
    // 8. Medium User Feed Source
    elseif (($auto['source_plugin_id'] ?? '') === 'medium') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $username = $tokenData['username'] ?? '';

        if (!empty($username)) {
            $mediumFeedUrl = "https://medium.com/feed/@" . ltrim($username, '@');
            $xmlContext = stream_context_create([
                'http' => ['timeout' => 15, 'user_agent' => 'MySocialManager/1.0']
            ]);
            $xmlData = @file_get_contents($mediumFeedUrl, false, $xmlContext);
            if ($xmlData) {
                $xml = @simplexml_load_string($xmlData);
                if ($xml && isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $itemNode) {
                        $title = trim((string)$itemNode->title);
                        $link = trim((string)$itemNode->link);
                        $guid = trim((string)($itemNode->guid ?? ''));
                        $remoteId = !empty($guid) ? $guid : $link;

                        if (!empty($title) && !empty($link)) {
                            $items[] = [
                                'remote_id' => $remoteId,
                                'title'     => mb_substr($title, 0, 100),
                                'link'      => $link
                            ];
                        }
                    }
                }
            }
        }
    }
    // 9. MYETV Network / Account Source Feed
    elseif (($auto['source_plugin_id'] ?? '') === 'myetv') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $realToken = is_array($tokenData) ? ($tokenData['token'] ?? $auto['source_access_token']) : $auto['source_access_token'];
        $feedUrl = is_array($tokenData) ? ($tokenData['feed_url'] ?? null) : null;

        if (empty($feedUrl)) {
            $ch = curl_init('https://developers.myetv.tv/api/v1/profile.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$realToken}",
                "Accept: application/json"
            ]);
            $profRes = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!empty($profRes['username'])) {
                $type = (!empty($profRes['type']) && strtolower($profRes['type']) === 'network') ? 'network' : 'account';
                $feedUrl = "https://feed.myetv.tv/{$type}/" . rawurlencode($profRes['username']);
            }
        }

        if (!empty($feedUrl)) {
            $xmlContext = stream_context_create([
                'http' => ['timeout' => 15, 'user_agent' => 'MySocialManager/1.0']
            ]);
            $xmlData = @file_get_contents($feedUrl, false, $xmlContext);
            if ($xmlData) {
                $xml = @simplexml_load_string($xmlData);
                if ($xml) {
                    $rawItems = $xml->channel->item ?? ($xml->entry ?? []);
                    foreach ($rawItems as $itemNode) {
                        $title = trim((string)$itemNode->title);
                        $link = '';
                        if (isset($itemNode->link['href'])) {
                            $link = trim((string)$itemNode->link['href']);
                        } elseif (isset($itemNode->link)) {
                            $link = trim((string)$itemNode->link);
                        }
                        $guid = trim((string)($itemNode->guid ?? ($itemNode->id ?? '')));
                        $remoteId = !empty($guid) ? $guid : $link;

                        if (!empty($title) && !empty($link)) {
                            $items[] = [
                                'remote_id' => $remoteId,
                                'title'     => mb_substr($title, 0, 100),
                                'link'      => $link
                            ];
                        }
                    }
                }
            }
        }
    }
    // 10. Threads (Meta) Source
    elseif (($auto['source_plugin_id'] ?? '') === 'threads') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $realToken = is_array($tokenData) ? ($tokenData['access_token'] ?? $auto['source_access_token']) : $auto['source_access_token'];
        $userId = is_array($tokenData) ? ($tokenData['threads_user_id'] ?? 'me') : 'me';

        if (!empty($realToken)) {
            $threadsUrl = "https://graph.threads.net/v1.0/{$userId}/threads?fields=id,text,permalink,timestamp&limit=10&access_token=" . urlencode($realToken);
            $ch = curl_init($threadsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $chResponse = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($chResponse, true);
            if (!empty($res['data'])) {
                foreach ($res['data'] as $thread) {
                    $text = trim($thread['text'] ?? '');
                    $link = $thread['permalink'] ?? '';
                    if (!empty($link)) {
                        $items[] = [
                            'remote_id' => (string)($thread['id'] ?? ''),
                            'title'     => !empty($text) ? mb_substr($text, 0, 100) . '...' : 'New Thread',
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }
    // 11. Dev.to Community Source
    elseif (($auto['source_plugin_id'] ?? '') === 'devto') {
        $tokenData = json_decode($auto['source_access_token'], true);
        $apiKey = is_array($tokenData) ? ($tokenData['api_key'] ?? $auto['source_access_token']) : $auto['source_access_token'];

        if (!empty($apiKey)) {
            $devtoUrl = "https://dev.to/api/articles/me/published?per_page=10";
            $ch = curl_init($devtoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $chResponse = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($chResponse, true);
            if (is_array($res)) {
                foreach ($res as $article) {
                    $link = $article['url'] ?? ($article['canonical_url'] ?? '');
                    $title = trim($article['title'] ?? 'New Dev.to Article');
                    if (!empty($link)) {
                        $items[] = [
                            'remote_id' => (string)($article['id'] ?? ''),
                            'title'     => mb_substr($title, 0, 100),
                            'link'      => $link
                        ];
                    }
                }
            }
        }
    }

    if (empty($items)) {
        echo " - No items found for source.\n\n";
        continue;
    }

    $onlyNewItems        = !empty($auto['only_new_items']);
    $randomRepostEnabled = !empty($auto['random_repost_enabled']);
    $randomRepostLimit   = max(10, (int)($auto['random_repost_limit'] ?? 10));
    $isFirstCheck        = empty($auto['last_checked']);

    if ($onlyNewItems && $isFirstCheck) {
        echo " - 'Only new items' active on first check. Skipping initial sync.\n";
        ChannelAutomation::updateLastChecked($auto['id']);
        continue;
    }

    $itemsToProcess = $items;
    if ($randomRepostEnabled && !empty($items)) {
        $pool = array_slice($items, 0, $randomRepostLimit);
        $itemsToProcess = [$pool[array_rand($pool)]];
    }

    $addedCount = 0;
    $currentPublishTime = time();

    // Cache target account details for domain loop check
    $targetAccountMeta = [];
    if (!empty($targetAccounts)) {
        $inClause = implode(',', array_map('intval', $targetAccounts));
        $metaStmt = $db->query("SELECT id, plugin_id, access_token, platform_user_id FROM social_accounts WHERE id IN ({$inClause})");
        while ($row = $metaStmt->fetch(PDO::FETCH_ASSOC)) {
            $targetAccountMeta[$row['id']] = $row;
        }
    }

    foreach ($itemsToProcess as $item) {
        if (!UsageManager::canPublishChannelPost($auto['user_id'])) {
            echo " - User channel automation post quota reached.\n";
            break;
        }

        // ==========================================
        // ANTI-LOOP CHECK 1: System-generated Echo
        // ==========================================
        if (!empty($item['remote_id'])) {
            try {
                $echoCheck = $db->prepare("SELECT id FROM posts WHERE external_post_id = ?");
                $echoCheck->execute([(string)$item['remote_id']]);
                if ($echoCheck->fetch()) {
                    echo " - [ANTI-LOOP] Skipped: Item Remote ID [{$item['remote_id']}] was originally published by this system. Loop prevented.\n";
                    continue;
                }
            } catch (\PDOException $e) {}
        }

        $postContent = $item['title'] . "\n\n" . $item['link'];
        $itemHost = parse_url($item['link'], PHP_URL_HOST);

        foreach ($targetAccounts as $accountId) {
            // Never re-target the exact same account as the source
            if (!empty($auto['source_account_id']) && (int)$accountId === (int)$auto['source_account_id']) {
                continue;
            }

            // ==========================================
            // ANTI-LOOP CHECK 2: Domain Loop Prevention
            // ==========================================
            $targetMeta = $targetAccountMeta[$accountId] ?? null;
            if ($targetMeta && !empty($itemHost)) {
                $targetDecoded = json_decode($targetMeta['access_token'] ?? '', true);
                $targetDomain = null;

                if ($targetMeta['plugin_id'] === 'wordpress' && !empty($targetDecoded['site_url'])) {
                    $targetDomain = parse_url($targetDecoded['site_url'], PHP_URL_HOST);
                } elseif ($targetMeta['plugin_id'] === 'tumblr' && !empty($targetDecoded['blog_name'])) {
                    $targetDomain = $targetDecoded['blog_name'];
                } elseif ($targetMeta['plugin_id'] === 'myetv') {
                    $targetDomain = 'myetv.tv';
                } elseif ($targetMeta['plugin_id'] === 'threads') {
                    $targetDomain = 'threads.net';
                } elseif ($targetMeta['plugin_id'] === 'devto') {
                    $targetDomain = 'dev.to';
                }

                if (!empty($targetDomain) && (strtolower($itemHost) === strtolower($targetDomain) || str_contains(strtolower($itemHost), strtolower($targetDomain)))) {
                    echo " - [ANTI-LOOP] Skipped target Account ID [{$accountId}]: Target domain [{$targetDomain}] matches item origin [{$itemHost}].\n";
                    continue;
                }
            }

            // Check for duplicate post by link
            if (!$randomRepostEnabled) {
                $checkStmt = $db->prepare("SELECT id FROM posts WHERE social_account_id = ? AND (source_link = ? OR content LIKE ?)");
                $checkStmt->execute([$accountId, $item['link'], "%" . $item['link'] . "%"]);
                if ($checkStmt->fetch()) {
                    continue;
                }
            }

            $scheduledDate = date('Y-m-d H:i:s', $currentPublishTime);

            try {
                $insertStmt = $db->prepare("
                    INSERT INTO posts (user_id, social_account_id, content, status, scheduled_for, scheduled_at, source_link, is_rss, is_channel_automation) 
                    VALUES (?, ?, ?, 'scheduled', ?, ?, ?, 0, 1)
                ");
                $insertStmt->execute([
                    $auto['user_id'],
                    $accountId,
                    $postContent,
                    $scheduledDate,
                    $scheduledDate,
                    $item['link']
                ]);
            } catch (\PDOException $e) {
                $insertStmtFallback = $db->prepare("
                    INSERT INTO posts (user_id, social_account_id, content, status, scheduled_for, scheduled_at, source_link, is_rss) 
                    VALUES (?, ?, ?, 'scheduled', ?, ?, ?, 0)
                ");
                $insertStmtFallback->execute([
                    $auto['user_id'],
                    $accountId,
                    $postContent,
                    $scheduledDate,
                    $scheduledDate,
                    $item['link']
                ]);
            }

            $addedCount++;
        }

        $currentPublishTime += ($intervalHours * 3600);
    }

    echo " - Added {$addedCount} scheduled posts to the queue.\n";
    ChannelAutomation::updateLastChecked($auto['id']);
    echo " - Feed ID {$auto['id']} successfully checked and updated.\n\n";

    // Anti-burst pacing between different channels (0.5s pause)
    usleep(500000);
}

echo "[END] Channel Automations Worker finished at " . gmdate('Y-m-d H:i:s') . " UTC\n";