<?php
// app/Controllers/CalendarController.php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/PluginManager.php';
require_once __DIR__ . '/../Models/User.php';

class CalendarController {

    /**
     * Render the main calendar view with dynamic plugin color mapping
     */
    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        // Retrieve current user's timezone from session or database fallback
        $userTimezone = $_SESSION['user_timezone'] ?? null;
        if (!$userTimezone) {
            $user = User::findById($userId);
            $userTimezone = $user['timezone'] ?? 'UTC';
            $_SESSION['user_timezone'] = $userTimezone;
        }

        // Validate timezone instantiation with strict fallback to UTC
        try {
            $userTzObj = new DateTimeZone($userTimezone);
            $utcTzObj  = new DateTimeZone('UTC');
        } catch (Exception $e) {
            $userTimezone = 'UTC';
            $userTzObj = new DateTimeZone('UTC');
            $utcTzObj  = new DateTimeZone('UTC');
        }

        // Fetch dynamic plugin colors using the existing PluginManager
        $pluginManager = new PluginManager();
        $pluginColorMap = $pluginManager->getPluginColors();

        // Fetch all user posts joined with social account metadata
        $stmt = $db->prepare("
            SELECT p.id, p.content, p.status, p.scheduled_for, p.scheduled_at, p.published_at, 
                   s.platform_name, s.plugin_id
            FROM posts p
            LEFT JOIN social_accounts s ON p.social_account_id = s.id
            WHERE p.user_id = ?
        ");
        $stmt->execute([$userId]);
        $posts = $stmt->fetchAll();

        $events = [];

        foreach ($posts as $post) {
            $rawDate = $post['scheduled_for'] ?? $post['scheduled_at'] ?? $post['published_at'] ?? null;
            if (!$rawDate) continue;

            // Convert UTC timestamp to the user's configured timezone
            try {
                $dt = new DateTime($rawDate, $utcTzObj);
                $dt->setTimezone($userTzObj);
                
                $formattedDateIso   = $dt->format('Y-m-d\TH:i:s'); 
                $formattedDateHuman = $dt->format('Y-m-d H:i'); 
            } catch (Exception $e) {
                $formattedDateIso   = $rawDate;
                $formattedDateHuman = $rawDate;
            }

            $cleanContent = strip_tags($post['content']);
            $title = mb_strlen($cleanContent) > 30 ? mb_substr($cleanContent, 0, 30) . '...' : $cleanContent;
            $fullTitle = ($post['platform_name'] ?? 'Social') . ': ' . $title;

            $pluginKey = strtolower(trim($post['plugin_id'] ?? ''));

            // Resolve color from PluginManager or fallback
            $pluginColor = $pluginColorMap[$pluginKey] ?? '#6c757d';

            // Global event indicator color logic
            if ($post['status'] === 'failed') {
                $eventColor = '#dc3545'; // Keep calendar dot red for errors
            } else {
                $eventColor = $pluginColor;
            }

            $pluginName = !empty($post['plugin_id']) ? ucfirst($post['plugin_id']) : 'Standard';

            $events[] = [
                'id' => $post['id'],
                'title' => $fullTitle,
                'start' => $formattedDateIso,
                'backgroundColor' => $eventColor,
                'borderColor' => $eventColor,
                'extendedProps' => [
                    'postId' => $post['id'],
                    'status' => ucfirst($post['status']),
                    'statusRaw' => strtolower($post['status']),
                    'platform' => $post['platform_name'] ?? 'Social',
                    'pluginName' => $pluginName,
                    'pluginColor' => $pluginColor,
                    'fullContent' => $post['content'],
                    'scheduledFor' => $formattedDateHuman
                ]
            ];
        }

        $eventsJson = json_encode($events);

        require_once __DIR__ . '/../Views/calendar/index.php';
    }

    /**
     * Delete a scheduled post via AJAX
     */
    public function delete(): void {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $postId = (int)($_POST['id'] ?? 0);

        if (!$postId) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            exit;
        }

        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT status FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $post = $stmt->fetch();

        if (!$post) {
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit;
        }

        if ($post['status'] !== 'scheduled') {
            echo json_encode(['success' => false, 'error' => 'Published or processed posts cannot be deleted from calendar']);
            exit;
        }

        $deleteStmt = $db->prepare("DELETE FROM posts WHERE id = ? AND user_id = ? AND status = 'scheduled'");
        $deleteStmt->execute([$postId, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Update a scheduled post via AJAX
     */
    public function update(): void {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $postId = (int)($_POST['id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $scheduledFor = trim($_POST['scheduled_for'] ?? '');

        if (!$postId || empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Content and ID are required']);
            exit;
        }

        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT status FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $post = $stmt->fetch();

        if (!$post) {
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit;
        }

        if ($post['status'] !== 'scheduled') {
            echo json_encode(['success' => false, 'error' => 'Published or processed posts cannot be modified']);
            exit;
        }

        $userTimezone = $_SESSION['user_timezone'] ?? 'UTC';
        try {
            $dt = new DateTime($scheduledFor, new DateTimeZone($userTimezone));
            $dt->setTimezone(new DateTimeZone('UTC'));
            $utcScheduledFor = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $utcScheduledFor = $scheduledFor;
        }

        $updateStmt = $db->prepare("
            UPDATE posts 
            SET content = ?, scheduled_for = ?, scheduled_at = ? 
            WHERE id = ? AND user_id = ? AND status = 'scheduled'
        ");
        $updateStmt->execute([$content, $utcScheduledFor, $utcScheduledFor, $postId, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }
}