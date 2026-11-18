<?php
// app/Models/RssFeed.php
require_once __DIR__ . '/../Core/Database.php';

class RssFeed {

    /**
     * Ensure database schema has AI caption columns on rss_feeds table
     */
    private static function ensureAiColumns(): void {
        static $checked = false;
        if ($checked) return;

        $db = Database::getInstance();
        $columns = [
            "ALTER TABLE rss_feeds ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE rss_feeds ADD COLUMN ai_prompt TEXT NULL",
            "ALTER TABLE rss_feeds ADD COLUMN ai_detail_level VARCHAR(20) NOT NULL DEFAULT 'medium'",
            "ALTER TABLE rss_feeds ADD COLUMN ai_max_tokens INT NOT NULL DEFAULT 350",
            "ALTER TABLE rss_feeds ADD COLUMN ai_max_chars INT NOT NULL DEFAULT 0"
        ];

        foreach ($columns as $sql) {
            try {
                $db->exec($sql);
            } catch (Exception $e) {
                // Column already exists
            }
        }

        $checked = true;
    }

    /**
     * Add a new RSS feed with advanced and optional AI caption settings
     */
    public static function add(
        int $userId, 
        string $url, 
        array $targetAccounts, 
        int $intervalHours = 1, 
        int $onlyNewItems = 0, 
        int $randomRepostEnabled = 0, 
        int $randomRepostLimit = 10,
        int $aiEnabled = 0,
        ?string $aiPrompt = null,
        string $aiDetailLevel = 'medium',
        int $aiMaxTokens = 350,
        int $aiMaxChars = 0
    ): bool {
        self::ensureAiColumns();
        $db = Database::getInstance();
        
        // If "only new items" is selected, initialize last_checked to NOW() so existing items are ignored
        $lastChecked = ($onlyNewItems === 1) ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("
            INSERT INTO rss_feeds (
                user_id, 
                feed_url, 
                target_accounts, 
                post_interval_hours, 
                only_new_items, 
                random_repost_enabled, 
                random_repost_limit,
                last_checked,
                ai_enabled,
                ai_prompt,
                ai_detail_level,
                ai_max_tokens,
                ai_max_chars
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId, 
            $url, 
            json_encode($targetAccounts), 
            $intervalHours, 
            $onlyNewItems, 
            $randomRepostEnabled, 
            $randomRepostLimit,
            $lastChecked,
            $aiEnabled,
            $aiPrompt,
            $aiDetailLevel,
            $aiMaxTokens,
            $aiMaxChars
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
        int $randomRepostLimit,
        int $aiEnabled = 0,
        ?string $aiPrompt = null,
        string $aiDetailLevel = 'medium',
        int $aiMaxTokens = 350,
        int $aiMaxChars = 0
    ): bool {
        self::ensureAiColumns();
        $db = Database::getInstance();

        // If only_new_items is toggled on, update last_checked to NOW() to prevent past items blast
        if ($onlyNewItems === 1) {
            $stmt = $db->prepare("
                UPDATE rss_feeds 
                SET feed_url = ?, 
                    target_accounts = ?, 
                    post_interval_hours = ?, 
                    only_new_items = ?, 
                    random_repost_enabled = ?, 
                    random_repost_limit = ?,
                    ai_enabled = ?,
                    ai_prompt = ?,
                    ai_detail_level = ?,
                    ai_max_tokens = ?,
                    ai_max_chars = ?,
                    last_checked = NOW()
                WHERE id = ? AND user_id = ?
            ");
        } else {
            $stmt = $db->prepare("
                UPDATE rss_feeds 
                SET feed_url = ?, 
                    target_accounts = ?, 
                    post_interval_hours = ?, 
                    only_new_items = ?, 
                    random_repost_enabled = ?, 
                    random_repost_limit = ?,
                    ai_enabled = ?,
                    ai_prompt = ?,
                    ai_detail_level = ?,
                    ai_max_tokens = ?,
                    ai_max_chars = ?
                WHERE id = ? AND user_id = ?
            ");
        }

        return $stmt->execute([
            $url, 
            json_encode($targetAccounts), 
            $intervalHours, 
            $onlyNewItems, 
            $randomRepostEnabled, 
            $randomRepostLimit,
            $aiEnabled,
            $aiPrompt,
            $aiDetailLevel,
            $aiMaxTokens,
            $aiMaxChars,
            $feedId, 
            $userId
        ]);
    }

    /**
     * Get a single feed by ID ensuring ownership
     */
    public static function getById(int $feedId, int $userId): ?array {
        self::ensureAiColumns();
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
        self::ensureAiColumns();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rss_feeds WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get ALL feeds from ALL users (used by the background Cronjob)
     */
    public static function getAllForCron(): array {
        self::ensureAiColumns();
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
