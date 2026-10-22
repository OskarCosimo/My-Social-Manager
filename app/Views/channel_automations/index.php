<?php
// app/Views/channel_automations/index.php
if (!isset($_SESSION['user_id'])) exit;

if (!function_exists('getProgressPercent')) {
    function getProgressPercent(int $used, int $max): int {
        if ($max <= 0) return 100;
        return (int)min(100, round(($used / $max) * 100));
    }
}

// Configured Channel Automations limits
$currentAutomationCount   = (int)($limits['current_channel_automations'] ?? count($automations ?? []));
$maxChannelAutomations    = (int)($limits['max_channel_automations'] ?? 5);
$isAutomationLimitReached = ($maxChannelAutomations > 0 && $currentAutomationCount >= $maxChannelAutomations);

// Dynamic Channel Posts limits & Period Label
$channelPostsUsed    = (int)($limits['channel_posts_used'] ?? 0);
$maxChannelPosts     = (int)($limits['max_channel_posts'] ?? 50);
$channelPostsPercent = getProgressPercent($channelPostsUsed, $maxChannelPosts);

$postPeriodLabel     = ucfirst(htmlspecialchars($limits['post_period'] ?? 'monthly'));
$postPeriodLower     = strtolower($postPeriodLabel);

// Filter connected accounts: only platforms that support reading source feeds (X.com available as target only)
$supportedSourcePlugins = [
    'youtube', 
    'facebook', 
    'instagram', 
    'linkedin_pages', 
    'tumblr', 
    'wordpress', 
    'twitch', 
    'medium',
    'myetv',
    'threads',
    'devto'
];

$sourceAccounts = array_filter($connectedAccounts ?? [], function($acc) use ($supportedSourcePlugins) {
    return in_array($acc['plugin_id'], $supportedSourcePlugins, true);
});

require_once __DIR__ . '/../partials/header.php';
?>

<style>
    .stat-card { transition: transform 0.2s ease; border-radius: 12px; }
    .stat-card:hover { transform: translateY(-3px); }
    .progress { height: 8px; border-radius: 4px; }
</style>

