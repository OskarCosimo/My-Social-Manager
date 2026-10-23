<?php
// app/Views/dashboard/index.php
if (!isset($_SESSION['user_id']) || !isset($limits) || !isset($plans)) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../Models/SystemSetting.php';

// Fetch Ad settings to determine status labels across plans
$customAdsScript  = SystemSetting::get('custom_ads_script') ?? '';
$adsFreeOnly      = SystemSetting::get('ads_free_only') ?? '0';
$hasAdsConfigured = !empty(trim($customAdsScript));

function getProgressPercent(int $used, int $max): int {
    if ($max <= 0) return 100;
    return (int)min(100, round(($used / $max) * 100));
}

// Compute total AI credits including extra purchased credits
$aiTotalCredits      = (int)($limits['ai_credits_month'] ?? 0) + (int)($limits['extra_ai_credits'] ?? 0);
$aiBalance           = max(0, $aiTotalCredits - (int)($limits['ai_credits_used'] ?? 0));

$accountsPercent     = getProgressPercent($limits['current_accounts'] ?? 0, $limits['max_accounts'] ?? 1);
$postsPercent        = getProgressPercent($limits['posts_used'] ?? 0, $limits['max_posts'] ?? 1);
$rssPostsPercent     = getProgressPercent($limits['rss_posts_used'] ?? 0, $limits['max_rss_posts'] ?? 1);
$channelPostsPercent = getProgressPercent($limits['channel_posts_used'] ?? 0, $limits['max_channel_posts'] ?? 1);
$aiPercent           = getProgressPercent($limits['ai_credits_used'] ?? 0, $aiTotalCredits > 0 ? $aiTotalCredits : 1);
$emailsPercent       = getProgressPercent($limits['emails_sent_month'] ?? 0, $limits['max_emails_per_month'] ?? 1);

$postPeriodLabel     = ucfirst(htmlspecialchars($limits['post_period'] ?? 'monthly'));

// Fetch configured AI credit top-up packages
$rawPacks = SystemSetting::get('ai_credit_packs');
$aiPacks = !empty($rawPacks) ? json_decode($rawPacks, true) : [
    ['credits' => 100,  'price' => 5.00],
    ['credits' => 500,  'price' => 20.00],
    ['credits' => 1000, 'price' => 35.00]
];

$freePlans    = [];
$monthlyPlans = [];
$yearlyPlans  = [];

foreach ($plans as $plan) {
    if (!empty($plan['is_hidden']) && ($plan['id'] != ($limits['plan_id'] ?? 0))) {
        continue;
    }

    $price = (float)($plan['price'] ?? 0);
    $cycle = strtolower($plan['billing_cycle'] ?? 'monthly');

    if ($price == 0.00 || $cycle === 'lifetime') {
        $freePlans[] = $plan;
    } elseif ($cycle === 'yearly') {
        $yearlyPlans[] = $plan;
    } else {
        $monthlyPlans[] = $plan;
    }
}

