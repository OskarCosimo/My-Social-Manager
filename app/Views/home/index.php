<?php
// app/Views/home/index.php

require_once __DIR__ . '/../../Models/SystemSetting.php';
require_once __DIR__ . '/../../Core/PluginManager.php';
require_once __DIR__ . '/../../Models/PluginSetting.php';

// Load active plugins
$pluginManager = new PluginManager();
$allPlugins = $pluginManager->getPlugins();
$activePluginNames = [];

foreach ($allPlugins as $p) {
    $pSettings = PluginSetting::getForPlugin($p->getId());
    if (($pSettings['is_active'] ?? '0') === '1') {
        $activePluginNames[] = $p->getName();
    }
}

// Fetch Ad settings to determine status labels across plans
$customAdsScript  = SystemSetting::get('custom_ads_script') ?? '';
$adsFreeOnly      = SystemSetting::get('ads_free_only') ?? '0';
$hasAdsConfigured = !empty(trim($customAdsScript));

// 1. Determine current language from cookie (default to 'en')
$currentLang = $_COOKIE['lang'] ?? 'en';

// 2. Dynamically load available languages checking both root /lang and app/lang
$langPathRoot = __DIR__ . '/../../../lang/*.json';
$langPathApp  = __DIR__ . '/../../lang/*.json';

$files = array_merge(glob($langPathRoot) ?: [], glob($langPathApp) ?: []);

$availableLanguages = [];
foreach ($files as $file) {
    $langCode = basename($file, '.json');
    if (!in_array($langCode, $availableLanguages)) {
        $availableLanguages[] = $langCode;
    }
}

if (empty($availableLanguages)) {
    $availableLanguages = ['en'];
}

// Separate visible plans into Free, Monthly, and Yearly arrays
$freePlans    = [];
$monthlyPlans = [];
$yearlyPlans  = [];

