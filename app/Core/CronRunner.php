<?php
// app/Core/CronRunner.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/../Models/SystemSetting.php';

class CronRunner {

    /**
     * List of internal workers and their recommended minimal execution interval in seconds
     */
    private static array $tasks = [
        'publisher' => [
            'name'     => 'Social Publisher Worker',
            'script'   => '/../../cron/publisher_worker.php',
            'interval' => 60 // 1 minute
        ],
        'ai_campaign' => [
            'name'     => 'AI Campaign Worker',
            'script'   => '/../../cron/ai_campaign_worker.php',
            'interval' => 60 // 1 minute
        ],
        'rss' => [
            'name'     => 'RSS Feeds Parser',
            'script'   => '/../../cron/rss_worker.php',
            'interval' => 900 // 15 minutes
        ],
        'reset_usage' => [
            'name'     => 'Usage Counters & Monthly Reset',
            'script'   => '/../../cron/reset_monthly_usage.php',
            'interval' => 3600 // 1 hour check
        ]
    ];

    /**
     * Main entry point called during web requests (WordPress WP-Cron style)
     */
    public static function runIfDue(): void {
        // 1. Check if internal PHP cron is enabled in system settings
        $enabled = SystemSetting::get('enable_internal_cron', '0');
        if ($enabled !== '1') {
            return;
        }

        // 2. Prevent concurrent runs using a short throttle lock
        $lastMasterRun = (int)SystemSetting::get('cron_internal_last_run', '0');
        if ((time() - $lastMasterRun) < 45) {
            return;
        }

        // Save current timestamp immediately as a lock
        SystemSetting::save('cron_internal_last_run', (string)time());

        // 3. Evaluate each task schedule and run if due
        foreach (self::$tasks as $key => $info) {
            $lastTaskRun = (int)SystemSetting::get("cron_last_run_{$key}", '0');
            if ((time() - $lastTaskRun) >= $info['interval']) {
                self::executeTask($key);
            }
        }
    }

    /**
     * Execute a specific worker script safely capturing its output
     */
    public static function executeTask(string $taskKey): string {
        if (!isset(self::$tasks[$taskKey])) {
            return "Invalid task identifier.";
        }

        $task = self::$tasks[$taskKey];
        $filePath = realpath(__DIR__ . $task['script']);

        if (!$filePath || !file_exists($filePath)) {
            return "Worker script file not found at: " . $task['script'];
        }

        if (!defined('INTERNAL_CRON_EXEC')) {
            define('INTERNAL_CRON_EXEC', true);
        }

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2) . '/');
        }

        // Update task run timestamp
        SystemSetting::save("cron_last_run_{$taskKey}", (string)time());

        // Capture buffer output to avoid corrupting HTTP page responses
        ob_start();
        try {
            require $filePath;
            $output = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            $output = "Exception during task [{$taskKey}]: " . $e->getMessage();
            error_log($output);
        }

        return $output;
    }

    /**
     * Get statuses of registered internal tasks
     */
    public static function getTasksStatus(): array {
        $result = [];
        foreach (self::$tasks as $key => $task) {
            $lastRun = (int)SystemSetting::get("cron_last_run_{$key}", '0');
            $nextRun = $lastRun > 0 ? ($lastRun + $task['interval']) : time();
            $result[$key] = [
                'name'     => $task['name'],
                'interval' => $task['interval'],
                'last_run' => $lastRun > 0 ? date('Y-m-d H:i:s', $lastRun) : 'Never',
                'next_run' => date('Y-m-d H:i:s', $nextRun),
                'is_due'   => (time() - $lastRun) >= $task['interval']
            ];
        }
        return $result;
    }

    /**
     * Retrieve the last 100 scheduled or pending queue items with decrypted user email
     */
    public static function getQueuedPosts(int $limit = 100): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.id, p.user_id, p.content, p.scheduled_for, p.is_rss, p.campaign_id, p.created_at,
                   u.email AS user_email, s.platform_name, s.plugin_id
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN social_accounts s ON p.social_account_id = s.id
            WHERE p.status = 'scheduled'
            ORDER BY p.scheduled_for ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['user_email'])) {
                $row['user_email'] = Crypto::decrypt($row['user_email']);
            }
        }

        return $rows;
    }

    /**
     * Retrieve the last 100 failed jobs with decrypted user email and error details
     */
    public static function getFailedPosts(int $limit = 100): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.id, p.user_id, p.content, p.scheduled_for, p.published_at, p.error_log, p.is_rss, p.campaign_id,
                   u.email AS user_email, s.platform_name, s.plugin_id
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN social_accounts s ON p.social_account_id = s.id
            WHERE p.status = 'failed'
            ORDER BY p.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['user_email'])) {
                $row['user_email'] = Crypto::decrypt($row['user_email']);
            }
        }

        return $rows;
    }

    /**
     * Reset a failed post status back to 'scheduled' to retry it
     */
    public static function retryPost(int $postId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE posts 
            SET status = 'scheduled', error_log = NULL, scheduled_for = NOW() 
            WHERE id = ? AND status = 'failed'
        ");
        return $stmt->execute([$postId]);
    }
}
