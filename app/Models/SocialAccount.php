<?php
// app/Models/SocialAccount.php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/UsageManager.php';

class SocialAccount {

    /**
     * Ensure default_media column exists on social_accounts table
     */
    public static function ensureDefaultMediaColumn(): void {
        static $checked = false;
        if ($checked) return;
        
        $db = Database::getInstance();
        try {
            $db->query("SELECT default_media FROM social_accounts LIMIT 1");
        } catch (\PDOException $e) {
            try {
                $db->exec("ALTER TABLE social_accounts ADD COLUMN default_media TEXT NULL");
            } catch (\PDOException $ex) {
                // Concurrency catch
            }
        }
        $checked = true;
    }

    /**
     * Save or update a social connection token with optional expiry and refresh token.
     */
    public static function save(
        int $userId, 
        string $pluginId, 
        string $platformUserId, 
        string $platformName, 
        string $accessToken, 
        ?string $refreshToken = null, 
        ?string $tokenExpiresAt = null
    ): bool {
        self::ensureDefaultMediaColumn();
        $db = Database::getInstance();

        $checkStmt = $db->prepare("
            SELECT id, refresh_token 
            FROM social_accounts 
            WHERE user_id = ? AND plugin_id = ? AND platform_user_id = ? 
            LIMIT 1
        ");
        $checkStmt->execute([$userId, $pluginId, $platformUserId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $finalRefreshToken = $refreshToken ?? $existing['refresh_token'];

            $updateStmt = $db->prepare("
                UPDATE social_accounts 
                SET platform_name = ?,
                    access_token = ?,
                    refresh_token = ?,
                    token_expires_at = ?
                WHERE id = ? AND user_id = ?
            ");
            return $updateStmt->execute([
                $platformName, 
                $accessToken, 
                $finalRefreshToken, 
                $tokenExpiresAt, 
                $existing['id'], 
                $userId
            ]);
        }

        if (!UsageManager::canAddAccount($userId)) {
            error_log("Security Block: User {$userId} tried to exceed account limit.");
            throw new Exception("Maximum account limit reached for your plan.");
        }

        $insertStmt = $db->prepare("
            INSERT INTO social_accounts 
            (user_id, plugin_id, platform_user_id, platform_name, access_token, refresh_token, token_expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $insertStmt->execute([
            $userId, 
            $pluginId, 
            $platformUserId, 
            $platformName, 
            $accessToken, 
            $refreshToken, 
            $tokenExpiresAt
        ]);
    }

    /**
     * Update per-account custom default fallback media URL
     */
    public static function updateDefaultMedia(int $accountId, int $userId, ?string $mediaUrl): bool {
        self::ensureDefaultMediaColumn();
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE social_accounts SET default_media = ? WHERE id = ? AND user_id = ?");
        return $stmt->execute([$mediaUrl !== '' ? $mediaUrl : null, $accountId, $userId]);
    }

    /**
     * Get per-account custom default fallback media URL
     */
    public static function getDefaultMedia(int $accountId, int $userId): ?string {
        self::ensureDefaultMediaColumn();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT default_media FROM social_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $val = $stmt->fetchColumn();
        return $val ? (string)$val : null;
    }

    /**
     * Update token expiration date specifically
     */
    public static function updateTokenExpiry(int $accountId, int $userId, ?string $tokenExpiresAt): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE social_accounts SET token_expires_at = ? WHERE id = ? AND user_id = ?");
        return $stmt->execute([$tokenExpiresAt, $accountId, $userId]);
    }

    /**
     * Get all connected accounts for a specific user
     */
    public static function getUserAccounts(int $userId): array {
        self::ensureDefaultMediaColumn();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM social_accounts WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single account by ID and user ID
     */
    public static function getAccountById(int $accountId, int $userId): ?array {
        self::ensureDefaultMediaColumn();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM social_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