if (isset($plans) && is_array($plans)) {
    foreach ($plans as $plan) {
        if (!empty($plan['is_hidden'])) {
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
}
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<style>
    .feature-icon-badge {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
</style>

<nav class="navbar navbar-expand-lg shadow-sm" style="background-color:#DAA520;">
    <div class="container">
        <a class="navbar-brand p-0" href="/">
            <img style="width:200px; height:50px; object-fit: contain;" src="https://socialmanager.myetv.tv/images/MYETV_logo_socialmanager_2.png" alt="Logo" />
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item dropdown me-3">
                    <a class="nav-link dropdown-toggle text-uppercase text-white fw-semibold d-inline-flex align-items-center gap-1" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-globe"></i> <?= htmlspecialchars($currentLang) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="langDropdown">
                        <?php foreach ($availableLanguages as $lang): ?>
                            <li>
                                <a class="dropdown-item lang-selector <?= $currentLang === $lang ? 'active fw-bold' : '' ?>" href="#" data-lang="<?= htmlspecialchars($lang) ?>">
                                    <?= strtoupper(htmlspecialchars($lang)) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <li class="nav-item">
                        <a href="/dashboard" class="btn btn-outline-light d-inline-flex align-items-center gap-1">
                            <i class="bi bi-speedometer2"></i> <?= __('go_to_dashboard') ?>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2 mb-2 mb-lg-0">
                        <a href="/login" class="btn btn-outline-light w-100"><?= __('login') ?></a>
                    </li>
                    <li class="nav-item">
                        <a href="/register" class="btn btn-dark w-100"><?= __('register') ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container mt-5 pt-4 text-center">
    <h1 class="display-4 fw-bold mb-3"><?= __('manage_all_your_social_in_one_place') ?></h1>
    <p class="lead text-muted mb-5"><?= __('publish_schedule_automate_posts') ?></p>

    <!-- Card Full Width: Supported Plugins -->
    <div class="card shadow-sm border-0 mb-5 text-start bg-white rounded-3">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-3">
                <div class="feature-icon-badge bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-plugin"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-1"><?= __('supported_platforms') ?></h4>
                    <p class="text-muted mb-0 small"><?= __('supported_platforms_sub') ?></p>
                </div>
            </div>
            
            <div class="d-flex flex-wrap gap-2 pt-3 border-top">
                <?php if (!empty($activePluginNames)): ?>
                    <?php foreach ($activePluginNames as $pluginName): ?>
                        <span class="badge bg-light text-dark border px-3 py-2 fs-6 fw-normal shadow-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-check2-circle text-primary"></i> <?= htmlspecialchars($pluginName) ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted small"><?= __('no_active_plugins') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Feature Grid -->
    <div class="row g-4 my-5 text-start">
        <div class="col-md-4">
            <div class="p-4 border rounded shadow-sm bg-white h-100 d-flex flex-column">
                <div class="feature-icon-badge bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="bi bi-share"></i>
                </div>
                <h4 class="fw-bold"><?= __('feature_social_title') ?></h4>
                <p class="text-muted mb-0"><?= __('feature_social_desc') ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-4 border rounded shadow-sm bg-white h-100 d-flex flex-column">
                <div class="feature-icon-badge bg-danger bg-opacity-10 text-danger mb-3">
                    <i class="bi bi-envelope-paper"></i>
                </div>
                <h4 class="fw-bold"><?= __('feature_mailing_title') ?></h4>
                <p class="text-muted mb-0"><?= __('feature_mailing_desc') ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-4 border rounded shadow-sm bg-white h-100 d-flex flex-column">
                <div class="feature-icon-badge mb-3" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                    <i class="bi bi-stars"></i>
                </div>
                <h4 class="fw-bold"><?= __('feature_ai_title') ?></h4>
                <p class="text-muted mb-0"><?= __('feature_ai_desc') ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-4 border rounded shadow-sm bg-white h-100 d-flex flex-column">
                <div class="feature-icon-badge bg-success bg-opacity-10 text-success mb-3">
                    <i class="bi bi-calendar3"></i>
                </div>
                <h4 class="fw-bold"><?= __('feature_calendar_title') ?></h4>
                <p class="text-muted mb-0"><?= __('feature_calendar_desc') ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-4 border rounded shadow-sm bg-white h-100 d-flex flex-column">
                <div class="feature-icon-badge bg-warning bg-opacity-10 text-warning mb-3">
                    <i class="bi bi-folder2-open"></i>
                </div>
                <h4 class="fw-bold"><?= __('feature_media_title') ?></h4>
                <p class="text-muted mb-0"><?= __('feature_media_desc') ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-4 border rounded shadow-sm bg-white h-100 d-flex flex-column">
                <div class="feature-icon-badge bg-info bg-opacity-10 text-info mb-3">
                    <i class="bi bi-collection"></i>
                </div>
                <h4 class="fw-bold"><?= __('feature_groups_title') ?></h4>
                <p class="text-muted mb-0"><?= __('feature_groups_desc') ?></p>
            </div>
        </div>
    </div>
    
    <!-- Pricing Plans Section -->
    <div class="mt-5 pt-5 border-top">
        <h2 class="fw-bold mb-4"><?= __('choose_your_plan') ?></h2>

        <!-- FREE PLANS SECTION -->
        <?php if (!empty($freePlans)): ?>
            <div class="mb-5">
                <div class="row row-cols-1 row-cols-md-2 g-4 justify-content-center">
                    <?php foreach ($freePlans as $plan): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-success">
                                <div class="position-absolute top-0 end-0 mt-3 me-3 d-flex gap-1">
                                    <span class="badge bg-success d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-check-circle-fill"></i> <?= __('free_plan_badge') ?>
                                    </span>
                                    <?php if (!empty($plan['is_default'])): ?>
                                        <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-star-fill"></i> <?= __('default_badge') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body p-4 d-flex flex-column text-center">
                                    <h4 class="card-title fw-bold mt-2"><?= htmlspecialchars($plan['name']) ?></h4>
                                    <h2 class="display-5 fw-bold my-3 text-success">
                                        €0.00
                                        <span class="fs-6 text-muted fw-normal">/ <?= htmlspecialchars($plan['billing_cycle']) ?></span>
                                    </h2>

                                    <ul class="list-unstyled text-start mb-4 flex-grow-1">
                                        <li class="mb-2 d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?= str_replace('{count}', (int)$plan['max_accounts'], __('up_to_social_accounts')) ?></span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?= str_replace(['{count}', '{period}'], [(int)$plan['max_posts'], htmlspecialchars($plan['post_period'])], __('posts_per')) ?></span>
                                        </li>
                                        <li class="mb-2 d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?= str_replace('{count}', (int)$plan['ai_credits_month'], __('ai_credits_limit')) ?></span>
                                        </li>
                                        <li class="mb-2 border-top pt-2 mt-2 text-info d-flex align-items-center">
                                            <i class="bi bi-rss text-info me-2"></i>
                                            <span><?= str_replace('{count}', (int)($plan['max_rss_feeds'] ?? 0), __('rss_feeds_limit')) ?></span>
                                        </li>
                                        <li class="mb-2 text-info d-flex align-items-center">
                                            <i class="bi bi-robot text-info me-2"></i>
                                            <span><?= str_replace(['{count}', '{period}'], [(int)($plan['max_rss_posts'] ?? 0), htmlspecialchars($plan['post_period'])], __('rss_posts_limit')) ?></span>
                                        </li>
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
                                        <li class="mb-2 border-top pt-2 mt-2 text-warning d-flex align-items-center">
                                            <i class="bi bi-folder2 text-warning me-2"></i>
                                            <span><?= str_replace('{size}', htmlspecialchars($plan['max_storage_size'] ?? '1GB'), __('storage_label')) ?></span>
                                        </li>
                                        <li class="mb-2 text-warning d-flex align-items-center">
                                            <i class="bi bi-cloud-arrow-up text-warning me-2"></i>
                                            <span><?= str_replace('{size}', htmlspecialchars($plan['max_upload_size'] ?? '50MB'), __('max_upload_limit')) ?></span>
                                        </li>
                                        <?php if ($hasAdsConfigured): ?>
                                            <li class="mb-2 border-top pt-2 mt-2 text-secondary d-flex align-items-center">
                                                <i class="bi bi-megaphone text-secondary me-2"></i>
                                                <span><?= __('with_ads') ?></span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>

                                    <div class="mt-auto">
                                        <?php if (!isset($isLoggedIn) || !$isLoggedIn): ?>
                                            <a href="/register" class="btn btn-lg w-100 btn-outline-success fw-bold"><?= __('get_started_free') ?></a>
                                        <?php else: ?>
                                            <a href="/dashboard" class="btn btn-lg w-100 btn-secondary fw-bold"><?= __('go_to_dashboard') ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- PAID PLANS SECTION (MONTHLY / YEARLY) -->
        <?php if (!empty($monthlyPlans) || !empty($yearlyPlans)): ?>
            <ul class="nav nav-pills justify-content-center mb-4" id="paidBillingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold px-4 fs-5 d-inline-flex align-items-center gap-2" id="monthly-plans-tab" data-bs-toggle="pill" data-bs-target="#monthly-plans-view" type="button" role="tab">
                        <i class="bi bi-calendar-event"></i> <?= __('monthly') ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold px-4 fs-5 d-inline-flex align-items-center gap-2" id="yearly-plans-tab" data-bs-toggle="pill" data-bs-target="#yearly-plans-view" type="button" role="tab">
                        <i class="bi bi-gift"></i> <?= __('yearly') ?> <span class="badge bg-success fs-6 ms-1"><?= __('save_money') ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="paidPlansContent">
                
                <!-- Monthly View -->
                <div class="tab-pane fade show active" id="monthly-plans-view" role="tabpanel">
                    <div class="row row-cols-1 row-cols-md-2 g-4 justify-content-center">
                        <?php if (empty($monthlyPlans)): ?>
                            <div class="col-12 text-center text-muted py-4"><?= __('no_monthly_plans') ?></div>
                        <?php else: ?>
                            <?php foreach ($monthlyPlans as $plan): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm border-<?= !empty($plan['is_popular']) ? 'primary' : 'light' ?>">
                                        <div class="position-absolute top-0 end-0 mt-3 me-3 d-flex gap-1">
                                            <?php if (!empty($plan['is_default'])): ?>
                                                <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-star-fill"></i> <?= __('default_badge') ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($plan['is_popular'])): ?>
                                                <span class="badge bg-primary"><?= __('most_popular_badge') ?></span>
                                            <?php endif; ?>
                                            <?php if ($hasAdsConfigured && $adsFreeOnly === '1'): ?>
                                                <span class="badge bg-success d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-shield-check"></i> <?= __('ads_free_badge') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="card-body p-4 d-flex flex-column text-center">
                                            <h4 class="card-title fw-bold mt-2"><?= htmlspecialchars($plan['name']) ?></h4>
                                            <h2 class="display-5 fw-bold my-3 text-primary">
                                                €<?= number_format((float)$plan['price'], 2) ?>
                                                <span class="fs-6 text-muted fw-normal">/ <?= htmlspecialchars($plan['billing_cycle']) ?></span>
                                            </h2>

                                            <ul class="list-unstyled text-start mb-4 flex-grow-1">
                                                <li class="mb-2 d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                    <span><?= str_replace('{count}', (int)$plan['max_accounts'], __('up_to_social_accounts')) ?></span>
                                                </li>
                                                <li class="mb-2 d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                    <span><?= str_replace(['{count}', '{period}'], [(int)$plan['max_posts'], htmlspecialchars($plan['post_period'])], __('posts_per')) ?></span>
                                                </li>
                                                <li class="mb-2 d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                    <span><?= str_replace('{count}', (int)$plan['ai_credits_month'], __('ai_credits_limit')) ?></span>
                                                </li>
                                                <li class="mb-2 border-top pt-2 mt-2 text-info d-flex align-items-center">
                                                    <i class="bi bi-rss text-info me-2"></i>
                                                    <span><?= str_replace('{count}', (int)($plan['max_rss_feeds'] ?? 0), __('rss_feeds_limit')) ?></span>
                                                </li>
                                                <li class="mb-2 text-info d-flex align-items-center">
                                                    <i class="bi bi-robot text-info me-2"></i>
                                                    <span><?= str_replace(['{count}', '{period}'], [(int)($plan['max_rss_posts'] ?? 0), htmlspecialchars($plan['post_period'])], __('rss_posts_limit')) ?></span>
                                                </li>
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
                                                <li class="mb-2 border-top pt-2 mt-2 text-warning d-flex align-items-center">
                                                    <i class="bi bi-folder2 text-warning me-2"></i>
                                                    <span><?= str_replace('{size}', htmlspecialchars($plan['max_storage_size'] ?? '1GB'), __('storage_label')) ?></span>
                                                </li>
                                                <li class="mb-2 text-warning d-flex align-items-center">
                                                    <i class="bi bi-cloud-arrow-up text-warning me-2"></i>
                                                    <span><?= str_replace('{size}', htmlspecialchars($plan['max_upload_size'] ?? '50MB'), __('max_upload_limit')) ?></span>
                                                </li>
                                                <?php if ($hasAdsConfigured): ?>
                                                    <?php if ($adsFreeOnly === '1'): ?>
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
                                                <?php if (!isset($isLoggedIn) || !$isLoggedIn): ?>
                                                    <a href="/login" class="btn btn-lg w-100 btn-outline-primary fw-bold"><?= __('login_to_subscribe') ?></a>
                                                <?php elseif (empty($plan['stripe_price_id'])): ?>
                                                    <a href="/dashboard" class="btn btn-lg w-100 btn-secondary fw-bold"><?= __('go_to_dashboard') ?></a>
                                                <?php else: ?>
                                                    <form action="/billing/checkout" method="POST">
                                                        <input type="hidden" name="price_id" value="<?= htmlspecialchars($plan['stripe_price_id']) ?>">
                                                        <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                                                        <button type="submit" class="btn btn-lg w-100 btn-primary fw-bold"><?= __('subscribe_now') ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Yearly View -->
                <div class="tab-pane fade" id="yearly-plans-view" role="tabpanel">
                    <div class="row row-cols-1 row-cols-md-2 g-4 justify-content-center">
                        <?php if (empty($yearlyPlans)): ?>
                            <div class="col-12 text-center text-muted py-4"><?= __('no_yearly_plans') ?></div>
                        <?php else: ?>
                            <?php foreach ($yearlyPlans as $plan): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm border-<?= !empty($plan['is_popular']) ? 'primary' : 'light' ?>">
                                        <div class="position-absolute top-0 end-0 mt-3 me-3 d-flex gap-1">
                                            <?php if (!empty($plan['is_default'])): ?>
                                                <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-star-fill"></i> <?= __('default_badge') ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($plan['is_popular'])): ?>
                                                <span class="badge bg-primary"><?= __('most_popular_badge') ?></span>
                                            <?php endif; ?>
                                            <?php if ($hasAdsConfigured && $adsFreeOnly === '1'): ?>
                                                <span class="badge bg-success d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-shield-check"></i> <?= __('ads_free_badge') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="card-body p-4 d-flex flex-column text-center">
                                            <h4 class="card-title fw-bold mt-2"><?= htmlspecialchars($plan['name']) ?></h4>
                                            <h2 class="display-5 fw-bold my-3 text-primary">
                                                €<?= number_format((float)$plan['price'], 2) ?>
                                                <span class="fs-6 text-muted fw-normal">/ <?= htmlspecialchars($plan['billing_cycle']) ?></span>
                                            </h2>

                                            <ul class="list-unstyled text-start mb-4 flex-grow-1">
                                                <li class="mb-2 d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                    <span><?= str_replace('{count}', (int)$plan['max_accounts'], __('up_to_social_accounts')) ?></span>
                                                </li>
                                                <li class="mb-2 d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                    <span><?= str_replace(['{count}', '{period}'], [(int)$plan['max_posts'], htmlspecialchars($plan['post_period'])], __('posts_per')) ?></span>
                                                </li>
                                                <li class="mb-2 d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                    <span><?= str_replace('{count}', (int)$plan['ai_credits_month'], __('ai_credits_limit')) ?></span>
                                                </li>
                                                <li class="mb-2 border-top pt-2 mt-2 text-info d-flex align-items-center">
                                                    <i class="bi bi-rss text-info me-2"></i>
                                                    <span><?= str_replace('{count}', (int)($plan['max_rss_feeds'] ?? 0), __('rss_feeds_limit')) ?></span>
                                                </li>
                                                <li class="mb-2 text-info d-flex align-items-center">
                                                    <i class="bi bi-robot text-info me-2"></i>
                                                    <span><?= str_replace(['{count}', '{period}'], [(int)($plan['max_rss_posts'] ?? 0), htmlspecialchars($plan['post_period'])], __('rss_posts_limit')) ?></span>
                                                </li>
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
                                                <li class="mb-2 border-top pt-2 mt-2 text-warning d-flex align-items-center">
                                                    <i class="bi bi-folder2 text-warning me-2"></i>
                                                    <span><?= str_replace('{size}', htmlspecialchars($plan['max_storage_size'] ?? '1GB'), __('storage_label')) ?></span>
                                                </li>
                                                <li class="mb-2 text-warning d-flex align-items-center">
                                                    <i class="bi bi-cloud-arrow-up text-warning me-2"></i>
                                                    <span><?= str_replace('{size}', htmlspecialchars($plan['max_upload_size'] ?? '50MB'), __('max_upload_limit')) ?></span>
                                                </li>
                                                <?php if ($hasAdsConfigured): ?>
                                                    <?php if ($adsFreeOnly === '1'): ?>
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
                                                <?php if (!isset($isLoggedIn) || !$isLoggedIn): ?>
                                                    <a href="/login" class="btn btn-lg w-100 btn-outline-primary fw-bold"><?= __('login_to_subscribe') ?></a>
                                                <?php elseif (empty($plan['stripe_price_id'])): ?>
                                                    <a href="/dashboard" class="btn btn-lg w-100 btn-secondary fw-bold"><?= __('go_to_dashboard') ?></a>
                                                <?php else: ?>
                                                    <form action="/billing/checkout" method="POST">
                                                        <input type="hidden" name="price_id" value="<?= htmlspecialchars($plan['stripe_price_id']) ?>">
                                                        <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                                                        <button type="submit" class="btn btn-lg w-100 btn-primary fw-bold"><?= __('subscribe_now') ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Handle Language Dropdown Selection
    const langSelectors = document.querySelectorAll('.lang-selector');
    langSelectors.forEach(function(selector) {
        selector.addEventListener('click', function(e) {
            e.preventDefault();
            const selectedLang = this.getAttribute('data-lang');
            document.cookie = "lang=" + selectedLang + "; path=/; max-age=" + (60*60*24*365);
            window.location.reload();
        });
    });

    // 2. Browser Language Detection
    const hasLangCookie = document.cookie.split('; ').some(row => row.trim().startsWith('lang='));
    if (!hasLangCookie) {
        let browserLang = (navigator.language || navigator.userLanguage || 'en').split('-')[0].toLowerCase();
        const availableLangs = <?= json_encode($availableLanguages) ?>;
        const finalLang = availableLangs.includes(browserLang) ? browserLang : 'en';
        document.cookie = "lang=" + finalLang + "; path=/; max-age=" + (60*60*24*365);
        if (finalLang !== '<?= $currentLang ?>') {
            window.location.reload();
        }
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>