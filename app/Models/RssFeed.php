<?php
// app/Models/RssFeed.php

require_once __DIR__ . '/../Core/Database.php';

class RssFeed {

    /**
     * Add a new RSS feed with advanced settings
     */
    public static function add(
        int $userId, 
        string $url, 
        array $targetAccounts, 
        int $intervalHours = 1, 
        int $onlyNewItems = 0, 
        int $randomRepostEnabled = 0, 
        int $randomRepostLimit = 10
    ): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO rss_feeds (
                user_id, 
                feed_url, 
                target_accounts, 
                post_interval_hours, 
                only_new_items, 
                random_repost_enabled, 
                random_repost_limit
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId, 
            $url, 
            json_encode($targetAccounts), 
            $intervalHours, 
            $onlyNewItems, 
            $randomRepostEnabled, 
            $randomRepostLimit
        ]);
    }

    /**
     * Update an existing RSS feed belonging to the user
     */
    public static function update(
        int $feedId, 
        int $userId, 
        string $url, 
        array $targetAccounts, 
        int $intervalHours, 
        int $onlyNewItems, 
        int $randomRepostEnabled, 
        int $randomRepostLimit
    ): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE rss_feeds 
            SET feed_url = ?, 
                target_accounts = ?, 
                post_interval_hours = ?, 
                only_new_items = ?, 
                random_repost_enabled = ?, 
                random_repost_limit = ? 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([
            $url, 
            json_encode($targetAccounts), 
            $intervalHours, 
            $onlyNewItems, 
            $randomRepostEnabled, 
            $randomRepostLimit, 
            $feedId, 
            $userId
        ]);
    }

    /**
     * Get a single feed by ID ensuring ownership
     */
    public static function getById(int $feedId, int $userId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rss_feeds WHERE id = ? AND user_id = ?");
        $stmt->execute([$feedId, $userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Get all feeds for a specific user (for the frontend dashboard)
     */
    public static function getUserFeeds(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rss_feeds WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get ALL feeds from ALL users (used by the background Cronjob)
     */
    public static function getAllForCron(): array {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM rss_feeds ORDER BY last_checked ASC");
        return $stmt->fetchAll();
    }

    /**
     * Update the last_checked timestamp after the cronjob runs
     */
    public static function updateLastChecked(int $feedId): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE rss_feeds SET last_checked = NOW() WHERE id = ?");
        $stmt->execute([$feedId]);
    }

    /**
     * Delete an RSS feed ensuring it belongs to the user
     */
    public static function delete(int $feedId, int $userId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM rss_feeds WHERE id = ? AND user_id = ?");
        return $stmt->execute([$feedId, $userId]);
    }
}