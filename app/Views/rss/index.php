<?php
// app/Views/rss/index.php
if (!isset($_SESSION['user_id'])) exit;

if (!function_exists('getProgressPercent')) {
    function getProgressPercent(int $used, int $max): int {
        if ($max <= 0) return 100;
        return (int)min(100, round(($used / $max) * 100));
    }
}

// Configured RSS Feeds limits
$currentFeedCount   = count($feeds ?? []);
$maxRssFeeds        = (int)($limits['max_rss_feeds'] ?? 0);
$isFeedLimitReached = ($maxRssFeeds > 0 && $currentFeedCount >= $maxRssFeeds);

// Dynamic RSS Posts limits & Period Label
$rssPostsUsed        = (int)($limits['rss_posts_used'] ?? 0);
$maxRssPosts         = (int)($limits['max_rss_posts'] ?? 0);
$rssPostsPercent     = getProgressPercent($rssPostsUsed, $maxRssPosts);

$postPeriodLabel     = ucfirst(htmlspecialchars($limits['post_period'] ?? 'monthly'));
$postPeriodLower     = strtolower($postPeriodLabel);
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<style>
    .stat-card { transition: transform 0.2s ease; border-radius: 12px; }
    .stat-card:hover { transform: translateY(-3px); }
    .progress { height: 8px; border-radius: 4px; }
</style>

