<?php
// cron/reset_monthly_usage.php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

require_once BASE_PATH . 'app/Core/Database.php';

// Allow execution either via CLI or via internal PHP cron runner
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CRON_EXEC')) {
    die("This script can only be run from the command line or internal runner.");
}

try {
    $db = Database::getInstance();
    
    // 1. Reset POSTS, RSS and Channel Automation counters based on plan period settings
    $stmtPosts = $db->query("
        UPDATE users u
        JOIN plans p ON u.plan_id = p.id
        SET u.posts_used = 0,
            u.rss_posts_used = 0,
            u.channel_posts_used = 0
        WHERE p.post_period = 'daily'
           OR (p.post_period = 'weekly' AND DAYOFWEEK(CURRENT_DATE) = 2)
           OR (p.post_period = 'monthly' AND DAYOFMONTH(CURRENT_DATE) = 1)
    ");

    $postsResetCount = $stmtPosts->rowCount();

    // 2. Reset AI credits and monthly email limits strictly on the 1st day of the month
    $aiResetCount = 0;
    if (date('j') == 1) {
        $stmtAi = $db->query("UPDATE users SET ai_credits_used = 0, emails_sent_month = 0");
        $aiResetCount = $stmtAi->rowCount();
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Reset complete. Posts, RSS & Channels reset: {$postsResetCount}. AI/Email reset: {$aiResetCount}.\n";

} catch (Exception $e) {
    error_log("CRON ERROR (Reset Limits): " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}