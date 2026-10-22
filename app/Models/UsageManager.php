<?php
// app/Models/UsageManager.php

require_once __DIR__ . '/../Core/Database.php';

class UsageManager {
    
    public static function getUserLimits(int $userId): array {
        $db = Database::getInstance();

        // 1. Check if subscription period has expired and process pending downgrade
        $settingKey = 'pending_downgrade_user_' . $userId;
        $stmtPending = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmtPending->execute([$settingKey]);
        $pendingPlanId = $stmtPending->fetchColumn();

        $stmtCheck = $db->prepare("SELECT plan_id, current_period_end FROM users WHERE id = ?");
        $stmtCheck->execute([$userId]);
        $user = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['current_period_end']) && strtotime($user['current_period_end']) < time()) {
            $targetPlanId = $pendingPlanId ? (int)$pendingPlanId : 1;

            $stmtPlan = $db->prepare("SELECT price FROM plans WHERE id = ?");
            $stmtPlan->execute([$targetPlanId]);
            $targetPrice = (float)($stmtPlan->fetchColumn() ?? 0);

            $isPremium = ($targetPrice > 0.00) ? 1 : 0;

            $stmtDowngrade = $db->prepare("
                UPDATE users 
                SET plan_id = :plan_id, 
                    is_premium = :is_premium, 
                    current_period_end = NULL 
                WHERE id = :id
            ");
            $stmtDowngrade->execute([
                ':plan_id'    => $targetPlanId,
                ':is_premium' => $isPremium,
                ':id'         => $userId
            ]);

            $stmtClean = $db->prepare("DELETE FROM system_settings WHERE setting_key = ?");
            $stmtClean->execute([$settingKey]);
            $pendingPlanId = false;
        }

        // 2. Main query retrieving all limits including dedicated Channel Automations
        $stmt = $db->prepare("
            SELECT u.posts_used, u.rss_posts_used, u.channel_posts_used, u.ai_credits_used, u.emails_sent_month, u.plan_id,
                   u.is_premium, u.premium_expires_at, u.current_period_end,
                   p.name as plan_name, p.price, p.billing_cycle, p.max_accounts, p.max_posts, 
                   p.max_rss_feeds, p.max_rss_posts, 
                   p.max_channel_automations, p.max_channel_posts,
                   p.post_period, p.ai_credits_month,
                   p.max_mailing_lists, p.max_subscribers_per_list, p.max_emails_per_month,
                   p.max_storage_size, p.max_upload_size,
                   (SELECT COUNT(*) FROM social_accounts WHERE user_id = u.id) as current_accounts,
                   (SELECT COUNT(*) FROM rss_feeds WHERE user_id = u.id) as current_rss_feeds,
                   (SELECT COUNT(*) FROM channel_automations WHERE user_id = u.id) as current_channel_automations,
                   (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND is_rss = 1 AND status = 'published') as real_rss_posts
            FROM users u
            JOIN plans p ON u.plan_id = p.id
            WHERE u.id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($pendingPlanId) {
            $stmtTarget = $db->prepare("SELECT name FROM plans WHERE id = ?");
            $stmtTarget->execute([(int)$pendingPlanId]);
            $result['pending_plan_name'] = $stmtTarget->fetchColumn() ?: 'Free';
            $result['has_pending_downgrade'] = true;
        } else {
            $result['has_pending_downgrade'] = false;
        }

        return $result;
    }

    public static function getAiLimits(int $userId): array {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT 
                u.ai_credits_used,
                p.ai_credits_month,
                p.ai_max_campaign_posts,
                p.ai_max_daily_posts 
            FROM users u
            LEFT JOIN plans p ON u.plan_id = p.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return [
                'ai_credits_used'       => 0,
                'ai_credits_month'      => 0,
                'ai_max_campaign_posts' => 3,
                'ai_max_daily_posts'    => 1,
                'ai_credits_balance'    => 0
            ];
        }

        $data['ai_credits_balance'] = max(0, $data['ai_credits_month'] - $data['ai_credits_used']);

        return [
            'ai_credits_used'       => (int)($data['ai_credits_used'] ?? 0),
            'ai_credits_month'      => (int)($data['ai_credits_month'] ?? 0),
            'ai_max_campaign_posts' => (int)($data['ai_max_campaign_posts'] ?? 3),
            'ai_max_daily_posts'    => (int)($data['ai_max_daily_posts'] ?? 1),
            'ai_credits_balance'    => (int)$data['ai_credits_balance']
        ];
    }

    public static function canAddAccount(int $userId): bool {
        $limits = self::getUserLimits($userId);
        if (empty($limits)) return false;
        return $limits['current_accounts'] < $limits['max_accounts'];
    }

    public static function canPublishPost(int $userId): bool {
        $limits = self::getUserLimits($userId);
        if (empty($limits)) return false;
        return $limits['posts_used'] < $limits['max_posts'];
    }

    /**
     * Check if user can add/publish RSS posts
     */
    public static function canPublishRssPost(int $userId, int $count = 1): bool {
        $limits = self::getUserLimits($userId);
        if (empty($limits)) return false;
        return ($limits['rss_posts_used'] + $count) <= $limits['max_rss_posts'];
    }

    /**
     * Check if user can create a new Channel Automation rule
     */
    public static function canAddChannelAutomation(int $userId): bool {
        $limits = self::getUserLimits($userId);
        if (empty($limits)) return false;

        $max = (int)($limits['max_channel_automations'] ?? 0);
        $current = (int)($limits['current_channel_automations'] ?? 0);

        if ($max <= 0) return true;
        return $current < $max;
    }

    /**
     * Check if user can post from Channel Automations within dedicated quota
     */
    public static function canPublishChannelPost(int $userId, int $count = 1): bool {
        $limits = self::getUserLimits($userId);
        if (empty($limits)) return false;

        $max = (int)($limits['max_channel_posts'] ?? 0);
        $used = (int)($limits['channel_posts_used'] ?? 0);

        if ($max <= 0) return true;
        return ($used + $count) <= $max;
    }

    public static function canGenerateAi(int $userId): bool {
        $limits = self::getUserLimits($userId);
        if (empty($limits)) return false;
        return $limits['ai_credits_used'] < $limits['ai_credits_month'];
    }

    public static function incrementPostUsage(int $userId): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET posts_used = posts_used + 1 WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
    }

    public static function incrementRssPostUsage(int $userId, int $amount = 1): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET rss_posts_used = rss_posts_used + :amount WHERE id = :user_id");
            $stmt->execute([
                ':amount'  => (int)$amount,
                ':user_id' => (int)$userId
            ]);
        } catch (Exception $e) {
            error_log("Failed to increment RSS post usage for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Increment dedicated Channel Automation post counter
     */
    public static function incrementChannelPostUsage(int $userId, int $amount = 1): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET channel_posts_used = channel_posts_used + :amount WHERE id = :user_id");
            $stmt->execute([
                ':amount'  => (int)$amount,
                ':user_id' => (int)$userId
            ]);
        } catch (Exception $e) {
            error_log("Failed to increment channel post usage for user {$userId}: " . $e->getMessage());
        }
    }

    public static function incrementAiUsage(int $userId, int $amount = 1): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET ai_credits_used = ai_credits_used + :amount WHERE id = :user_id");
        $stmt->execute([
            ':amount' => $amount,
            ':user_id' => $userId
        ]);
    }

    public static function canSendEmails(int $userId, int $count = 1): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.emails_sent_month, p.max_emails_per_month 
            FROM users u
            JOIN plans p ON u.plan_id = p.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) return false;

        $maxEmails = (int)($data['max_emails_per_month'] ?? 0);
        $sentEmails = (int)($data['emails_sent_month'] ?? 0);

        if ($maxEmails <= 0) return false;
        return ($sentEmails + $count) <= $maxEmails;
    }

    public static function incrementEmailUsage(int $userId, int $count = 1): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET emails_sent_month = emails_sent_month + :count WHERE id = :user_id");
        $stmt->execute([
            ':count'   => $count,
            ':user_id' => $userId
        ]);
    }
}