<div class="d-flex main-wrapper bg-light">
    
    <?php 
    $activeMenu = 'rss'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-rss text-primary"></i> <?= __('rss_automations_title') ?>
            </h1>
            
            <div class="shadow-sm border rounded bg-white px-3 py-2 fs-6 fw-semibold d-inline-flex align-items-center gap-2">
                <span>
                    <i class="bi bi-tag-fill text-muted me-1"></i>
                    <strong><?= str_replace('{name}', htmlspecialchars($limits['plan_name'] ?? 'Free'), __('plan_badge_name')) ?></strong>
                </span>
                <span class="text-primary ms-2 border-start ps-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-rss"></i>
                    <strong><?= str_replace(['{current}', '{max}'], [$currentFeedCount, $maxRssFeeds], __('configured_feeds_ratio')) ?></strong>
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

        <!-- FULL WIDTH RSS POSTS STAT CARD -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card stat-card shadow-sm border-0 position-relative overflow-hidden bg-white">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-uppercase fw-bold text-muted mb-0 small">
                                <?= str_replace('{period}', $postPeriodLabel, __('posts_period_title')) ?>
                            </h6>
                            <div class="bg-info bg-opacity-10 text-info p-2 rounded d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="bi bi-robot fs-5"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="display-6 fw-bold mb-0"><?= $rssPostsUsed ?></h2>
                            <span class="text-muted ms-2">/ <?= $maxRssPosts ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= $rssPostsPercent ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= str_replace(['{percent}', '{period}'], [$rssPostsPercent, $postPeriodLower], __('rss_limit_reached_percent')) ?>
                        </small>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0dcaf0;"></div>
                </div>
            </div>
        </div>

        <?php if ($isFeedLimitReached): ?>
            <div class="alert alert-warning mb-4 border-warning shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <?= str_replace(['{current}', '{max}', '{plan}'], [$currentFeedCount, $maxRssFeeds, htmlspecialchars($limits['plan_name'] ?? 'Free')], __('rss_feed_limit_reached_alert')) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Collapsible Add New Feed Section -->
        <div class="accordion mb-4 shadow-sm" id="addFeedAccordion">
            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="headingAddFeed">
                    <button class="accordion-button <?= $isFeedLimitReached ? 'collapsed' : '' ?> fw-bold d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddFeed" aria-expanded="<?= $isFeedLimitReached ? 'false' : 'true' ?>" aria-controls="collapseAddFeed">
                        <i class="bi bi-plus-circle text-primary"></i> <?= __('add_new_rss_automation') ?>
                    </button>
                </h2>
                <div id="collapseAddFeed" class="accordion-collapse collapse <?= $isFeedLimitReached ? '' : 'show' ?>" aria-labelledby="headingAddFeed" data-bs-parent="#addFeedAccordion">
                    <div class="accordion-body bg-white p-4">
                        
                        <form action="/rss/add" method="POST" id="add-rss-form">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label for="feed_url" class="form-label fw-bold text-dark"><?= __('rss_feed_url') ?></label>
                                    <input type="url" name="feed_url" id="feed_url" class="form-control form-control-lg shadow-sm border-secondary-subtle" placeholder="https://example.com/feed" required <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="post_interval_hours" class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-clock"></i> <?= __('posting_interval') ?>
                                    </label>
                                    <select name="post_interval_hours" id="post_interval_hours" class="form-select form-select-lg shadow-sm border-secondary-subtle" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                        <?php for ($i = 1; $i <= 24; $i++): ?>
                                            <option value="<?= $i ?>">
                                                <?= $i === 1 ? __('hour_between_posts') : str_replace('{count}', $i, __('hours_between_posts')) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Advanced Feed Processing Rules -->
                            <div class="bg-light p-3 rounded border mb-4">
                                <h6 class="fw-bold text-secondary mb-3 d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-gear-wide-connected"></i> <?= __('automation_rules') ?>
                                </h6>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="only_new_items" id="only_new_items" value="1" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="only_new_items">
                                        <?= __('post_only_new_items_desc') ?>
                                    </label>
                                </div>

                                <div class="row align-items-center g-2">
                                    <div class="col-auto">
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="random_repost_enabled" id="random_repost_enabled" value="1" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                            <label class="form-check-label fw-semibold text-dark d-inline-flex align-items-center gap-1" for="random_repost_enabled">
                                                <i class="bi bi-shuffle"></i> <?= __('endless_random_repost') ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <select name="random_repost_limit" id="random_repost_limit" class="form-select form-select-sm border-secondary-subtle fw-bold" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                            <?php foreach ([10, 20, 30, 40, 50] as $num): ?>
                                                <option value="<?= $num ?>"><?= str_replace('{count}', $num, __('items_count')) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="border-top pt-4">
                                <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
                                    <label class="form-label fw-bold text-secondary small text-uppercase mb-0 d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-share"></i> <?= __('target_social_accounts') ?>
                                    </label>
                                    
                                    <?php if (!empty($groups)): ?>
                                        <div class="input-group input-group-sm w-auto shadow-sm">
                                            <span class="input-group-text bg-light fw-bold text-secondary d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-folder2"></i> <?= __('quick_selection') ?>
                                            </span>
                                            <select class="form-select border-secondary-subtle" id="group-select" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                                <option value=""><?= __('choose_group') ?></option>
                                                <?php foreach ($groups as $group): ?>
                                                    <?php 
                                                        $jsonValue = isset($group['account_ids']) ? json_encode($group['account_ids']) : ($group['account_ids_json'] ?? '[]'); 
                                                    ?>
                                                    <option value="<?= htmlspecialchars($jsonValue, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($group['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline-danger" type="button" id="clear-accounts-btn" title="<?= __('clear_selections_title') ?>" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($connectedAccounts)): ?>
                                    <div class="table-responsive bg-light border rounded p-2 shadow-sm mb-3">
                                        <table class="table table-hover align-middle mb-0 dt-table w-100" id="rss-accounts-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40px;" class="text-center">✓</th>
                                                    <th><?= __('account_details') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($connectedAccounts as $account): ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input m-0 border-secondary" type="checkbox" name="target_accounts[]" value="<?= (int)$account['id'] ?>" id="acc_<?= (int)$account['id'] ?>" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                                        </td>
                                                        <td>
                                                            <label class="form-check-label w-100 cursor-pointer d-flex flex-column" for="acc_<?= (int)$account['id'] ?>">
                                                                <span class="fw-bold text-dark"><?= htmlspecialchars($account['platform_name'] ?? 'Account') ?></span>
                                                                <span class="badge bg-secondary w-auto align-self-start mt-1" style="font-size: 0.7em;">
                                                                    <?= htmlspecialchars(ucfirst($account['plugin_id'] ?? 'Social')) ?>
                                                                </span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($mailingLists)): ?>
                                    <hr class="text-muted opacity-25">
                                    <div class="mt-3 mb-4">
                                        <label class="form-label fw-semibold text-secondary d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-envelope-paper"></i> <?= __('mailing_lists') ?>
                                        </label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($mailingLists as $list): ?>
                                                <div class="form-check border rounded px-3 py-2 bg-white shadow-sm">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="target_mailing_lists[]" 
                                                           value="<?= $list['id'] ?>" 
                                                           id="rss_ml_<?= $list['id'] ?>"
                                                           <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                                    <label class="form-check-label d-flex align-items-center gap-2" for="rss_ml_<?= $list['id'] ?>">
                                                        <span class="badge bg-warning text-dark">Mailing List</span>
                                                        <?= htmlspecialchars($list['name']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow d-inline-flex align-items-center justify-content-center gap-2" <?= $isFeedLimitReached ? 'disabled' : '' ?>>
                                    <i class="bi bi-floppy"></i> <?= __('save_feed_automation') ?>
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

        <!-- Configured Feeds Table -->
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 fw-bold d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-secondary mb-0 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-list-task text-primary"></i> <?= __('configured_rss_feeds') ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($feeds)): ?>
                    <div class="p-5 text-center text-muted bg-light rounded border border-dashed">
                        <h4><?= __('no_rss_feeds_yet') ?></h4>
                        <p class="mb-0"><?= __('no_rss_feeds_yet_sub') ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 dt-table w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;"><?= __('feed_url_header') ?></th>
                                    <th style="width: 20%;"><?= __('interval_rules_header') ?></th>
                                    <th style="width: 35%;"><?= __('target_accounts_header') ?></th>
                                    <th style="width: 10%;"><?= __('last_checked_header') ?></th>
                                    <th class="text-end" style="width: 10%;"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feeds as $feed): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width: 200px;">
                                            <a href="<?= htmlspecialchars($feed['feed_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-bold text-primary d-inline-flex align-items-center gap-1" title="<?= htmlspecialchars($feed['feed_url']) ?>">
                                                <i class="bi bi-box-arrow-up-right small"></i> <?= htmlspecialchars($feed['feed_url']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle fw-bold mb-1 d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-clock"></i> <?= str_replace('{count}', (int)($feed['post_interval_hours'] ?? 1), __('every_hours_badge')) ?>
                                            </span>
                                            <div>
                                                <?php if (!empty($feed['only_new_items'])): ?>
                                                    <span class="badge bg-success text-white d-inline-flex align-items-center gap-1" title="Only new items published after setup">
                                                        <i class="bi bi-asterisk"></i> <?= __('only_new_badge') ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($feed['random_repost_enabled'])): ?>
                                                    <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1" title="Random repost active">
                                                        <i class="bi bi-shuffle"></i> <?= str_replace('{count}', (int)($feed['random_repost_limit'] ?? 10), __('repost_badge')) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php 
                                                $targets = json_decode($feed['target_accounts'], true) ?? [];
                                                if (!empty($targets)):
                                                    foreach ($targets as $targetId):
                                                        if (is_string($targetId) && str_starts_with($targetId, 'ml_')):
                                                            $mlId = (int)substr($targetId, 3);
                                                            $mlName = "Mailing List #" . $mlId;
                                                            if (!empty($mailingLists)) {
                                                                foreach ($mailingLists as $ml) {
                                                                    if ((int)$ml['id'] === $mlId) {
                                                                        $mlName = $ml['name'];
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                ?>
                                                            <span class="badge bg-warning text-dark border d-flex align-items-center shadow-sm py-1">
                                                                <i class="bi bi-envelope-paper me-1"></i>
                                                                <span class="text-truncate d-inline-block" style="max-width: 120px;" title="<?= htmlspecialchars($mlName) ?>">
                                                                    <?= htmlspecialchars($mlName) ?>
                                                                </span>
                                                            </span>
                                                <?php 
                                                        else:
                                                            $accName = "ID: " . $targetId;
                                                            $accPlugin = "";
                                                            foreach ($connectedAccounts as $acc) {
                                                                if ($acc['id'] == $targetId) {
                                                                    $accName = $acc['platform_name'] ?? 'Account';
                                                                    $accPlugin = $acc['plugin_id'] ?? '';
                                                                    break;
                                                                }
                                                            }
                                                ?>
                                                            <span class="badge bg-light text-dark border d-flex align-items-center shadow-sm py-1">
                                                                <span class="text-truncate d-inline-block" style="max-width: 100px;" title="<?= htmlspecialchars($accName) ?>">
                                                                    <?= htmlspecialchars($accName) ?>
                                                                </span>
                                                                <?php if ($accPlugin): ?>
                                                                    <span class="badge bg-secondary ms-1" style="font-size: 0.65em;"><?= htmlspecialchars(ucfirst($accPlugin)) ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                <?php 
                                                        endif;
                                                    endforeach;
                                                else: 
                                                ?>
                                                    <span class="text-muted small"><?= __('no_accounts') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted fw-semibold">
                                                <?= !empty($feed['last_checked']) ? htmlspecialchars($feed['last_checked']) : __('never') ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <button class="btn btn-sm btn-outline-primary shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editFeedModal_<?= $feed['id'] ?>">
                                                    <i class="bi bi-pencil"></i> <?= __('edit') ?>
                                                </button>
                                                <form action="/rss/delete" method="POST" onsubmit="return confirm('<?= addslashes(__('confirm_delete_feed')) ?>');" class="m-0">
                                                    <input type="hidden" name="feed_id" value="<?= htmlspecialchars($feed['id']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-trash3"></i> <?= __('delete') ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- EDIT MODAL FOR FEED #<?= $feed['id'] ?> -->
                                    <div class="modal fade" id="editFeedModal_<?= $feed['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content text-start">
                                                <form action="/rss/update" method="POST">
                                                    <input type="hidden" name="feed_id" value="<?= (int)$feed['id'] ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                                                            <i class="bi bi-pencil-square text-primary"></i> <?= __('edit_rss_feed_automation') ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    
                                                    <div class="modal-body">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-bold text-dark"><?= __('rss_feed_url') ?></label>
                                                                <input type="url" name="feed_url" class="form-control" value="<?= htmlspecialchars($feed['feed_url']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                                                    <i class="bi bi-clock"></i> <?= __('posting_interval') ?>
                                                                </label>
                                                                <select name="post_interval_hours" class="form-select">
                                                                    <?php for ($i = 1; $i <= 24; $i++): ?>
                                                                        <option value="<?= $i ?>" <?= ((int)($feed['post_interval_hours'] ?? 1) === $i) ? 'selected' : '' ?>>
                                                                            <?= $i === 1 ? __('hour_between_posts') : str_replace('{count}', $i, __('hours_between_posts')) ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="bg-light p-3 rounded border mb-3">
                                                            <h6 class="fw-bold text-secondary mb-3 d-inline-flex align-items-center gap-1">
                                                                <i class="bi bi-gear-wide-connected"></i> <?= __('automation_rules') ?>
                                                            </h6>
                                                            
                                                            <div class="form-check form-switch mb-3">
                                                                <input class="form-check-input" type="checkbox" name="only_new_items" id="edit_only_new_<?= $feed['id'] ?>" value="1" <?= !empty($feed['only_new_items']) ? 'checked' : '' ?>>
                                                                <label class="form-check-label fw-semibold text-dark" for="edit_only_new_<?= $feed['id'] ?>">
                                                                    <?= __('post_only_new_items_short') ?>
                                                                </label>
                                                            </div>

                                                            <div class="row align-items-center g-2">
                                                                <div class="col-auto">
                                                                    <div class="form-check form-switch m-0">
                                                                        <input class="form-check-input" type="checkbox" name="random_repost_enabled" id="edit_random_repost_<?= $feed['id'] ?>" value="1" <?= !empty($feed['random_repost_enabled']) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label fw-semibold text-dark d-inline-flex align-items-center gap-1" for="edit_random_repost_<?= $feed['id'] ?>">
                                                                            <i class="bi bi-shuffle"></i> <?= __('endless_random_repost') ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-auto">
                                                                    <select name="random_repost_limit" class="form-select form-select-sm fw-bold">
                                                                        <?php foreach ([10, 20, 30, 40, 50] as $num): ?>
                                                                            <option value="<?= $num ?>" <?= ((int)($feed['random_repost_limit'] ?? 10) === $num) ? 'selected' : '' ?>><?= str_replace('{count}', $num, __('items_count')) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Target Social Accounts (Edit Modal) -->
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                                                <i class="bi bi-share"></i> <?= __('target_social_accounts') ?>
                                                            </label>
                                                            <div class="border rounded p-2 bg-light" style="max-height: 180px; overflow-y: auto;">
                                                                <?php 
                                                                $selectedTargets = json_decode($feed['target_accounts'], true) ?? [];
                                                                foreach ($connectedAccounts as $account): 
                                                                    $isChecked = in_array((int)$account['id'], $selectedTargets, true);
                                                                ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="target_accounts[]" value="<?= (int)$account['id'] ?>" id="edit_acc_<?= $feed['id'] ?>_<?= $account['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="edit_acc_<?= $feed['id'] ?>_<?= $account['id'] ?>">
                                                                            <strong><?= htmlspecialchars($account['platform_name']) ?></strong> (<?= htmlspecialchars(ucfirst($account['plugin_id'])) ?>)
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>

                                                        <!-- Target Mailing Lists (Edit Modal) -->
                                                        <?php if (!empty($mailingLists)): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                                                    <i class="bi bi-envelope-paper"></i> <?= __('mailing_lists') ?>
                                                                </label>
                                                                <div class="border rounded p-2 bg-light d-flex flex-wrap gap-2" style="max-height: 150px; overflow-y: auto;">
                                                                    <?php 
                                                                    foreach ($mailingLists as $list): 
                                                                        $isMlChecked = in_array('ml_' . $list['id'], $selectedTargets, true);
                                                                    ?>
                                                                        <div class="form-check border rounded px-2 py-1 bg-white shadow-sm">
                                                                            <input class="form-check-input" type="checkbox" name="target_mailing_lists[]" value="<?= (int)$list['id'] ?>" id="edit_ml_<?= $feed['id'] ?>_<?= $list['id'] ?>" <?= $isMlChecked ? 'checked' : '' ?>>
                                                                            <label class="form-check-label" for="edit_ml_<?= $feed['id'] ?>_<?= $list['id'] ?>">
                                                                                <span class="badge bg-warning text-dark" style="font-size: 0.65em;">Mailing List</span>
                                                                                <strong><?= htmlspecialchars($list['name']) ?></strong>
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                                                        <button type="submit" class="btn btn-primary fw-bold"><?= __('save_changes') ?></button>
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
    
    const storageKey = 'rss_selected_accounts';
    const savedAccounts = JSON.parse(localStorage.getItem(storageKey)) || [];

    function updateSavedAccounts() {
        const checkedBoxes = document.querySelectorAll('#add-rss-form input[name="target_accounts[]"]:checked');
        const checkedIds = Array.from(checkedBoxes).map(cb => cb.value);
        localStorage.setItem(storageKey, JSON.stringify(checkedIds));
    }

    document.querySelectorAll('#add-rss-form input[name="target_accounts[]"]').forEach(checkbox => {
        if (savedAccounts.includes(checkbox.value)) {
            checkbox.checked = true;
        }
        checkbox.addEventListener('change', updateSavedAccounts);
    });

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
                console.error("Failed to parse accounts JSON:", e);
                return;
            }

            document.querySelectorAll('#add-rss-form input[name="target_accounts[]"]').forEach(cb => {
                if (accountIds.includes(String(cb.value))) {
                    cb.checked = true;
                }
            });

            try {
                const dt = $('#rss-accounts-table').DataTable();
                dt.$('input[name="target_accounts[]"]').each(function() {
                    if (accountIds.includes(String(this.value))) {
                        $(this).prop('checked', true);
                    }
                });
            } catch (error) {}
            
            updateSavedAccounts();
            this.value = ""; 
        });
    }

    const clearBtn = document.getElementById('clear-accounts-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            document.querySelectorAll('#add-rss-form input[name="target_accounts[]"]').forEach(cb => {
                cb.checked = false;
            });

            try {
                $('#rss-accounts-table').DataTable().$('input[name="target_accounts[]"]').prop('checked', false);
            } catch(e) {}

            localStorage.removeItem(storageKey);
        });
    }

    $('#add-rss-form').on('submit', function(e) {
        let dt;
        try {
            dt = $('#rss-accounts-table').DataTable();
        } catch(err) {
            return;
        }

        const form = $(this);
        const checkedInputs = dt.$('input[name="target_accounts[]"]:checked');
        
        $('.dt-hidden-inputs').remove();
        
        checkedInputs.each(function() {
            if (!$.contains(document, this)) {
                form.append(
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'target_accounts[]')
                        .val($(this).val())
                        .addClass('dt-hidden-inputs')
                );
            }
        });
        
        localStorage.removeItem(storageKey);
    });

});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
