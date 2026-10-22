<?php
// app/Models/User.php

require_once __DIR__ . '/../Core/Database.php';

class User {

    public static function createWithVerification(string $email, string $passwordHash, int $emailVerified, ?string $token): int|false {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO users (email, password, email_verified, verification_token, accountsocialmanager) 
            VALUES (?, ?, ?, ?, 1)
        ");
        if ($stmt->execute([$email, $passwordHash, $emailVerified, $token])) {
            return (int)$db->lastInsertId();
        }
        return false;
    }

    public static function findByEmail(string $email): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $email, string $passwordHash, string $role = 'user'): int {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO users (email, password, role, accountsocialmanager) VALUES (?, ?, ?, 1)");
        $stmt->execute([$email, $passwordHash, $role]);
        return (int)$db->lastInsertId();
    }

    public static function getAll(): array {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public static function delete(int $id): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function updatePassword(int $id, string $newPasswordHash): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$newPasswordHash, $id]);
    }

    public static function updateRememberToken(int $id, ?string $token): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        return $stmt->execute([$token, $id]);
    }

    public static function findByRememberToken(string $token): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE remember_token = ? LIMIT 1");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySocialId(string $provider, string $socialId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE social_provider = ? AND social_id = ? LIMIT 1");
        $stmt->execute([$provider, $socialId]);
        return $stmt->fetch() ?: null;
    }

    public static function createFromSocial(string $email, string $provider, string $socialId): int {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO users (email, email_verified, social_provider, social_id, role, accountsocialmanager) 
            VALUES (?, 1, ?, ?, 'user', 1)
        ");
        $stmt->execute([$email, $provider, $socialId]);
        return (int)$db->lastInsertId();
    }

    public static function linkSocialAccount(int $userId, string $provider, string $socialId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET social_provider = ?, social_id = ? WHERE id = ?");
        return $stmt->execute([$provider, $socialId, $userId]);
    }

    public static function update2FA(int $userId, ?string $secret, bool $enabled): bool {
        $db = Database::getInstance();
        $isEnabled = $enabled ? 1 : 0;
        $stmt = $db->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = ? WHERE id = ?");
        return $stmt->execute([$secret, $isEnabled, $userId]);
    }

    /**
     * Update the subscription plan and calculate expiration date based on plan details
     * 
     * @param int $userId The ID of the user
     * @param int $planId The ID of the new plan to assign
     * @return bool True on success, false on failure
     */
    public static function updatePlan(int $userId, int $planId): bool {
        $db = Database::getInstance();

        // Fetch the plan details to inspect price and billing cycle
        $stmtPlan = $db->prepare("SELECT price, billing_cycle FROM plans WHERE id = ?");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();

        if (!$plan) {
            return false;
        }

        $cycle = strtolower($plan['billing_cycle'] ?? 'monthly');
        $price = (float)($plan['price'] ?? 0.00);

        // Determine expiration date and is_premium flag
        if ($price == 0.00 || $cycle === 'lifetime') {
            $periodEnd = null;
            $isPremium = 0;
        } elseif ($cycle === 'yearly') {
            $periodEnd = date('Y-m-d H:i:s', strtotime('+1 year'));
            $isPremium = 1;
        } else {
            $periodEnd = date('Y-m-d H:i:s', strtotime('+1 month'));
            $isPremium = 1;
        }

        $stmt = $db->prepare("
            UPDATE users 
            SET plan_id = :plan_id,
                is_premium = :is_premium,
                current_period_end = :current_period_end
            WHERE id = :id
        ");

        return $stmt->execute([
            'plan_id'            => $planId,
            'is_premium'         => $isPremium,
            'current_period_end' => $periodEnd,
            'id'                 => $userId
        ]);
    }
}