<?php
// app/Models/ChannelAutomation.php

require_once __DIR__ . '/../Core/Database.php';

class ChannelAutomation {

    /**
     * Ensure database schema has AI caption columns on channel_automations table
     */
    private static function ensureAiColumns(): void {
        static $checked = false;
        if ($checked) return;

        $db = Database::getInstance();
        $columns = [
            "ALTER TABLE channel_automations ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE channel_automations ADD COLUMN ai_prompt TEXT NULL",
            "ALTER TABLE channel_automations ADD COLUMN ai_detail_level VARCHAR(20) NOT NULL DEFAULT 'medium'",
            "ALTER TABLE channel_automations ADD COLUMN ai_max_tokens INT NOT NULL DEFAULT 350",
            "ALTER TABLE channel_automations ADD COLUMN ai_max_chars INT NOT NULL DEFAULT 0"
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
     * Add a new channel automation rule with optional AI caption settings
     */
    public static function add(
        int $userId,
        string $sourceType,
        ?int $sourceAccountId,
        ?string $sourceIdentifier,
        string $sourceTitle,
        array $targetAccounts,
        int $intervalHours = 1,
        int $onlyNewItems = 0,
        int $randomRepostEnabled = 0,
        int $randomRepostLimit = 10,
        ?string $filterKeywords = null,
        ?string $filterPlaylistId = null,
        int $aiEnabled = 0,
        ?string $aiPrompt = null,
        string $aiDetailLevel = 'medium',
        int $aiMaxTokens = 350,
        int $aiMaxChars = 0
    ): bool {
        self::ensureAiColumns();
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO channel_automations (
                user_id,
                source_type,
                source_account_id,
                source_identifier,
                source_title,
                target_accounts,
                post_interval_hours,
                only_new_items,
                random_repost_enabled,
                random_repost_limit,
                filter_keywords,
                filter_playlist_id,
                ai_enabled,
                ai_prompt,
                ai_detail_level,
                ai_max_tokens,
                ai_max_chars
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $userId,
            $sourceType,
            $sourceAccountId,
            $sourceIdentifier,
            $sourceTitle,
            json_encode($targetAccounts),
            $intervalHours,
            $onlyNewItems,
            $randomRepostEnabled,
            $randomRepostLimit,
            $filterKeywords,
            $filterPlaylistId,
            $aiEnabled,
            $aiPrompt,
            $aiDetailLevel,
            $aiMaxTokens,
            $aiMaxChars
        ]);
    }

    /**
     * Update an existing automation rule
     */
    public static function update(
        int $id,
        int $userId,
        array $targetAccounts,
        int $intervalHours,
        int $onlyNewItems,
        int $randomRepostEnabled,
        int $randomRepostLimit,
        ?string $filterKeywords = null,
        ?string $filterPlaylistId = null,
        int $aiEnabled = 0,
        ?string $aiPrompt = null,
        string $aiDetailLevel = 'medium',
        int $aiMaxTokens = 350,
        int $aiMaxChars = 0
    ): bool {
        self::ensureAiColumns();
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE channel_automations 
            SET target_accounts = ?,
                post_interval_hours = ?,
                only_new_items = ?,
                random_repost_enabled = ?,
                random_repost_limit = ?,
                filter_keywords = ?,
                filter_playlist_id = ?,
                ai_enabled = ?,
                ai_prompt = ?,
                ai_detail_level = ?,
                ai_max_tokens = ?,
                ai_max_chars = ?
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([
            json_encode($targetAccounts),
            $intervalHours,
            $onlyNewItems,
            $randomRepostEnabled,
            $randomRepostLimit,
            $filterKeywords,
            $filterPlaylistId,
            $aiEnabled,
            $aiPrompt,
            $aiDetailLevel,
            $aiMaxTokens,
            $aiMaxChars,
            $id,
            $userId
        ]);
    }

    /**
     * Get automation record by ID ensuring user ownership
     */
    public static function getById(int $id, int $userId): ?array {
        self::ensureAiColumns();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM channel_automations WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all automations for a given user
     */
    public static function getUserAutomations(int $userId): array {
        self::ensureAiColumns();
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT ca.*, sa.platform_name, sa.plugin_id AS source_plugin_id
            FROM channel_automations ca
            LEFT JOIN social_accounts sa ON ca.source_account_id = sa.id
            WHERE ca.user_id = ?
            ORDER BY ca.id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active records across the platform for the cron worker
     */
    public static function getAllForCron(): array {
        self::ensureAiColumns();
        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT ca.*, sa.platform_name, sa.plugin_id AS source_plugin_id, sa.access_token AS source_access_token, sa.platform_user_id AS source_platform_user_id
            FROM channel_automations ca
            LEFT JOIN social_accounts sa ON ca.source_account_id = sa.id
            ORDER BY ca.last_checked ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update last_checked timestamp after processing using pure UTC timestamp
     */
    public static function updateLastChecked(int $id): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE channel_automations SET last_checked = UTC_TIMESTAMP() WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Delete an automation record
     */
    public static function delete(int $id, int $userId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM channel_automations WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}
