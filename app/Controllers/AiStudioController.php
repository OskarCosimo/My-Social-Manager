<?php
// app/Controllers/AiStudioController.php

require_once __DIR__ . '/../Models/AiCampaign.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Models/Group.php';
require_once __DIR__ . '/../Core/Database.php';

class AiStudioController {
    
    /**
     * Display the AI Studio campaign creation form and active campaigns list
     */
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        
        $accounts = SocialAccount::getUserAccounts($userId);
        $aiLimits = UsageManager::getAiLimits($userId);

        $aiCreditsUsed    = (int)($aiLimits['ai_credits_used'] ?? 0);
        $aiCreditsMonth   = (int)($aiLimits['ai_credits_month'] ?? 0);
        $aiCreditsBalance = (int)($aiLimits['ai_credits_balance'] ?? 0);
        $aiPercent = ($aiCreditsMonth > 0) ? (int)min(100, round(($aiCreditsUsed / $aiCreditsMonth) * 100)) : 0;

        $db = Database::getInstance(); 
        $groupModel = new \App\Models\Group($db);
        $groups = $groupModel->getUserGroups($userId);

        $campaigns = AiCampaign::getByUserId($userId);

        require_once __DIR__ . '/../Views/ai_studio/index.php';
    }

    /**
     * Process the form submission and store the campaign
     */
    public function store() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        
        $prompt = trim($_POST['prompt'] ?? '');
        $selectedAccounts = $_POST['accounts'] ?? [];
        $uniquePosts = !empty($_POST['unique_posts']) ? 1 : 0;
        
        $scheduleSettings = [
            'total_posts'   => max(1, (int)($_POST['total_posts'] ?? 1)),
            'posts_per_day' => max(1, (int)($_POST['posts_per_day'] ?? 1)),
            'start_time'    => $_POST['start_time'] ?? '09:00',
            'end_time'      => $_POST['end_time'] ?? '18:00',
            'unique_posts'  => $uniquePosts
        ];

        if (empty($prompt) || empty($selectedAccounts)) {
            $_SESSION['error'] = __('ai_studio_missing_fields') ?? 'Prompt and at least one target account are required.';
            header('Location: /ai-studio');
            exit;
        }

        $aiLimits = UsageManager::getAiLimits($userId);

        if ($aiLimits['ai_credits_balance'] <= 0) {
            $_SESSION['error'] = __('ai_credits_exhausted_error') ?? 'You have exhausted your AI credits for this month.';
            header('Location: /ai-studio');
            exit;
        }
        
        if ($scheduleSettings['total_posts'] > $aiLimits['ai_max_campaign_posts']) {
            $_SESSION['error'] = __('plan_campaign_limit_exceeded') ?? 'Your plan does not allow this many posts in a single campaign.';
            header('Location: /ai-studio');
            exit;
        }

        if ($scheduleSettings['posts_per_day'] > $aiLimits['ai_max_daily_posts']) {
            $_SESSION['error'] = __('plan_daily_limit_exceeded') ?? 'Your plan does not allow this many posts per day.';
            header('Location: /ai-studio');
            exit;
        }

        AiCampaign::create($userId, $prompt, $selectedAccounts, $scheduleSettings);

        $_SESSION['success'] = __('campaign_queued_success') ?? 'Campaign successfully queued! The AI worker will process it shortly.';
        header('Location: /ai-studio');
        exit;
    }

    /**
     * Update an existing campaign
     */
    public function update() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $prompt = trim($_POST['prompt'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $uniquePosts = !empty($_POST['unique_posts']) ? 1 : 0;

        if (!in_array($status, ['active', 'paused', 'completed'])) {
            $status = 'active';
        }

        $existing = AiCampaign::getById($campaignId, $userId);
        if (!$existing) {
            $_SESSION['error'] = __('campaign_not_found') ?? 'Campaign not found or access denied.';
            header('Location: /ai-studio');
            exit;
        }

        $aiLimits = UsageManager::getAiLimits($userId);
        $totalPosts = max(1, (int)($_POST['total_posts'] ?? ($existing['schedule_settings']['total_posts'] ?? 1)));
        $postsPerDay = max(1, (int)($_POST['posts_per_day'] ?? ($existing['schedule_settings']['posts_per_day'] ?? 1)));
        $startTime = $_POST['start_time'] ?? ($existing['schedule_settings']['start_time'] ?? '09:00');
        $endTime = $_POST['end_time'] ?? ($existing['schedule_settings']['end_time'] ?? '18:00');

        if ($totalPosts > $aiLimits['ai_max_campaign_posts']) {
            $_SESSION['error'] = __('plan_campaign_limit_exceeded') ?? 'Total posts exceed your plan allowance.';
            header('Location: /ai-studio');
            exit;
        }

        if ($postsPerDay > $aiLimits['ai_max_daily_posts']) {
            $_SESSION['error'] = __('plan_daily_limit_exceeded') ?? 'Daily posts exceed your plan allowance.';
            header('Location: /ai-studio');
            exit;
        }

        $scheduleSettings = [
            'total_posts'   => $totalPosts,
            'posts_per_day' => $postsPerDay,
            'start_time'    => $startTime,
            'end_time'      => $endTime,
            'unique_posts'  => $uniquePosts
        ];

        AiCampaign::update($campaignId, $userId, $prompt, $scheduleSettings, $status);

        $_SESSION['success'] = __('campaign_updated_success') ?? 'Campaign updated successfully.';
        header('Location: /ai-studio');
        exit;
    }

    /**
     * Delete a campaign and its scheduled posts
     */
    public function delete() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $campaignId = (int)($_POST['campaign_id'] ?? 0);

        if ($campaignId <= 0) {
            $_SESSION['error'] = __('invalid_campaign_id') ?? 'Invalid campaign ID.';
            header('Location: /ai-studio');
            exit;
        }

        $success = AiCampaign::deleteWithScheduledPosts($campaignId, $userId);

        if ($success) {
            $_SESSION['success'] = __('campaign_deleted_success') ?? 'Campaign and its scheduled posts have been deleted.';
        } else {
            $_SESSION['error'] = __('campaign_delete_error') ?? 'Failed to delete campaign.';
        }

        header('Location: /ai-studio');
        exit;
    }

    /**
     * Return scheduled posts of a campaign in JSON format for the modal DataTables
     */
    public function getCampaignPosts($campaignId): void {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $campaignId = (int)$campaignId;

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.id, p.content, p.scheduled_for, p.status, p.created_at,
                   s.platform_name, s.plugin_id
            FROM posts p
            LEFT JOIN social_accounts s ON p.social_account_id = s.id
            WHERE p.campaign_id = :campaign_id 
              AND p.user_id = :user_id 
              AND p.status = 'scheduled'
            ORDER BY p.scheduled_for ASC
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':user_id' => $userId]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'posts' => $posts]);
        exit;
    }

    /**
     * Delete selected scheduled posts
     */
    public function deleteCampaignPosts(): void {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $postIds = $input['post_ids'] ?? [];

        if (!is_array($postIds)) {
            $postIds = [$postIds];
        }

        $postIds = array_map('intval', array_filter($postIds));

        if (empty($postIds)) {
            echo json_encode(['success' => false, 'error' => 'No posts selected']);
            exit;
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $params = array_merge($postIds, [$userId]);

        $stmt = $db->prepare("
            DELETE FROM posts 
            WHERE id IN ($placeholders) 
              AND user_id = ? 
              AND status = 'scheduled'
        ");
        $stmt->execute($params);
        $deletedCount = $stmt->rowCount();

        echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
        exit;
    }

    /**
     * Delete all scheduled posts for a campaign
     */
    public function deleteAllCampaignPosts(): void {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $campaignId = (int)($input['campaign_id'] ?? 0);

        if ($campaignId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid campaign ID']);
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            DELETE FROM posts 
            WHERE campaign_id = :campaign_id 
              AND user_id = :user_id 
              AND status = 'scheduled'
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':user_id' => $userId]);
        $deletedCount = $stmt->rowCount();

        echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
        exit;
    }
}
