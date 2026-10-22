<?php
// app/Models/MailingList.php

require_once __DIR__ . '/../Core/Database.php';

class MailingList {
    
    /**
     * Get all mailing lists for a specific user
     */
    public static function getUserLists(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM mailing_lists WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific mailing list by ID and owner User ID
     */
    public static function getById(int $listId, int $userId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM mailing_lists WHERE id = ? AND user_id = ?");
        $stmt->execute([$listId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create a new mailing list with paid options
     */
    public static function create(
        int $userId, 
        string $name, 
        bool $requireConfirmation = false, 
        bool $isPaid = false, 
        string $gateway = 'stripe', 
        string $type = 'one_time', 
        string $period = 'monthly', 
        float $price = 0.00, 
        string $currency = 'EUR'
    ): bool {
        $db = Database::getInstance();
        $publicHash = bin2hex(random_bytes(16)); 
        
        $stmt = $db->prepare("
            INSERT INTO mailing_lists 
            (user_id, name, public_hash, require_confirmation, is_paid, payment_gateway, payment_type, billing_period, price, currency) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId, $name, $publicHash, $requireConfirmation ? 1 : 0, 
            $isPaid ? 1 : 0, $gateway, $type, $period, $price, strtoupper($currency)
        ]);
    }

    /**
     * Add subscriber with payment details
     */
    public static function addSubscriber(
        int $listId, 
        string $email, 
        bool $requireConfirmation = false, 
        string $paymentStatus = 'free', 
        ?string $subscriptionId = null, 
        ?string $expiresAt = null
    ) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT id, is_confirmed, payment_status FROM mailing_list_subscribers WHERE list_id = ? AND email = ?");
        $stmt->execute([$listId, $email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing pending status to active/paid if renewing
            $stmtUpdate = $db->prepare("
                UPDATE mailing_list_subscribers 
                SET payment_status = ?, gateway_subscription_id = ?, expires_at = ?, is_confirmed = 1 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$paymentStatus, $subscriptionId, $expiresAt, $existing['id']]);
            return true;
        }

        $isConfirmed = ($requireConfirmation && $paymentStatus === 'free') ? 0 : 1;
        $token = ($requireConfirmation && $paymentStatus === 'free') ? bin2hex(random_bytes(32)) : null;

        $stmt = $db->prepare("
            INSERT INTO mailing_list_subscribers 
            (list_id, email, is_confirmed, confirmation_token, payment_status, gateway_subscription_id, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([$listId, $email, $isConfirmed, $token, $paymentStatus, $subscriptionId, $expiresAt]);

        if ($success && $requireConfirmation && $paymentStatus === 'free') {
            return $token;
        }

        return $success;
    }

    /**
     * Activate paid subscriber after successful webhook notification
     */
    public static function activatePaidSubscriber(int $listId, string $email, string $subscriptionId, ?string $expiresAt): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE mailing_list_subscribers 
            SET payment_status = 'paid', is_confirmed = 1, gateway_subscription_id = ?, expires_at = ? 
            WHERE list_id = ? AND email = ?
        ");
        return $stmt->execute([$subscriptionId, $expiresAt, $listId, $email]);
    }

    /**
     * Cancel or expire subscription via webhook
     */
    public static function setSubscriberPaymentStatus(string $subscriptionId, string $status): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE mailing_list_subscribers SET payment_status = ? WHERE gateway_subscription_id = ?");
        return $stmt->execute([$status, $subscriptionId]);
    }

    public static function confirmSubscriber(string $token): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE mailing_list_subscribers SET is_confirmed = 1, confirmation_token = NULL WHERE confirmation_token = ?");
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public static function toggleExclusion(int $subscriberId, int $listId, int $userId): bool {
        $db = Database::getInstance();
        $stmtCheck = $db->prepare("SELECT id FROM mailing_lists WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$listId, $userId]);
        if (!$stmtCheck->fetch()) {
            return false;
        }

        $stmt = $db->prepare("UPDATE mailing_list_subscribers SET is_excluded = IF(is_excluded = 1, 0, 1) WHERE id = ? AND list_id = ?");
        return $stmt->execute([$subscriberId, $listId]);
    }

    public static function delete(int $listId, int $userId): bool {
        $db = Database::getInstance();
        $stmtSub = $db->prepare("DELETE FROM mailing_list_subscribers WHERE list_id = ?");
        $stmtSub->execute([$listId]);

        $stmtList = $db->prepare("DELETE FROM mailing_lists WHERE id = ? AND user_id = ?");
        return $stmtList->execute([$listId, $userId]);
    }

    public static function deleteSubscriber(int $subscriberId, int $listId, int $userId): bool {
        $db = Database::getInstance();
        $stmtCheck = $db->prepare("SELECT id FROM mailing_lists WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$listId, $userId]);
        if (!$stmtCheck->fetch()) {
            return false;
        }

        $stmt = $db->prepare("DELETE FROM mailing_list_subscribers WHERE id = ? AND list_id = ?");
        return $stmt->execute([$subscriberId, $listId]);
    }

    public static function getSubscribersDetailed(int $listId, int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT mls.* 
            FROM mailing_list_subscribers mls
            JOIN mailing_lists ml ON mls.list_id = ml.id
            WHERE mls.list_id = ? AND ml.user_id = ?
            ORDER BY mls.id DESC
        ");
        $stmt->execute([$listId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active non-excluded confirmed subscriber emails for sending campaigns.
     * Checks if payment is valid (free or paid and non-expired).
     */
    public static function getActiveSubscribers(int $listId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT email FROM mailing_list_subscribers 
            WHERE list_id = ? 
              AND is_confirmed = 1 
              AND is_excluded = 0
              AND (payment_status = 'free' OR (payment_status = 'paid' AND (expires_at IS NULL OR expires_at > NOW())))
        ");
        $stmt->execute([$listId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}