$activeTab = !empty($freePlans) ? 'free' : (!empty($monthlyPlans) ? 'monthly' : 'yearly');
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<style>
    .stat-card { transition: transform 0.2s ease; border-radius: 12px; }
    .stat-card:hover { transform: translateY(-4px); }
    .progress { height: 8px; border-radius: 4px; }
    .stat-icon-badge { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; flex-shrink: 0; }
    .pack-option-card { border: 2px solid #dee2e6; border-radius: 10px; cursor: pointer; transition: all 0.2s; }
    .pack-option-card:hover, .pack-option-card.selected { border-color: #6f42c1; background-color: rgba(111, 66, 193, 0.04); }
</style>

<div class="d-flex main-wrapper">

    <?php 
    $activeMenu = 'dashboard'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-0">
                    <?= str_replace('{username}', htmlspecialchars($_SESSION['username'] ?? 'User'), __('welcome_back_user')) ?>
                </h2>
                <p class="text-muted mb-0"><?= __('dashboard_overview_subtitle') ?></p>
            </div>
            <a href="/compose" class="btn btn-primary btn-lg shadow-sm d-inline-flex align-items-center gap-2">
                <i class="bi bi-pencil-square"></i> <?= __('compose_post') ?>
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-4"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0 mb-4"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Stat Cards Row 1: Social Accounts & Publishing Channels (4 Cards) -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">
            
            <!-- Social Accounts Card -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('social_accounts') ?></h6>
                            <div class="stat-icon-badge bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-share fs-5"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="display-6 fw-bold mb-0"><?= $limits['current_accounts'] ?? 0 ?></h2>
                            <span class="text-muted ms-2">/ <?= $limits['max_accounts'] ?? 1 ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $accountsPercent ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= str_replace('{percent}', $accountsPercent, __('plan_limit_reached_percent')) ?>
                        </small>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0d6efd;"></div>
                </div>
            </div>

            <!-- Dynamic Posts Usage Card -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted mb-0">
                                <?= str_replace('{period}', $postPeriodLabel, __('posts_period_title')) ?>
                            </h6>
                            <div class="stat-icon-badge bg-success bg-opacity-10 text-success">
                                <i class="bi bi-file-earmark-text fs-5"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="display-6 fw-bold mb-0"><?= $limits['posts_used'] ?? 0 ?></h2>
                            <span class="text-muted ms-2">/ <?= $limits['max_posts'] ?? 10 ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $postsPercent ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= str_replace(['{percent}', '{period}'], [$postsPercent, strtolower($postPeriodLabel)], __('posts_period_limit_reached')) ?>
                        </small>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #198754;"></div>
                </div>
            </div>

            <!-- RSS Automation Limits Card -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('rss_automation') ?></h6>
                            <div class="stat-icon-badge bg-info bg-opacity-10 text-info">
                                <i class="bi bi-rss fs-5"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-2">
                                <span class="text-muted small fw-bold"><?= __('active_feeds_label') ?></span>
                                <span class="fw-bold text-dark"><?= $limits['current_rss_feeds'] ?? 0 ?> / <?= $limits['max_rss_feeds'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex align-items-end mb-1">
                                <span class="fs-5 fw-bold mb-0"><?= $limits['rss_posts_used'] ?? 0 ?></span>
                                <span class="text-muted small ms-1"><?= str_replace('{count}', $limits['max_rss_posts'] ?? 0, __('rss_posts_count_label')) ?></span>
                            </div>
                            <div class="progress mb-1">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $rssPostsPercent ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?= str_replace(['{percent}', '{period}'], [$rssPostsPercent, strtolower($postPeriodLabel)], __('rss_limit_used')) ?>
                            </small>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0dcaf0;"></div>
                </div>
            </div>

            <!-- Channel Automations Limits Card -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('channel_automations') ?? 'Channel Automations' ?></h6>
                            <div class="stat-icon-badge bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-broadcast-pin fs-5"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-2">
                                <span class="text-muted small fw-bold">Active Channels:</span>
                                <span class="fw-bold text-dark"><?= $limits['current_channel_automations'] ?? 0 ?> / <?= $limits['max_channel_automations'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex align-items-end mb-1">
                                <span class="fs-5 fw-bold mb-0"><?= $limits['channel_posts_used'] ?? 0 ?></span>
                                <span class="text-muted small ms-1">/ <?= (int)($limits['max_channel_posts'] ?? 0) ?> Posts</span>
                            </div>
                            <div class="progress mb-1">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $channelPostsPercent ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?= str_replace(['{percent}', '{period}'], [$channelPostsPercent, strtolower($postPeriodLabel)], __('channel_limit_reached_percent') ?? '{percent}% of {period} channel posting limit reached') ?>
                            </small>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0d6efd;"></div>
                </div>
            </div>

        </div>

        <!-- Stat Cards Row 2: AI, Storage & Mailing Lists (4 Cards) -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-5">
            
            <!-- AI Credits Card with Top-Up Button -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('ai_credits_title') ?></h6>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-bold" data-bs-toggle="modal" data-bs-target="#buyCreditsModal" title="<?= __('top_up_credits') ?? 'Top Up Credits' ?>">
                                    <i class="bi bi-cart-plus-fill"></i> <?= __('buy_ai_credits') ?? 'Buy' ?>
                                </button>
                                <div class="stat-icon-badge" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                                    <i class="bi bi-stars fs-5"></i>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="display-6 fw-bold mb-0"><?= (int)($limits['ai_credits_used'] ?? 0) ?></h2>
                            <span class="text-muted ms-2">/ <?= $aiTotalCredits ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar" role="progressbar" style="width: <?= $aiPercent ?>%; background-color: #6f42c1;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <?= str_replace('{percent}', $aiPercent, __('ai_allowance_used')) ?>
                            </small>
                            <span class="badge bg-success-subtle text-success border border-success-subtle small fw-bold">
                                <?= number_format($aiBalance) ?> <?= __('remaining') ?? 'remaining' ?>
                            </span>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #6f42c1;"></div>
                </div>
            </div>

            <!-- File Manager Storage -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('file_manager') ?></h6>
                            <div class="stat-icon-badge bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-folder2-open fs-5"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                <span class="text-muted fw-bold"><?= __('storage_limit_label') ?></span>
                                <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($limits['max_storage_size'] ?? '1GB') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span class="text-muted fw-bold"><?= __('max_upload_field_label') ?></span>
                                <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($limits['max_upload_size'] ?? '50MB') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #ffc107;"></div>
                </div>
            </div>

            <!-- Mailing Lists Overview -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('mailing_lists_overview') ?></h6>
                            <div class="stat-icon-badge bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-envelope-paper fs-5"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                <span class="text-muted fw-bold"><?= __('lists_created_label') ?></span>
                                <span class="fw-bold fs-5 text-dark"><?= $limits['current_mailing_lists'] ?? 0 ?> / <?= $limits['max_mailing_lists'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span class="text-muted fw-bold"><?= __('max_subscribers_per_list_label') ?></span>
                                <span class="fw-bold fs-5 text-dark"><?= number_format($limits['max_subscribers_per_list'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #dc3545;"></div>
                </div>
            </div>

            <!-- Monthly Email Limit -->
            <div class="col">
                <div class="card stat-card h-100 shadow-sm border-0 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('monthly_emails_sent_title') ?></h6>
                            <div class="stat-icon-badge bg-dark bg-opacity-10 text-dark">
                                <i class="bi bi-send fs-5"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="display-6 fw-bold mb-0"><?= number_format($limits['emails_sent_month'] ?? 0) ?></h2>
                            <span class="text-muted ms-2">/ <?= number_format($limits['max_emails_per_month'] ?? 0) ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-dark" role="progressbar" style="width: <?= $emailsPercent ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= str_replace('{percent}', $emailsPercent, __('monthly_email_limit_used')) ?>
                        </small>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #212529;"></div>
                </div>
            </div>

        </div>

        <div class="row">
            <!-- Left Column: Quick Actions -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-lightning-charge text-primary"></i> <?= __('quick_actions') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="/accounts" class="btn btn-outline-secondary d-flex align-items-center justify-content-start p-3 text-start">
                                <div class="stat-icon-badge bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-share fs-5"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= __('connect_social_account') ?></strong>
                                    <small class="text-muted"><?= __('connect_social_account_sub') ?></small>
                                </div>
                            </a>
                            <a href="/channel-automations" class="btn btn-outline-secondary d-flex align-items-center justify-content-start p-3 text-start">
                                <div class="stat-icon-badge bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-broadcast-pin fs-5"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= __('channel_automations') ?? 'Channel Automations' ?></strong>
                                    <small class="text-muted">Automate video & social page cross-posting</small>
                                </div>
                            </a>
                            <a href="/mailinglists" class="btn btn-outline-secondary d-flex align-items-center justify-content-start p-3 text-start">
                                <div class="stat-icon-badge bg-danger bg-opacity-10 text-danger me-3">
                                    <i class="bi bi-envelope-paper fs-5"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= __('manage_mailing_lists') ?></strong>
                                    <small class="text-muted"><?= __('manage_mailing_lists_sub') ?></small>
                                </div>
                            </a>
                            <a href="/calendar" class="btn btn-outline-secondary d-flex align-items-center justify-content-start p-3 text-start">
                                <div class="stat-icon-badge bg-success bg-opacity-10 text-success me-3">
                                    <i class="bi bi-calendar3 fs-5"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= __('view_calendar') ?></strong>
                                    <small class="text-muted"><?= __('view_calendar_sub') ?></small>
                                </div>
                            </a>
                            <a href="/filemanager/standalone" class="btn btn-outline-secondary d-flex align-items-center justify-content-start p-3 text-start">
                                <div class="stat-icon-badge bg-warning bg-opacity-10 text-warning me-3">
                                    <i class="bi bi-images fs-5"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= __('manage_media') ?></strong>
                                    <small class="text-muted"><?= __('manage_media_sub') ?></small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Current Plan Box -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm border-0 bg-primary text-white h-100 overflow-hidden position-relative">
                    <div class="position-absolute top-0 end-0 p-5 opacity-25" style="transform: scale(2) translate(10%, -10%); font-size: 10rem; pointer-events: none;">
                        <i class="bi bi-rocket-takeoff"></i>
                    </div>
                    
                    <div class="card-body p-4 d-flex flex-column justify-content-between z-1 position-relative">
                        <div>
                            <?php 
                            $price = (float)($limits['price'] ?? 0.00);
                            $isFree = ($price == 0.00);
                            $billingCycle = ucfirst(htmlspecialchars($limits['billing_cycle'] ?? 'monthly'));
                            
                            $rawExpiry = $limits['current_period_end'] ?? $limits['premium_expires_at'] ?? null;
                            $formattedExpiry = (!empty($rawExpiry) && $rawExpiry !== '0000-00-00 00:00:00') 
                                               ? date('M d, Y', strtotime($rawExpiry)) 
                                               : null;
                            
                            $hasPendingDowngrade = !empty($limits['has_pending_downgrade']);
                            $pendingPlanName = htmlspecialchars($limits['pending_plan_name'] ?? 'Free');
                            ?>

                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <span class="badge <?= $isFree ? 'bg-success' : 'bg-warning text-dark' ?> fw-bold text-uppercase px-3 py-2 d-inline-flex align-items-center gap-1">
                                    <i class="bi <?= $isFree ? 'bi-check-circle-fill' : 'bi-star-fill' ?>"></i>
                                    <?= $isFree ? __('free_plan_badge') : __('paid_plan_badge') ?>
                                </span>
                                <span class="badge bg-white bg-opacity-25 text-white fw-semibold fs-6">
                                    <?= str_replace('{cycle}', $billingCycle, __('billing_label')) ?> <?= !$isFree ? '(€' . number_format($price, 2) . ')' : '' ?>
                                </span>
                            </div>

                            <h3 class="display-6 fw-bold mb-3">
                                <?= str_replace('{name}', htmlspecialchars($limits['plan_name'] ?? 'Free'), __('current_plan_title')) ?>
                            </h3>

                            <div class="bg-white bg-opacity-10 p-3 rounded border border-white border-opacity-25 mb-3">
                                <?php if (!$isFree): ?>
                                    <p class="fs-6 mb-1 d-flex align-items-center flex-wrap gap-1">
                                        <i class="bi bi-calendar-check me-1"></i> <strong><?= __('subscription_active_until') ?></strong> 
                                        <?php if ($formattedExpiry): ?>
                                            <mark class="bg-warning text-dark fw-bold px-2 py-1 rounded"><?= $formattedExpiry ?></mark>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= __('active_plan') ?></span>
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($hasPendingDowngrade): ?>
                                        <div class="mt-3 p-3 bg-dark bg-opacity-25 rounded border border-warning">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div>
                                                    <strong class="text-warning d-flex align-items-center gap-1">
                                                        <i class="bi bi-exclamation-triangle-fill"></i> <?= __('scheduled_downgrade_notice') ?>
                                                    </strong>
                                                    <span class="small">
                                                        <?= str_replace(['{current}', '{pending}', '{date}'], [htmlspecialchars($limits['plan_name']), $pendingPlanName, $formattedExpiry ?? 'period end'], __('downgrade_notice_text')) ?>
                                                    </span>
                                                </div>
                                                <form action="/billing/cancel-downgrade" method="POST" class="m-0">
                                                    <button type="submit" class="btn btn-sm btn-outline-light text-nowrap fw-bold d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-x-circle"></i> <?= __('cancel_downgrade') ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <small class="opacity-75 d-block mt-1"><?= __('paid_features_active_note') ?></small>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <p class="fs-6 mb-0 d-flex align-items-center gap-2">
                                        <i class="bi bi-info-circle fs-5"></i>
                                        <span><?= __('free_tier_active_note') ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top border-white border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small opacity-75">
                                <?= __('need_more_limits_prompt') ?>
                            </div>
                            <button class="btn btn-light fw-bold text-primary shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#upgradeModal">
                                <?= __('change_upgrade_plan_btn') ?> <i class="bi bi-lightning-charge-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODALE ACQUISTO CREDITI AI (ONE-TIME TOP-UP VIA STRIPE O PAYPAL) -->
<div class="modal fade" id="buyCreditsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-stars text-primary"></i> <?= __('buy_ai_credits_title') ?? 'Purchase Additional AI Credits' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">
                    <?= __('buy_ai_credits_desc') ?? 'Top up your account with extra AI credits anytime. Purchased credits never expire and are consumed only after your plan allowance is depleted.' ?>
                </p>

                <div class="row g-3 mb-4">
                    <?php foreach ($aiPacks as $index => $pack): ?>
                        <div class="col-md-4">
                            <div class="p-3 pack-option-card text-center <?= $index === 0 ? 'selected' : '' ?>" data-credits="<?= (int)$pack['credits'] ?>" data-price="<?= number_format((float)$pack['price'], 2, '.', '') ?>">
                                <div class="badge bg-primary bg-opacity-10 text-primary mb-2 fw-bold px-3 py-1">
                                    <i class="bi bi-lightning-charge"></i> <?= number_format((int)$pack['credits']) ?> Credits
                                </div>
                                <h3 class="fw-bold mb-0 text-dark">€<?= number_format((float)$pack['price'], 2) ?></h3>
                                <small class="text-muted">one-time payment</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border rounded p-3 bg-light mb-3">
                    <label class="form-label fw-bold text-dark mb-2"><?= __('select_payment_method') ?? 'Choose Payment Method:' ?></label>
                    
                    <div class="d-flex gap-3">
                        <form action="/billing/buy-credits/stripe" method="POST" class="flex-grow-1" id="stripeCreditFormDash">
                            <input type="hidden" name="credits" id="stripe_credits_val_dash" value="<?= (int)($aiPacks[0]['credits'] ?? 100) ?>">
                            <input type="hidden" name="price" id="stripe_price_val_dash" value="<?= number_format((float)($aiPacks[0]['price'] ?? 5.00), 2, '.', '') ?>">
                            <button type="submit" class="btn btn-outline-primary btn-lg w-100 fw-bold d-inline-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-credit-card-2-front"></i> <?= __('pay_with_card') ?? 'Card (Stripe)' ?>
                            </button>
                        </form>

                        <form action="/billing/buy-credits/paypal" method="POST" class="flex-grow-1" id="paypalCreditFormDash">
                            <input type="hidden" name="credits" id="paypal_credits_val_dash" value="<?= (int)($aiPacks[0]['credits'] ?? 100) ?>">
                            <input type="hidden" name="price" id="paypal_price_val_dash" value="<?= number_format((float)($aiPacks[0]['price'] ?? 5.00), 2, '.', '') ?>">
                            <button type="submit" class="btn btn-outline-warning text-dark btn-lg w-100 fw-bold d-inline-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-paypal text-primary"></i> <?= __('pay_with_paypal') ?? 'PayPal' ?>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('close') ?? 'Close' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODALE CAMBIO PIANO -->
<div class="modal fade" id="upgradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold fs-4 d-flex align-items-center gap-2">
                    <i class="bi bi-box2-heart text-primary"></i> <?= __('change_or_upgrade_plan_modal') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-5">
                
                <ul class="nav nav-pills justify-content-center mb-4" id="billingTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'free' ? 'active' : '' ?> fw-bold px-4 d-inline-flex align-items-center gap-2" id="free-tab" data-bs-toggle="tab" data-bs-target="#free-plans" type="button" role="tab">
                            <i class="bi bi-flower1"></i> <?= __('free_plan_badge') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'monthly' ? 'active' : '' ?> fw-bold px-4 d-inline-flex align-items-center gap-2" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly-plans" type="button" role="tab">
                            <i class="bi bi-calendar-event"></i> <?= __('monthly') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'yearly' ? 'active' : '' ?> fw-bold px-4 d-inline-flex align-items-center gap-2" id="yearly-tab" data-bs-toggle="tab" data-bs-target="#yearly-plans" type="button" role="tab">
                            <i class="bi bi-gift"></i> <?= __('yearly') ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="billingTabContent">
                    
                    <?php
                    $currentPrice = (float)($limits['price'] ?? 0);
                    
                    $renderPlanCard = function($plan, $limits, $currentPrice) use ($hasAdsConfigured, $adsFreeOnly) {
                        $isCurrent = ($plan['id'] == ($limits['plan_id'] ?? 0));
                        $planPrice = (float)($plan['price'] ?? 0);
                        $isFreePlan = ($planPrice == 0.00);
                        $isDowngrade = ($planPrice < $currentPrice);
                        ?>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm <?= $isCurrent ? 'border-primary border-2' : '' ?>">
                                <div class="position-absolute top-0 end-0 mt-3 me-3 d-flex gap-1">
                                    <?php if (!empty($plan['is_default'])): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i><?= __('default_badge') ?></span>
                                    <?php endif; ?>
                                    <?php if ($isCurrent): ?>
                                        <span class="badge bg-primary"><?= __('active_plan') ?></span>
                                    <?php endif; ?>
                                    <?php if ($hasAdsConfigured && $adsFreeOnly === '1' && !$isFreePlan): ?>
                                        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i><?= __('ads_free_badge') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-4 text-center d-flex flex-column">
                                    <h4 class="fw-bold mb-3"><?= htmlspecialchars($plan['name']) ?></h4>
                                    <h2 class="display-5 fw-bold text-primary mb-4">
                                        €<?= number_format($planPrice, 2) ?>
                                        <span class="fs-6 text-muted fw-normal">/<?= htmlspecialchars($plan['billing_cycle']) ?></span>
                                    </h2>
                                    
                                    <ul class="list-unstyled text-start mb-4 flex-grow-1">
                                        <li class="mb-2 d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?= str_replace('{count}', (int)$plan['max_accounts'], __('up_to_social_accounts')) ?></span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?= str_replace(['{count}', '{period}'], [(int)$plan['max_posts'], htmlspecialchars($plan['post_period'] ?? 'monthly')], __('posts_per')) ?></span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?= str_replace('{count}', (int)$plan['ai_credits_month'], __('ai_credits_limit')) ?></span>
                                        </li>
                                        <!-- RSS Feeds Limits -->
                                        <li class="mb-2 border-top pt-2 mt-2 text-info d-flex align-items-center">
                                            <i class="bi bi-rss text-info me-2"></i>
                                            <span><?= str_replace('{count}', (int)($plan['max_rss_feeds'] ?? 0), __('rss_feeds_limit')) ?></span>
                                        </li>
                                        <li class="mb-2 text-info d-flex align-items-center">
                                            <i class="bi bi-robot text-info me-2"></i>
                                            <span><?= str_replace(['{count}', '{period}'], [(int)($plan['max_rss_posts'] ?? 0), htmlspecialchars($plan['post_period'] ?? 'monthly')], __('rss_posts_limit')) ?></span>
                                        </li>
                                        <!-- Channel Automations Limits -->
                                        <li class="mb-2 border-top pt-2 mt-2 text-primary d-flex align-items-center">
                                            <i class="bi bi-broadcast-pin text-primary me-2"></i>
                                            <span><?= str_replace('{count}', (int)($plan['max_channel_automations'] ?? 0), __('channel_automations_limit')) ?></span>
                                        </li>
                                        <li class="mb-2 text-primary d-flex align-items-center">
                                            <i class="bi bi-shuffle text-primary me-2"></i>
                                            <span><?= str_replace(['{count}', '{period}'], [(int)($plan['max_channel_posts'] ?? 0), htmlspecialchars($plan['post_period'] ?? 'monthly')], __('channel_posts_limit')) ?></span>
                                        </li>
                                        <!-- Mailing Lists Limits -->
                                        <li class="mb-2 border-top pt-2 mt-2 text-danger d-flex align-items-center">
                                            <i class="bi bi-envelope-paper text-danger me-2"></i>
                                            <span><?= str_replace('{count}', (int)($plan['max_mailing_lists'] ?? 0), __('mailing_lists_limit')) ?></span>
                                        </li>
                                        <li class="mb-2 text-danger d-flex align-items-center">
                                            <i class="bi bi-people text-danger me-2"></i>
                                            <span><?= str_replace('{count}', number_format((int)($plan['max_subscribers_per_list'] ?? 0)), __('max_subscribers_limit')) ?></span>
                                        </li>
                                        <li class="mb-2 text-danger d-flex align-items-center">
                                            <i class="bi bi-send text-danger me-2"></i>
                                            <span><?= str_replace('{count}', number_format((int)($plan['max_emails_per_month'] ?? 0)), __('monthly_email_limit')) ?></span>
                                        </li>
                                        <!-- Storage & Upload Limits -->
                                        <li class="mb-2 border-top pt-2 mt-2 text-warning d-flex align-items-center">
                                            <i class="bi bi-folder2 text-warning me-2"></i>
                                            <span><?= str_replace('{size}', htmlspecialchars($plan['max_storage_size'] ?? '1GB'), __('storage_label')) ?></span>
                                        </li>
                                        <li class="mb-2 text-warning d-flex align-items-center">
                                            <i class="bi bi-cloud-arrow-up text-warning me-2"></i>
                                            <span><?= str_replace('{size}', htmlspecialchars($plan['max_upload_size'] ?? '50MB'), __('max_upload_label')) ?></span>
                                        </li>
                                        <?php if ($hasAdsConfigured): ?>
                                            <?php if ($adsFreeOnly === '1' && !$isFreePlan): ?>
                                                <li class="mb-2 border-top pt-2 mt-2 text-success d-flex align-items-center">
                                                    <i class="bi bi-shield-check text-success me-2"></i>
                                                    <span><?= __('ads_free_badge') ?></span>
                                                </li>
                                            <?php else: ?>
                                                <li class="mb-2 border-top pt-2 mt-2 text-secondary d-flex align-items-center">
                                                    <i class="bi bi-megaphone text-secondary me-2"></i>
                                                    <span><?= __('with_ads') ?></span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </ul>

                                    <div class="mt-auto">
                                        <?php if ($isCurrent): ?>
                                            <button disabled class="btn btn-secondary w-100 fw-bold"><?= __('active_plan') ?></button>
                                        <?php else: ?>
                                            <form action="/billing/change-plan" method="POST" onsubmit="return <?= $isDowngrade ? "confirm('" . addslashes(str_replace('{name}', htmlspecialchars($plan['name']), __('confirm_plan_change'))) . "')" : "true" ?>;">
                                                <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                                                <input type="hidden" name="price_id" value="<?= htmlspecialchars($plan['stripe_price_id'] ?? '') ?>">
                                                <button type="submit" class="btn <?= $isDowngrade ? 'btn-outline-warning text-dark' : 'btn-outline-primary' ?> w-100 fw-bold">
                                                    <?= $isDowngrade ? str_replace('{name}', htmlspecialchars($plan['name']), __('downgrade_to_plan')) : __('select_plan') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    };
                    ?>

                    <div class="tab-pane fade <?= $activeTab === 'free' ? 'show active' : '' ?>" id="free-plans" role="tabpanel">
                        <div class="row g-4 justify-content-center">
                            <?php if (empty($freePlans)): ?>
                                <div class="col-12 text-center text-muted py-4"><?= __('no_free_plans') ?></div>
                            <?php else: ?>
                                <?php foreach ($freePlans as $plan) { $renderPlanCard($plan, $limits, $currentPrice); } ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade <?= $activeTab === 'monthly' ? 'show active' : '' ?>" id="monthly-plans" role="tabpanel">
                        <div class="row g-4 justify-content-center">
                            <?php if (empty($monthlyPlans)): ?>
                                <div class="col-12 text-center text-muted py-4"><?= __('no_monthly_plans') ?></div>
                            <?php else: ?>
                                <?php foreach ($monthlyPlans as $plan) { $renderPlanCard($plan, $limits, $currentPrice); } ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade <?= $activeTab === 'yearly' ? 'show active' : '' ?>" id="yearly-plans" role="tabpanel">
                        <div class="row g-4 justify-content-center">
                            <?php if (empty($yearlyPlans)): ?>
                                <div class="col-12 text-center text-muted py-4"><?= __('no_yearly_plans') ?></div>
                            <?php else: ?>
                                <?php foreach ($yearlyPlans as $plan) { $renderPlanCard($plan, $limits, $currentPrice); } ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pack selection handler in Dashboard Buy Credits Modal
    document.querySelectorAll('#buyCreditsModal .pack-option-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('#buyCreditsModal .pack-option-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            const credits = this.dataset.credits;
            const price = this.dataset.price;

            document.getElementById('stripe_credits_val_dash').value = credits;
            document.getElementById('stripe_price_val_dash').value   = price;
            document.getElementById('paypal_credits_val_dash').value = credits;
            document.getElementById('paypal_price_val_dash').value   = price;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
