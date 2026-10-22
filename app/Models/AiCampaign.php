<?php
// app/Models/AiCampaign.php

require_once __DIR__ . '/../Core/Database.php';

class AiCampaign {
    
    /**
     * Create a new AI generated campaign
     */
    public static function create($userId, $prompt, $selectedAccounts, $scheduleSettings) {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO ai_campaigns (user_id, prompt, selected_accounts, schedule_settings, status, created_at)
            VALUES (:user_id, :prompt, :selected_accounts, :schedule_settings, 'active', NOW())
        ");

        $stmt->execute([
            'user_id'           => $userId,
            'prompt'            => $prompt,
            'selected_accounts' => json_encode($selectedAccounts),
            'schedule_settings' => json_encode($scheduleSettings)
        ]);

        return $db->lastInsertId();
    }

    /**
     * Get all active campaigns to be processed by the Cron Worker
     */
    public static function getActiveCampaigns() {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM ai_campaigns WHERE status = 'active'");
        
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($campaigns as &$campaign) {
            $campaign['selected_accounts'] = json_decode($campaign['selected_accounts'], true) ?: [];
            $campaign['schedule_settings'] = json_decode($campaign['schedule_settings'], true) ?: [];
        }
        
        return $campaigns;
    }

    /**
     * Update the status of a specific campaign
     */
    public static function updateStatus($id, $status) {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE ai_campaigns SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }
    
    /**
     * Get a campaign by ID verifying user ownership
     */
    public static function getById(int $id, int $userId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM ai_campaigns WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            return null;
        }

        $campaign['selected_accounts'] = json_decode($campaign['selected_accounts'], true) ?: [];
        $campaign['schedule_settings'] = json_decode($campaign['schedule_settings'], true) ?: [];
        return $campaign;
    }

    /**
     * Get all campaigns for a specific user with post counts
     */
    public static function getByUserId($userId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT c.*,
                (SELECT COUNT(*) FROM posts p WHERE p.campaign_id = c.id AND p.status = 'scheduled') AS scheduled_posts_count,
                (SELECT COUNT(*) FROM posts p WHERE p.campaign_id = c.id AND p.status = 'published') AS published_posts_count,
                (SELECT COUNT(*) FROM posts p WHERE p.campaign_id = c.id) AS total_posts_count
            FROM ai_campaigns c 
            WHERE c.user_id = :user_id 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($campaigns as &$campaign) {
            $campaign['selected_accounts'] = json_decode($campaign['selected_accounts'], true) ?: [];
            $campaign['schedule_settings'] = json_decode($campaign['schedule_settings'], true) ?: [];
        }

        return $campaigns;
    }

    /**
     * Update campaign data and update remaining scheduled posts if prompt changes
     */
    public static function update(int $id, int $userId, string $prompt, array $scheduleSettings, string $status): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            UPDATE ai_campaigns 
            SET prompt = :prompt,
                schedule_settings = :schedule_settings,
                status = :status
            WHERE id = :id AND user_id = :user_id
        ");

        $updated = $stmt->execute([
            ':prompt'            => $prompt,
            ':schedule_settings' => json_encode($scheduleSettings),
            ':status'            => $status,
            ':id'                => $id,
            ':user_id'           => $userId
        ]);

        return $updated;
    }

    /**
     * Delete campaign and delete scheduled posts associated with it
     */
    public static function deleteWithScheduledPosts(int $id, int $userId): bool {
        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Delete only pending/scheduled posts belonging to this campaign
            $deletePostsStmt = $db->prepare("
                DELETE FROM posts 
                WHERE campaign_id = :campaign_id 
                  AND user_id = :user_id 
                  AND status = 'scheduled'
            ");
            $deletePostsStmt->execute([
                ':campaign_id' => $id,
                ':user_id'     => $userId
            ]);

            // Delete the campaign record
            $deleteCampaignStmt = $db->prepare("
                DELETE FROM ai_campaigns 
                WHERE id = :id AND user_id = :user_id
            ");
            $deleteCampaignStmt->execute([
                ':id'      => $id,
                ':user_id' => $userId
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error deleting AI campaign: " . $e->getMessage());
            return false;
        }
    }
}