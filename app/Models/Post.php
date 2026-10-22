<?php
// app/Models/Post.php

require_once __DIR__ . '/../Core/Database.php';

class Post {

    /**
     * Create a new post in the database.
     * Sets status to 'scheduled' and populates both scheduled_for and scheduled_at.
     */
    public static function create(int $userId, string $content, array $mediaUrls = [], ?string $scheduledAt = null, ?int $socialAccountId = null): int {
        $db = Database::getInstance();
        
        // If no scheduling date is provided, fallback to current timestamp
        if (empty($scheduledAt)) {
            $scheduledAt = date('Y-m-d H:i:s');
        }

        $stmt = $db->prepare("
            INSERT INTO posts (
                user_id, content, media_urls, status, scheduled_at, scheduled_for, social_account_id, created_at
            ) VALUES (
                :user_id, 
                :content, 
                :media_urls, 
                'scheduled', 
                COALESCE(:scheduled_at, NOW()), 
                COALESCE(:scheduled_for, NOW()), 
                :social_account_id, 
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id'           => $userId,
            ':content'           => $content,
            ':media_urls'        => !empty($mediaUrls) ? json_encode($mediaUrls) : null,
            ':scheduled_at'      => $scheduledAt,
            ':scheduled_for'     => $scheduledAt,
            ':social_account_id' => $socialAccountId
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Retrieve all scheduled posts that are due for publication.
     * Joins with social_accounts to get the plugin ID and access token.
     */
    public static function getDuePosts(): array {
        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT p.id, p.content, p.media_urls, p.scheduled_for,
                   s.plugin_id, s.access_token 
            FROM posts p
            JOIN social_accounts s ON p.social_account_id = s.id
            WHERE p.status = 'scheduled' 
            AND (p.scheduled_for IS NULL OR p.scheduled_for <= UTC_TIMESTAMP())
        ");
        return $stmt->fetchAll();
    }

    /**
     * Mark a post as successfully published.
     */
    public static function markAsPublished(int $postId, ?string $remoteId = null): void {
        $db = Database::getInstance();
        $log = $remoteId ? "Success. Remote ID: $remoteId" : "Success.";
        
        $stmt = $db->prepare("
            UPDATE posts 
            SET status = 'published', published_at = NOW(), error_log = ? 
            WHERE id = ?
        ");
        $stmt->execute([$log, $postId]);
    }

    /**
     * Mark a post as failed and save the error reason.
     */
    public static function markAsFailed(int $postId, string $errorMsg): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE posts 
            SET status = 'failed', error_log = ? 
            WHERE id = ?
        ");
        $stmt->execute([$errorMsg, $postId]);
    }
}