<?php
// app/Services/MediaFallbackService.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/PluginSetting.php';
require_once __DIR__ . '/../Models/SocialAccount.php';

class MediaFallbackService {

    /**
     * Networks that strictly require at least one image
     */
    private static array $imageRequiredNetworks = ['instagram', 'pinterest'];

    /**
     * Networks that strictly require a video file
     */
    private static array $videoRequiredNetworks = ['youtube', 'tiktok'];

    /**
     * Common video file extensions
     */
    private static array $videoExtensions = ['mp4', 'mov', 'webm', 'avi', 'mkv', 'm4v', 'flv', '3gp'];

    /**
     * Check if a plugin strictly requires an image
     */
    public static function isImageRequired(string $pluginId): bool {
        return in_array(strtolower(trim($pluginId)), self::$imageRequiredNetworks, true);
    }

    /**
     * Check if a plugin strictly requires a video
     */
    public static function isVideoRequired(string $pluginId): bool {
        return in_array(strtolower(trim($pluginId)), self::$videoRequiredNetworks, true);
    }

    /**
     * Check if a plugin requires any mandatory media (image or video)
     */
    public static function isMediaRequired(string $pluginId): bool {
        return self::isImageRequired($pluginId) || self::isVideoRequired($pluginId);
    }

    /**
     * Check if a given URL or file path points to a video
     */
    public static function isVideoUrl(string $url): bool {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) return false;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::$videoExtensions, true);
    }

    /**
     * Get configured default image for a given plugin
     */
    public static function getDefaultImage(string $pluginId): ?string {
        $settings = PluginSetting::getForPlugin($pluginId);
        $defaultImg = trim($settings['default_image'] ?? '');

        if (empty($defaultImg)) {
            return null;
        }

        return self::ensureAbsoluteUrl($defaultImg);
    }

    /**
     * Get configured default video for a given plugin
     */
    public static function getDefaultVideo(string $pluginId): ?string {
        $settings = PluginSetting::getForPlugin($pluginId);
        $defaultVideo = trim($settings['default_video'] ?? '');

        if (empty($defaultVideo)) {
            return null;
        }

        return self::ensureAbsoluteUrl($defaultVideo);
    }

    /**
     * Resolve media attachments (image or video) for a post if empty or invalid for the target network.
     * Priority: Account-level custom override -> Scraped media from link -> Global Admin plugin default.
     */
    public static function resolvePostMedia(array $post, string $pluginId): array {
        $userId = (int)($post['user_id'] ?? 0);
        $content = $post['content'] ?? '';
        $sourceLink = !empty($post['source_link']) ? trim($post['source_link']) : null;
        $accountId = (int)($post['social_account_id'] ?? 0);

        // Fetch per-account custom override if available
        $accountCustomMedia = null;
        if ($accountId > 0 && $userId > 0) {
            $accountCustomMedia = SocialAccount::getDefaultMedia($accountId, $userId);
        }

        // 1. If not specified in source_link, search for first URL inside post content
        if (empty($sourceLink) || str_starts_with($sourceLink, 'mailing_list:')) {
            $sourceLink = self::extractFirstUrl($content);
        }

        // =========================================================================
        // A. VIDEO RESOLUTION (For YouTube, TikTok)
        // =========================================================================
        if (self::isVideoRequired($pluginId)) {
            // Check account-level video override first
            if (!empty($accountCustomMedia) && self::isVideoUrl($accountCustomMedia)) {
                return [self::ensureAbsoluteUrl($accountCustomMedia)];
            }

            // Attempt to scrape a video from the link
            if (!empty($sourceLink) && filter_var($sourceLink, FILTER_VALIDATE_URL)) {
                $coverVideoUrl = self::fetchCoverVideoFromUrl($sourceLink);

                if (!empty($coverVideoUrl)) {
                    $localStoredVideo = self::downloadAndStoreVideoInFileManager($coverVideoUrl, $userId);
                    if (!empty($localStoredVideo)) {
                        return [$localStoredVideo];
                    }
                    return [$coverVideoUrl];
                }
            }

            // Fallback to configured global default video in Admin plugin settings
            $defaultVideo = self::getDefaultVideo($pluginId);
            if (!empty($defaultVideo)) {
                return [$defaultVideo];
            }

            return [];
        }

        // =========================================================================
        // B. IMAGE RESOLUTION (For Instagram, Pinterest)
        // =========================================================================
        if (self::isImageRequired($pluginId)) {
            // Check account-level image override first
            if (!empty($accountCustomMedia)) {
                return [self::ensureAbsoluteUrl($accountCustomMedia)];
            }

            // Attempt to scrape an image from the link
            if (!empty($sourceLink) && filter_var($sourceLink, FILTER_VALIDATE_URL)) {
                $coverImageUrl = self::fetchCoverImageFromUrl($sourceLink);

                if (!empty($coverImageUrl)) {
                    $localStoredUrl = self::downloadAndStoreInFileManager($coverImageUrl, $userId);
                    if (!empty($localStoredUrl)) {
                        return [$localStoredUrl];
                    }
                    return [$coverImageUrl];
                }
            }

            // Fallback to configured global default image in Admin plugin settings
            $defaultPluginImage = self::getDefaultImage($pluginId);
            if (!empty($defaultPluginImage)) {
                return [$defaultPluginImage];
            }
        }

        return [];
    }

    /**
     * Extract the first HTTP/HTTPS URL from a text string
     */
    public static function extractFirstUrl(string $text): ?string {
        if (preg_match('~https?://[^\s<>"\']+~i', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Scrape OpenGraph / Twitter Card cover image from a target webpage
     */
    public static function fetchCoverImageFromUrl(string $url): ?string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!$html) {
            return null;
        }

        // If the URL itself directly points to an image
        if ($contentType && str_starts_with($contentType, 'image/')) {
            return $url;
        }

        // YouTube thumbnail shortcut
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $url, $ytMatch)) {
            return 'https://img.youtube.com/vi/' . $ytMatch[1] . '/maxresdefault.jpg';
        }

        // Extract OpenGraph og:image
        if (preg_match('/<meta[^>]+(?:property|name)=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::normalizeScrapedUrl(trim($m[1]), $url);
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']og:image["\']/i', $html, $m)) {
            return self::normalizeScrapedUrl(trim($m[1]), $url);
        }

        // Extract Twitter Card image
        if (preg_match('/<meta[^>]+(?:name|property)=["\']twitter:image(?:|:src)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::normalizeScrapedUrl(trim($m[1]), $url);
        }

        // Extract link rel="image_src"
        if (preg_match('/<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::normalizeScrapedUrl(trim($m[1]), $url);
        }

        return null;
    }

    /**
     * Scrape OpenGraph og:video or HTML5 <video> from a target webpage
     */
    public static function fetchCoverVideoFromUrl(string $url): ?string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!$html) {
            return null;
        }

        // If the URL itself directly points to a video
        if ($contentType && str_starts_with($contentType, 'video/')) {
            return $url;
        }

        // Extract og:video or og:video:secure_url
        if (preg_match('/<meta[^>]+(?:property|name)=["\']og:video(?::secure_url|:url)?["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $candidate = self::normalizeScrapedUrl(trim($m[1]), $url);
            if (self::isVideoUrl($candidate)) {
                return $candidate;
            }
        }

        // Extract twitter:player:stream
        if (preg_match('/<meta[^>]+(?:name|property)=["\']twitter:player:stream["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $candidate = self::normalizeScrapedUrl(trim($m[1]), $url);
            if (self::isVideoUrl($candidate)) {
                return $candidate;
            }
        }

        // Extract HTML5 <video><source src="...">
        if (preg_match('/<source[^>]+src=["\']([^"\']+\.(?:mp4|webm|mov))["\']/i', $html, $m)) {
            return self::normalizeScrapedUrl(trim($m[1]), $url);
        }

        return null;
    }

    /**
     * Download an image and store it into the user's File Manager directory
     */
    public static function downloadAndStoreInFileManager(string $imageUrl, int $userId): ?string {
        if ($userId <= 0) return null;

        $basePath = defined('BASE_PATH') ? BASE_PATH : (dirname(__DIR__, 2) . '/');
        $userFolder = 'user_' . $userId;
        $targetDir = $basePath . 'public/assets/My-File-Manager/files/' . $userFolder . '/';

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0775, true)) return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($imageData)) {
            return null;
        }

        $ext = 'jpg';
        if ($contentType) {
            if (str_contains($contentType, 'png')) $ext = 'png';
            elseif (str_contains($contentType, 'webp')) $ext = 'webp';
            elseif (str_contains($contentType, 'gif')) $ext = 'gif';
            elseif (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) $ext = 'jpg';
        }

        $filename = 'cover_' . date('Ymd_His') . '_' . substr(md5($imageUrl . microtime()), 0, 8) . '.' . $ext;
        $filePath = $targetDir . $filename;

        if (@file_put_contents($filePath, $imageData) === false) {
            return null;
        }

        return rtrim(Config::APP_URL, '/') . '/public/assets/My-File-Manager/files/' . $userFolder . '/' . $filename;
    }

    /**
     * Download a video file and store it into the user's File Manager directory (safely bounded)
     */
    public static function downloadAndStoreVideoInFileManager(string $videoUrl, int $userId): ?string {
        if ($userId <= 0) return null;

        $basePath = defined('BASE_PATH') ? BASE_PATH : (dirname(__DIR__, 2) . '/');
        $userFolder = 'user_' . $userId;
        $targetDir = $basePath . 'public/assets/My-File-Manager/files/' . $userFolder . '/';

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0775, true)) return null;
        }

        // Determine extension
        $path = parse_url($videoUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::$videoExtensions, true)) {
            $ext = 'mp4';
        }

        $filename = 'video_' . date('Ymd_His') . '_' . substr(md5($videoUrl . microtime()), 0, 8) . '.' . $ext;
        $filePath = $targetDir . $filename;

        // Stream download with 100MB safety ceiling
        $fp = @fopen($filePath, 'w+');
        if (!$fp) return null;

        $ch = curl_init($videoUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200 || filesize($filePath) < 1024) {
            @unlink($filePath);
            return null;
        }

        return rtrim(Config::APP_URL, '/') . '/public/assets/My-File-Manager/files/' . $userFolder . '/' . $filename;
    }

    /**
     * Resolve relative URLs to absolute HTTP/HTTPS URLs
     */
    private static function normalizeScrapedUrl(string $src, string $pageUrl): string {
        $src = html_entity_decode(trim($src));

        if (str_starts_with($src, '//')) {
            return 'https:' . $src;
        }

        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }

        $parsed = parse_url($pageUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if (str_starts_with($src, '/')) {
            return $scheme . '://' . $host . $src;
        }

        $path = $parsed['path'] ?? '/';
        $dir = rtrim(dirname($path), '/');
        return $scheme . '://' . $host . ($dir ? $dir . '/' : '/') . $src;
    }

    /**
     * Ensure a relative local path becomes a full URL
     */
    public static function ensureAbsoluteUrl(string $url): string {
        if (!preg_match('~^https?://~i', $url)) {
            return rtrim(Config::APP_URL, '/') . '/' . ltrim($url, '/');
        }
        return $url;
    }
}
