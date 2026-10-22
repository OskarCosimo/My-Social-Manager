<?php
// app/Models/SocialAccount.php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/UsageManager.php';

class SocialAccount {

    /**
     * Save or update a social connection token with optional expiry and refresh token.
     * Prevents duplicate entries on reconnect and ensures plan limits are respected.
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
        $db = Database::getInstance();

        // 1. Check if this account has already been connected by this user
        $checkStmt = $db->prepare("
            SELECT id, refresh_token 
            FROM social_accounts 
            WHERE user_id = ? AND plugin_id = ? AND platform_user_id = ? 
            LIMIT 1
        ");
        $checkStmt->execute([$userId, $pluginId, $platformUserId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Account exists: update tokens without creating a duplicate record
            // Retain the existing refresh_token if the incoming one is null
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

        // 2. Account does not exist: enforce subscription plan limits before creation
        if (!UsageManager::canAddAccount($userId)) {
            error_log("Security Block: User {$userId} tried to exceed account limit.");
            throw new Exception("Maximum account limit reached for your plan.");
        }

        // 3. Insert new social account record
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
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM social_accounts WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single account by ID and user ID
     */
    public static function getAccountById(int $accountId, int $userId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM social_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}