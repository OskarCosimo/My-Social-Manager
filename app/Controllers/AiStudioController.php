<?php
// app/Controllers/AiStudioController.php

require_once __DIR__ . '/../Models/AiCampaign.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Models/Group.php';

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
        
        // Fetch connected accounts
        $accounts = SocialAccount::getUserAccounts($userId);

        // Fetch AI usage limits for the current user
        $aiLimits = UsageManager::getAiLimits($userId);

        // Calculate progress percentage of used AI credits
        $aiCreditsUsed    = (int)($aiLimits['ai_credits_used'] ?? 0);
        $aiCreditsMonth   = (int)($aiLimits['ai_credits_month'] ?? 0);
        $aiCreditsBalance = (int)($aiLimits['ai_credits_balance'] ?? 0);
        $aiPercent = ($aiCreditsMonth > 0) ? (int)min(100, round(($aiCreditsUsed / $aiCreditsMonth) * 100)) : 0;

        // Fetch user groups to populate the Quick Selection dropdown
        $db = Database::getInstance(); 
        $groupModel = new \App\Models\Group($db);
        $groups = $groupModel->getUserGroups($userId);

        // Fetch all campaigns created by this user
        $campaigns = AiCampaign::getByUserId($userId);

        // Load the view
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
        
        $scheduleSettings = [
            'total_posts'   => max(1, (int)($_POST['total_posts'] ?? 1)),
            'posts_per_day' => max(1, (int)($_POST['posts_per_day'] ?? 1)),
            'start_time'    => $_POST['start_time'] ?? '09:00',
            'end_time'      => $_POST['end_time'] ?? '18:00'
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
            'end_time'      => $endTime
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
}