<div class="d-flex main-wrapper bg-light">
    
    <?php 
    $activeMenu = 'channel_automations'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-broadcast-pin text-primary"></i> <?= __('channel_automations_title') ?? 'Channel & Social Automations' ?>
            </h1>
            
            <div class="shadow-sm border rounded bg-white px-3 py-2 fs-6 fw-semibold d-inline-flex align-items-center gap-2">
                <span>
                    <i class="bi bi-tag-fill text-muted me-1"></i>
                    <strong><?= htmlspecialchars($limits['plan_name'] ?? 'Free') ?></strong>
                </span>
                <span class="text-primary ms-2 border-start ps-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-broadcast-pin"></i>
                    <strong><?= $currentAutomationCount ?> / <?= $maxChannelAutomations ?></strong>
                </span>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- FULL WIDTH CHANNEL AUTOMATIONS POSTS STAT CARD -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card stat-card shadow-sm border-0 position-relative overflow-hidden bg-white">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-uppercase fw-bold text-muted mb-0 small">
                                <?= str_replace('{period}', $postPeriodLabel, __('channel_automation_posts_title') ?? "{$postPeriodLabel} Channel Automation Posts") ?>
                            </h6>
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="bi bi-broadcast fs-5"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="display-6 fw-bold mb-0"><?= $channelPostsUsed ?></h2>
                            <span class="text-muted ms-2">/ <?= $maxChannelPosts ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $channelPostsPercent ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= str_replace(
                                ['{percent}', '{period}'], 
                                [$channelPostsPercent, $postPeriodLower], 
                                __('channel_posts_usage_stat') ?? "You used {$channelPostsPercent}% of your allowed channel posts for this {$postPeriodLower}."
                            ) ?>
                        </small>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0d6efd;"></div>
                </div>
            </div>
        </div>

        <?php if ($isAutomationLimitReached): ?>
            <div class="alert alert-warning mb-4 border-warning shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <?= str_replace(
                        '{max}', 
                        '<strong>' . $maxChannelAutomations . '</strong>', 
                        __('channel_limit_reached_message') ?? "You have reached your plan limit of {$maxChannelAutomations} channel automations. Please upgrade your plan to add more."
                    ) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Collapsible Add New Automation Card -->
        <div class="accordion mb-4 shadow-sm" id="addAutomationAccordion">
            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="headingAddAutomation">
                    <button class="accordion-button <?= $isAutomationLimitReached ? 'collapsed' : '' ?> fw-bold d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddAutomation" aria-expanded="<?= $isAutomationLimitReached ? 'false' : 'true' ?>" aria-controls="collapseAddAutomation">
                        <i class="bi bi-plus-circle text-primary"></i> <?= __('add_new_channel_automation') ?? 'Add New Channel Automation' ?>
                    </button>
                </h2>
                <div id="collapseAddAutomation" class="accordion-collapse collapse <?= $isAutomationLimitReached ? '' : 'show' ?>" aria-labelledby="headingAddAutomation" data-bs-parent="#addAutomationAccordion">
                    <div class="accordion-body bg-white p-4">
                        
                        <form action="/channel-automations/add" method="POST" id="add-automation-form">
                            
                            <!-- Source Selection -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold text-dark"><?= __('source_type') ?? 'Source Type' ?></label>
                                    <select name="source_type" id="source_type_select" class="form-select form-select-lg shadow-sm border-secondary-subtle" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                        <option value="connected_account"><?= __('connected_account_extended') ?? 'Connected Account (FB, IG, YouTube, LinkedIn, Tumblr, WP, Twitch, Medium, MYETV, Threads, Dev.to)' ?></option>
                                        <option value="youtube_url"><?= __('public_youtube_channel') ?? 'Public YouTube Channel (URL or Handle)' ?></option>
                                    </select>
                                </div>

                                <!-- Option A: Connected Account Dropdown -->
                                <div class="col-md-7" id="source_account_wrapper">
                                    <label class="form-label fw-bold text-dark"><?= __('select_source_account') ?? 'Select Source Account to Monitor' ?></label>
                                    <select name="source_account_id" id="source_account_id" class="form-select form-select-lg shadow-sm border-secondary-subtle" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                        <?php if (empty($sourceAccounts)): ?>
                                            <option value=""><?= __('no_accounts_available') ?? 'No compatible accounts connected' ?></option>
                                        <?php else: ?>
                                            <?php foreach ($sourceAccounts as $acc): ?>
                                                <option value="<?= (int)$acc['id'] ?>">
                                                    <?= htmlspecialchars($acc['platform_name']) ?> (<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $acc['plugin_id']))) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted"><?= __('only_source_platforms_hint') ?? 'Only platforms that allow automatic reading of new posts are shown.' ?></small>
                                </div>

                                <!-- Option B: YouTube URL Input -->
                                <div class="col-md-7 d-none" id="youtube_url_wrapper">
                                    <label class="form-label fw-bold text-dark"><?= __('youtube_channel_url') ?? 'YouTube Channel URL or @Handle' ?></label>
                                    <input type="text" name="youtube_url" id="youtube_url" class="form-control form-control-lg shadow-sm border-secondary-subtle" placeholder="https://www.youtube.com/@ChannelName or Channel ID" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                    <small class="text-muted"><?= __('youtube_url_hint') ?? 'Accepts channel URLs, custom handles, or channel IDs.' ?></small>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label for="post_interval_hours" class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-clock"></i> <?= __('check_interval') ?? 'Check Frequency' ?>
                                    </label>
                                    <select name="post_interval_hours" id="post_interval_hours" class="form-select shadow-sm border-secondary-subtle" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                        <?php for ($i = 1; $i <= 24; $i++): ?>
                                            <option value="<?= $i ?>">
                                                <?= $i === 1 ? (__('every_hour') ?? 'Every hour') : str_replace('{count}', $i, __('every_x_hours') ?? "Every {$i} hours") ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Rules Box -->
                            <div class="bg-light p-3 rounded border mb-4">
                                <h6 class="fw-bold text-secondary mb-3 d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-gear-wide-connected"></i> <?= __('automation_rules') ?? 'Automation Rules' ?>
                                </h6>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="only_new_items" id="only_new_items" value="1" checked <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="only_new_items">
                                        <?= __('post_only_new_items_desc') ?>
                                    </label>
                                </div>

                                <div class="row align-items-center g-2">
                                    <div class="col-auto">
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="random_repost_enabled" id="random_repost_enabled" value="1" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                            <label class="form-check-label fw-semibold text-dark d-inline-flex align-items-center gap-1" for="random_repost_enabled">
                                                <i class="bi bi-shuffle"></i> <?= __('endless_random_repost') ?? 'Random Repost from recent items' ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <select name="random_repost_limit" id="random_repost_limit" class="form-select form-select-sm border-secondary-subtle fw-bold" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                            <?php foreach ([10, 20, 30, 40, 50] as $num): ?>
                                                <option value="<?= $num ?>"><?= str_replace('{count}', $num, __('pool_of_x_items') ?? "Pool of {$num} items") ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Target Social Accounts -->
                            <div class="border-top pt-4">
                                <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
                                    <label class="form-label fw-bold text-secondary small text-uppercase mb-0 d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-share"></i> <?= __('target_social_accounts') ?? 'Target Social Accounts' ?>
                                    </label>
                                    
                                    <?php if (!empty($groups)): ?>
                                        <div class="input-group input-group-sm w-auto shadow-sm">
                                            <span class="input-group-text bg-light fw-bold text-secondary">
                                                <i class="bi bi-folder2 me-1"></i> <?= __('quick_group') ?? 'Quick Group' ?>
                                            </span>
                                            <select class="form-select border-secondary-subtle" id="group-select" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                                <option value=""><?= __('select_group') ?? 'Select Group' ?></option>
                                                <?php foreach ($groups as $group): ?>
                                                    <?php 
                                                        $jsonValue = isset($group['account_ids']) ? json_encode($group['account_ids']) : ($group['account_ids_json'] ?? '[]'); 
                                                    ?>
                                                    <option value="<?= htmlspecialchars($jsonValue, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($group['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline-danger" type="button" id="clear-accounts-btn" title="<?= __('clear_selections') ?? 'Clear Selections' ?>" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($connectedAccounts)): ?>
                                    <div class="table-responsive bg-light border rounded p-2 shadow-sm mb-4">
                                        <table class="table table-hover align-middle mb-0 dt-table w-100" id="automations-accounts-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40px;" class="text-center">✓</th>
                                                    <th><?= __('account_details') ?? 'Account Details' ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($connectedAccounts as $account): ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input m-0 border-secondary" type="checkbox" name="target_accounts[]" value="<?= (int)$account['id'] ?>" id="acc_<?= (int)$account['id'] ?>" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                                        </td>
                                                        <td>
                                                            <label class="form-check-label w-100 cursor-pointer d-flex flex-column" for="acc_<?= (int)$account['id'] ?>">
                                                                <span class="fw-bold text-dark"><?= htmlspecialchars($account['platform_name'] ?? 'Account') ?></span>
                                                                <span class="badge bg-secondary w-auto align-self-start mt-1" style="font-size: 0.7em;">
                                                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $account['plugin_id'] ?? 'Social'))) ?>
                                                                </span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow d-inline-flex align-items-center justify-content-center gap-2" <?= $isAutomationLimitReached ? 'disabled' : '' ?>>
                                    <i class="bi bi-floppy"></i> <?= __('save_channel_automation') ?? 'Save Automation Rule' ?>
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>

        <!-- Configured Automations Table -->
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 fw-bold d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-secondary mb-0 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-list-task text-primary"></i> <?= __('active_automations') ?? 'Configured Automations' ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($automations)): ?>
                    <div class="p-5 text-center text-muted bg-light rounded border border-dashed">
                        <h4><?= __('no_channel_automations_yet') ?? 'No channel automations configured yet.' ?></h4>
                        <p class="mb-0"><?= __('no_channel_automations_yet_sub') ?? 'Connect a YouTube channel or social page above to automatically broadcast new content.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 dt-table w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;"><?= __('monitored_source_header') ?? 'Monitored Source' ?></th>
                                    <th style="width: 20%;"><?= __('interval_rules_header') ?? 'Interval & Rules' ?></th>
                                    <th style="width: 35%;"><?= __('target_accounts_header') ?? 'Target Accounts' ?></th>
                                    <th style="width: 10%;"><?= __('last_checked_header') ?? 'Last Checked' ?></th>
                                    <th class="text-end" style="width: 10%;"><?= __('actions') ?? 'Actions' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($automations as $auto): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark d-block">
                                                <?= htmlspecialchars($auto['source_title']) ?>
                                            </span>
                                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.7em;">
                                                <?= htmlspecialchars($auto['source_type'] === 'youtube_url' ? (__('youtube_public') ?? 'YouTube Public') : str_replace('_', ' ', $auto['source_plugin_id'] ?? 'Social')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle fw-bold mb-1 d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-clock"></i> <?= str_replace('{count}', (int)$auto['post_interval_hours'], __('every_hours_badge') ?? "Every " . (int)$auto['post_interval_hours'] . "h") ?>
                                            </span>
                                            <div>
                                                <?php if (!empty($auto['only_new_items'])): ?>
                                                    <span class="badge bg-success text-white"><?= __('only_new_badge') ?? 'Only New' ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($auto['random_repost_enabled'])): ?>
                                                    <span class="badge bg-warning text-dark"><?= __('repost') ?? 'Repost' ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php 
                                                $targets = json_decode($auto['target_accounts'], true) ?? [];
                                                if (!empty($targets)):
                                                    foreach ($targets as $targetId):
                                                        $accName = "ID: " . $targetId;
                                                        $accPlugin = "";
                                                        foreach ($connectedAccounts as $acc) {
                                                            if ($acc['id'] == $targetId) {
                                                                $accName = $acc['platform_name'];
                                                                $accPlugin = $acc['plugin_id'];
                                                                break;
                                                            }
                                                        }
                                                ?>
                                                    <span class="badge bg-light text-dark border d-flex align-items-center shadow-sm py-1">
                                                        <span class="text-truncate d-inline-block" style="max-width: 100px;">
                                                            <?= htmlspecialchars($accName) ?>
                                                        </span>
                                                        <?php if ($accPlugin): ?>
                                                            <span class="badge bg-secondary ms-1" style="font-size: 0.65em;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $accPlugin))) ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; else: ?>
                                                    <span class="text-muted small"><?= __('no_target_accounts') ?? 'No target accounts' ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted fw-semibold">
                                                <?= !empty($auto['last_checked']) ? htmlspecialchars($auto['last_checked']) : (__('never') ?? 'Never') ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <!-- Edit Button opening Modal -->
                                                <button type="button" class="btn btn-sm btn-outline-primary shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editAutomationModal_<?= (int)$auto['id'] ?>" title="<?= __('edit') ?? 'Edit' ?>">
                                                    <i class="bi bi-pencil"></i> <?= __('edit') ?? 'Edit' ?>
                                                </button>

                                                <!-- Delete Button -->
                                                <form action="/channel-automations/delete" method="POST" onsubmit="return confirm('<?= addslashes(__('confirm_delete_channel_automation') ?? 'Delete this automation rule?') ?>');" class="m-0">
                                                    <input type="hidden" name="automation_id" value="<?= (int)$auto['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- EDIT MODAL FOR AUTOMATION #<?= $auto['id'] ?> -->
                                    <div class="modal fade" id="editAutomationModal_<?= (int)$auto['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content text-start">
                                                <form action="/channel-automations/update" method="POST">
                                                    <input type="hidden" name="automation_id" value="<?= (int)$auto['id'] ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                                                            <i class="bi bi-pencil-square text-primary"></i> <?= __('edit') ?? 'Edit' ?>: <?= htmlspecialchars($auto['source_title']) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('close') ?? 'Close' ?>"></button>
                                                    </div>
                                                    
                                                    <div class="modal-body">
                                                        <!-- Monitored Source Information -->
                                                        <div class="alert alert-light border mb-3">
                                                            <small class="text-muted text-uppercase fw-bold d-block mb-1"><?= __('monitored_source_header') ?? 'Monitored Source' ?></small>
                                                            <strong class="text-dark fs-6"><?= htmlspecialchars($auto['source_title']) ?></strong>
                                                            <span class="badge bg-secondary ms-2 text-uppercase"><?= htmlspecialchars($auto['source_type'] === 'youtube_url' ? (__('youtube_public') ?? 'YouTube Public') : str_replace('_', ' ', $auto['source_plugin_id'] ?? 'Social')) ?></span>
                                                        </div>

                                                        <!-- Posting Interval -->
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                                                <i class="bi bi-clock"></i> <?= __('check_interval') ?? 'Check Frequency' ?>
                                                            </label>
                                                            <select name="post_interval_hours" class="form-select">
                                                                <?php for ($i = 1; $i <= 24; $i++): ?>
                                                                    <option value="<?= $i ?>" <?= ((int)($auto['post_interval_hours'] ?? 1) === $i) ? 'selected' : '' ?>>
                                                                        <?= $i === 1 ? (__('every_hour') ?? 'Every hour') : str_replace('{count}', $i, __('every_x_hours') ?? "Every {$i} hours") ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>

                                                        <!-- Automation Rules -->
                                                        <div class="bg-light p-3 rounded border mb-3">
                                                            <h6 class="fw-bold text-secondary mb-3 d-inline-flex align-items-center gap-1">
                                                                <i class="bi bi-gear-wide-connected"></i> <?= __('automation_rules') ?? 'Automation Rules' ?>
                                                            </h6>
                                                            
                                                            <div class="form-check form-switch mb-3">
                                                                <input class="form-check-input" type="checkbox" name="only_new_items" id="edit_only_new_<?= $auto['id'] ?>" value="1" <?= !empty($auto['only_new_items']) ? 'checked' : '' ?>>
                                                                <label class="form-check-label fw-semibold text-dark" for="edit_only_new_<?= $auto['id'] ?>">
                                                                    <?= __('post_only_new_items_desc') ?>
                                                                </label>
                                                            </div>

                                                            <div class="row align-items-center g-2">
                                                                <div class="col-auto">
                                                                    <div class="form-check form-switch m-0">
                                                                        <input class="form-check-input" type="checkbox" name="random_repost_enabled" id="edit_random_repost_<?= $auto['id'] ?>" value="1" <?= !empty($auto['random_repost_enabled']) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label fw-semibold text-dark d-inline-flex align-items-center gap-1" for="edit_random_repost_<?= $auto['id'] ?>">
                                                                            <i class="bi bi-shuffle"></i> <?= __('endless_random_repost') ?? 'Random Repost from recent items' ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-auto">
                                                                    <select name="random_repost_limit" class="form-select form-select-sm fw-bold">
                                                                        <?php foreach ([10, 20, 30, 40, 50] as $num): ?>
                                                                            <option value="<?= $num ?>" <?= ((int)($auto['random_repost_limit'] ?? 10) === $num) ? 'selected' : '' ?>>
                                                                                <?= str_replace('{count}', $num, __('pool_of_x_items') ?? "Pool of {$num} items") ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Target Accounts Selection (Anti-loop excludes source account) -->
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                                                <i class="bi bi-share"></i> <?= __('target_social_accounts') ?? 'Target Social Accounts' ?>
                                                            </label>
                                                            <div class="border rounded p-2 bg-light" style="max-height: 220px; overflow-y: auto;">
                                                                <?php 
                                                                $selectedTargets = json_decode($auto['target_accounts'], true) ?? [];
                                                                foreach ($connectedAccounts as $account): 
                                                                    // Anti-loop: don't show the source account in targets
                                                                    if (!empty($auto['source_account_id']) && (int)$account['id'] === (int)$auto['source_account_id']) continue;
                                                                    $isChecked = in_array((int)$account['id'], array_map('intval', $selectedTargets), true);
                                                                ?>
                                                                    <div class="form-check mb-1">
                                                                        <input class="form-check-input" type="checkbox" name="target_accounts[]" value="<?= (int)$account['id'] ?>" id="edit_acc_<?= $auto['id'] ?>_<?= $account['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                                        <label class="form-check-label cursor-pointer" for="edit_acc_<?= $auto['id'] ?>_<?= $account['id'] ?>">
                                                                            <strong><?= htmlspecialchars($account['platform_name']) ?></strong> (<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $account['plugin_id']))) ?>)
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>

                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?? 'Cancel' ?></button>
                                                        <button type="submit" class="btn btn-primary fw-bold"><?= __('save_changes') ?? 'Save Changes' ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sourceTypeSelect = document.getElementById('source_type_select');
    const sourceAccountWrapper = document.getElementById('source_account_wrapper');
    const youtubeUrlWrapper = document.getElementById('youtube_url_wrapper');

    if (sourceTypeSelect) {
        sourceTypeSelect.addEventListener('change', function() {
            if (this.value === 'youtube_url') {
                sourceAccountWrapper.classList.add('d-none');
                youtubeUrlWrapper.classList.remove('d-none');
            } else {
                sourceAccountWrapper.classList.remove('d-none');
                youtubeUrlWrapper.classList.add('d-none');
            }
        });
    }

    const groupSelect = document.getElementById('group-select');
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            if (!this.value) return; 
            
            let accountIds = [];
            try {
                let parsed = JSON.parse(this.value);
                if (typeof parsed === 'string') parsed = parsed.split(','); 
                accountIds = Array.isArray(parsed) ? parsed.map(id => String(id).trim()) : [String(parsed).trim()];
            } catch(e) {
                return;
            }

            document.querySelectorAll('#add-automation-form input[name="target_accounts[]"]').forEach(cb => {
                if (accountIds.includes(String(cb.value))) {
                    cb.checked = true;
                }
            });

            this.value = ""; 
        });
    }

    const clearBtn = document.getElementById('clear-accounts-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            document.querySelectorAll('#add-automation-form input[name="target_accounts[]"]').forEach(cb => {
                cb.checked = false;
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>