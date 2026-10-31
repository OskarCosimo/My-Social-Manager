<?php
// app/Services/MediaFallbackService.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/PluginSetting.php';

class MediaFallbackService {

    /**
     * Networks that strictly require at least one media image
     */
    private static array $mediaRequiredNetworks = ['instagram', 'pinterest'];

    /**
     * Check if a plugin requires at least one media file
     */
    public static function isMediaRequired(string $pluginId): bool {
        return in_array(strtolower(trim($pluginId)), self::$mediaRequiredNetworks, true);
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
     * Resolve media attachments for a post if empty and required by the platform
     */
    public static function resolvePostMedia(array $post, string $pluginId): array {
        $userId = (int)($post['user_id'] ?? 0);
        $content = $post['content'] ?? '';
        $sourceLink = !empty($post['source_link']) ? trim($post['source_link']) : null;

        // 1. If not specified in source_link, search for first URL inside post content
        if (empty($sourceLink) || str_starts_with($sourceLink, 'mailing_list:')) {
            $sourceLink = self::extractFirstUrl($content);
        }

        // 2. If a valid external URL is found, attempt to scrape its cover image
        if (!empty($sourceLink) && filter_var($sourceLink, FILTER_VALIDATE_URL)) {
            $coverImageUrl = self::fetchCoverImageFromUrl($sourceLink);

            if (!empty($coverImageUrl)) {
                // Download and store the cover image into the user's File Manager directory
                $localStoredUrl = self::downloadAndStoreInFileManager($coverImageUrl, $userId);
                if (!empty($localStoredUrl)) {
                    return [$localStoredUrl];
                }
                
                // Fallback to direct external URL if local file manager download fails
                return [$coverImageUrl];
            }
        }

        // 3. Fallback to default plugin image configured in the Admin area
        $defaultPluginImage = self::getDefaultImage($pluginId);
        if (!empty($defaultPluginImage)) {
            return [$defaultPluginImage];
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

        // Extract OpenGraph og:image (property or name)
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
     * Download an image and store it into the user's File Manager directory
     */
    public static function downloadAndStoreInFileManager(string $imageUrl, int $userId): ?string {
        if ($userId <= 0) {
            return null;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : (dirname(__DIR__, 2) . '/');
        $userFolder = 'user_' . $userId;
        $targetDir = $basePath . 'public/assets/My-File-Manager/files/' . $userFolder . '/';

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0775, true)) {
                return null;
            }
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

        // Resolve file extension
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

        // Return the full public URL reachable by social networks
        return rtrim(Config::APP_URL, '/') . '/public/assets/My-File-Manager/files/' . $userFolder . '/' . $filename;
    }

    /**
     * Resolve relative URLs to absolute HTTP/HTTPS URLs
     */
    private static function normalizeScrapedUrl(string $imageSrc, string $pageUrl): string {
        $imageSrc = html_entity_decode(trim($imageSrc));

        if (str_starts_with($imageSrc, '//')) {
            return 'https:' . $imageSrc;
        }

        if (str_starts_with($imageSrc, 'http://') || str_starts_with($imageSrc, 'https://')) {
            return $imageSrc;
        }

        $parsed = parse_url($pageUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if (str_starts_with($imageSrc, '/')) {
            return $scheme . '://' . $host . $imageSrc;
        }

        $path = $parsed['path'] ?? '/';
        $dir = rtrim(dirname($path), '/');
        return $scheme . '://' . $host . ($dir ? $dir . '/' : '/') . $imageSrc;
    }

    /**
     * Ensure a relative local path becomes a full URL
     */
    private static function ensureAbsoluteUrl(string $url): string {
        if (!preg_match('~^https?://~i', $url)) {
            return rtrim(Config::APP_URL, '/') . '/' . ltrim($url, '/');
        }
        return $url;
    }
}