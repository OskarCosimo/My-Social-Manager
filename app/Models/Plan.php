<?php
// app/Models/Plan.php

require_once __DIR__ . '/../Core/Database.php';

class Plan {

    /**
     * Ensure database schema has ai_credits_period column on plans table
     */
    private static function ensureAiPeriodColumn(): void {
        static $checked = false;
        if ($checked) return;
        try {
            $db = Database::getInstance();
            $db->exec("ALTER TABLE plans ADD COLUMN ai_credits_period VARCHAR(20) NOT NULL DEFAULT 'monthly'");
        } catch (Exception $e) {}
        $checked = true;
    }
    
    public static function getAll(): array {
        self::ensureAiPeriodColumn();
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM plans ORDER BY price ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function update(int $id, array $data): void {
        self::ensureAiPeriodColumn();
        $db = Database::getInstance();

        if (!empty($data['is_default'])) {
            $db->exec("UPDATE plans SET is_default = 0");
        }

        $stmt = $db->prepare("
            UPDATE plans 
            SET name = :name,
                plan_code = :plan_code,
                stripe_price_id = :stripe_price_id,
                price = :price,
                billing_cycle = :billing_cycle,
                max_accounts = :max_accounts,
                max_posts = :max_posts,
                post_period = :post_period,
                max_rss_feeds = :max_rss_feeds,
                max_rss_posts = :max_rss_posts,
                max_channel_automations = :max_channel_automations,
                max_channel_posts = :max_channel_posts,
                ai_credits_month = :ai_credits_month,
                ai_credits_period = :ai_credits_period,
                ai_max_campaign_posts = :ai_max_campaign_posts,
                ai_max_daily_posts = :ai_max_daily_posts,
                max_storage_size = :max_storage_size,
                max_upload_size = :max_upload_size,
                max_mailing_lists = :max_mailing_lists,
                max_subscribers_per_list = :max_subscribers_per_list,
                max_emails_per_month = :max_emails_per_month,
                is_default = :is_default,
                is_hidden = :is_hidden
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':name'                     => $data['name'],
            ':plan_code'                => $data['plan_code'] ?? null,
            ':stripe_price_id'          => $data['stripe_price_id'] ?? null,
            ':price'                    => $data['price'] ?? 0.00,
            ':billing_cycle'            => $data['billing_cycle'] ?? 'monthly',
            ':max_accounts'             => $data['max_accounts'],
            ':max_posts'                => $data['max_posts'],
            ':post_period'              => $data['post_period'],
            ':max_rss_feeds'            => $data['max_rss_feeds'] ?? 5,
            ':max_rss_posts'            => $data['max_rss_posts'] ?? 50,
            ':max_channel_automations'  => $data['max_channel_automations'] ?? 5,
            ':max_channel_posts'        => $data['max_channel_posts'] ?? 50,
            ':ai_credits_month'         => $data['ai_credits_month'],
            ':ai_credits_period'        => $data['ai_credits_period'] ?? 'monthly',
            ':ai_max_campaign_posts'    => $data['ai_max_campaign_posts'],
            ':ai_max_daily_posts'       => $data['ai_max_daily_posts'],
            ':max_storage_size'         => $data['max_storage_size'],
            ':max_upload_size'          => $data['max_upload_size'],
            ':max_mailing_lists'        => (int)($data['max_mailing_lists'] ?? 0),
            ':max_subscribers_per_list' => (int)($data['max_subscribers_per_list'] ?? 0),
            ':max_emails_per_month'     => (int)($data['max_emails_per_month'] ?? 0),
            ':is_default'               => !empty($data['is_default']) ? 1 : 0,
            ':is_hidden'                => !empty($data['is_hidden']) ? 1 : 0,
            ':id'                       => $id
        ]);
    }

    public static function create(array $data): void {
        self::ensureAiPeriodColumn();
        $db = Database::getInstance();

        if (!empty($data['is_default'])) {
            $db->exec("UPDATE plans SET is_default = 0");
        }

        $stmt = $db->prepare("
            INSERT INTO plans (
                name, plan_code, stripe_price_id, price, billing_cycle, 
                max_accounts, max_posts, post_period, max_rss_feeds, max_rss_posts,
                max_channel_automations, max_channel_posts,
                ai_credits_month, ai_credits_period, is_default, is_hidden, ai_max_campaign_posts, 
                ai_max_daily_posts, max_storage_size, max_upload_size, 
                max_mailing_lists, max_subscribers_per_list, max_emails_per_month
            )
            VALUES (
                :name, :plan_code, :stripe_price_id, :price, :billing_cycle, 
                :max_accounts, :max_posts, :post_period, :max_rss_feeds, :max_rss_posts,
                :max_channel_automations, :max_channel_posts,
                :ai_credits_month, :ai_credits_period, :is_default, :is_hidden, :ai_max_campaign_posts, 
                :ai_max_daily_posts, :max_storage_size, :max_upload_size, 
                :max_mailing_lists, :max_subscribers_per_list, :max_emails_per_month
            )
        ");
        
        $stmt->execute([
            ':name'                     => $data['name'],
            ':plan_code'                => $data['plan_code'] ?? null,
            ':stripe_price_id'          => $data['stripe_price_id'] ?? null,
            ':price'                    => $data['price'] ?? 0.00,
            ':billing_cycle'            => $data['billing_cycle'] ?? 'monthly',
            ':max_accounts'             => $data['max_accounts'],
            ':max_posts'                => $data['max_posts'],
            ':post_period'              => $data['post_period'],
            ':max_rss_feeds'            => $data['max_rss_feeds'] ?? 5,
            ':max_rss_posts'            => $data['max_rss_posts'] ?? 50,
            ':max_channel_automations'  => $data['max_channel_automations'] ?? 5,
            ':max_channel_posts'        => $data['max_channel_posts'] ?? 50,
            ':ai_credits_month'         => $data['ai_credits_month'],
            ':ai_credits_period'        => $data['ai_credits_period'] ?? 'monthly',
            ':is_default'               => !empty($data['is_default']) ? 1 : 0,
            ':is_hidden'                => !empty($data['is_hidden']) ? 1 : 0,
            ':ai_max_campaign_posts'    => $data['ai_max_campaign_posts'],
            ':ai_max_daily_posts'       => $data['ai_max_daily_posts'],
            ':max_storage_size'         => $data['max_storage_size'],
            ':max_upload_size'          => $data['max_upload_size'],
            ':max_mailing_lists'        => (int)($data['max_mailing_lists'] ?? 0),
            ':max_subscribers_per_list' => (int)($data['max_subscribers_per_list'] ?? 0),
            ':max_emails_per_month'     => (int)($data['max_emails_per_month'] ?? 0),
        ]);
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("DELETE FROM plans WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Error deleting plan ID {$id}: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getByUserId(int $userId): ?array {
        self::ensureAiPeriodColumn();
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.* FROM plans p 
            JOIN users u ON u.plan_id = p.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        return $plan ?: null;
    }
}
