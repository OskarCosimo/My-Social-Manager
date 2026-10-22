<?php
// app/Views/accounts/index.php
if (!isset($_SESSION['user_id'])) exit;

/**
 * Helper function to calculate progress percentage
 */
function getProgressPercent(int $used, int $max): int {
    if ($max <= 0) return 100;
    return (int)min(100, round(($used / $max) * 100));
}

$planName        = htmlspecialchars($limits['plan_name'] ?? 'Free');
$currentAccounts = (int)($limits['current_accounts'] ?? count($connectedAccounts ?? []));
$maxAccounts     = (int)($limits['max_accounts'] ?? 1);
$accountsPercent = getProgressPercent($currentAccounts, $maxAccounts);
$isLimitReached  = ($maxAccounts > 0 && $currentAccounts >= $maxAccounts);

// Count expired or expiring accounts to show a top banner
$expiredCount = 0;
$expiringCount = 0;
foreach ($connectedAccounts ?? [] as $acc) {
    if (($acc['status_badge'] ?? '') === 'expired') $expiredCount++;
    if (($acc['status_badge'] ?? '') === 'expiring') $expiringCount++;
}

require_once __DIR__ . '/../partials/header.php'; 
?>

<!-- Main flex wrapper to align sidebar and content side-by-side -->
<div class="d-flex main-wrapper">
    
    <?php 
    $activeMenu = 'accounts'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <!-- Main content area taking up the remaining full width -->
    <main class="content-area w-100 p-4">
        
        <!-- Header & Plan Info Badge -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-share text-primary"></i> <?= __('social_accounts') ?>
            </h1>
            
            <div class="shadow-sm border rounded bg-white px-3 py-2 fs-6 fw-semibold d-inline-flex align-items-center gap-2">
                <span>
                    <i class="bi bi-tag-fill text-muted me-1"></i>
                    <strong><?= __('plan_badge_name', ['name' => $planName]) ?></strong>
                </span>
                <span class="text-primary ms-2 border-start ps-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-people-fill"></i>
                    <strong><?= __('social_accounts_counter', ['current' => $currentAccounts, 'max' => $maxAccounts]) ?></strong>
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

        <!-- Warning banner if any token is expired or expiring soon -->
        <?php if ($expiredCount > 0): ?>
            <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4 border-0" role="alert">
                <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                <div>
                    <?= __('banner_expired_action', ['count' => $expiredCount]) ?>
                </div>
            </div>
        <?php elseif ($expiringCount > 0): ?>
            <div class="alert alert-warning shadow-sm d-flex align-items-center mb-4 border-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                <div>
                    <?= __('banner_expiring_notice', ['count' => $expiringCount]) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Social Accounts Progress Bar Card -->
        <div class="card stat-card shadow-sm border-0 mb-4 position-relative overflow-hidden">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('connected_social_accounts') ?></h6>
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-end mb-2">
                    <h2 class="display-6 fw-bold mb-0"><?= $currentAccounts ?></h2>
                    <span class="text-muted ms-2">/ <?= $maxAccounts ?></span>
                </div>
                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $accountsPercent ?>%;"></div>
                </div>
                <small class="text-muted">
                    <?= __('plan_limit_reached_percent', ['percent' => $accountsPercent]) ?>
                </small>
            </div>
            <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0d6efd;"></div>
        </div>

        <?php if ($isLimitReached): ?>
            <div class="alert alert-warning mb-4 border-warning shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <?= __('banner_limit_reached', ['current' => $currentAccounts, 'max' => $maxAccounts, 'plan' => $planName]) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-md-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-hdd-network text-primary"></i> <?= __('active_connections') ?>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($connectedAccounts)): ?>
                            <p class="text-muted mb-0"><?= __('no_accounts_connected_yet') ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 dt-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?= __('account_name') ?></th>
                                            <th><?= __('plugin_details') ?></th>
                                            <th><?= __('token_status') ?></th>
                                            <th class="text-end"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($connectedAccounts as $account): ?>
                                            <tr id="account-row-<?= (int)$account['id'] ?>">
                                                <td>
                                                    <strong class="d-block"><?= htmlspecialchars($account['platform_name'] ?? '') ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary text-uppercase" title="<?= __('platform_user_id_label', ['id' => htmlspecialchars($account['platform_user_id'] ?? '')]) ?: ('ID: ' . htmlspecialchars($account['platform_user_id'] ?? '')) ?>">
                                                        <?= htmlspecialchars(str_replace('_', ' ', $account['plugin_id'] ?? '')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($account['status_badge'] === 'expired'): ?>
                                                        <span class="badge bg-danger status-badge d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-x-circle-fill"></i> <?= __('token_status_expired') ?>
                                                        </span>
                                                    <?php elseif ($account['status_badge'] === 'expiring'): ?>
                                                        <span class="badge bg-warning text-dark status-badge d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-exclamation-triangle-fill"></i> 
                                                            <?= __('token_status_expiring', ['days' => (int)$account['days_left']]) ?>
                                                        </span>
                                                    <?php elseif ($account['status_badge'] === 'valid'): ?>
                                                        <span class="badge bg-success status-badge d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-check-circle-fill"></i> 
                                                            <?= __('token_status_valid', ['days' => (int)$account['days_left']]) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border status-badge d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> <?= __('token_status_active') ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($account['token_expires_at'])): ?>
                                                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                                                            <?= __('token_expires_label', ['date' => date('M d, Y', strtotime($account['token_expires_at']))]) ?: ('Exp: ' . date('M d, Y', strtotime($account['token_expires_at']))) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <!-- Live test token button if supported by plugin -->
                                                        <?php if (!empty($account['supports_live_check'])): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-secondary btn-check-token d-inline-flex align-items-center gap-1" 
                                                                    data-account-id="<?= (int)$account['id'] ?>" 
                                                                    title="<?= __('check_token') ?>">
                                                                <i class="bi bi-arrow-repeat"></i> <?= __('check_token') ?>
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Reconnect button -->
                                                        <a href="/plugin/auth?id=<?= urlencode($account['plugin_id']) ?>" 
                                                           class="btn btn-sm d-inline-flex align-items-center gap-1 <?= in_array($account['status_badge'], ['expired', 'expiring']) ? 'btn-warning' : 'btn-outline-primary' ?>" 
                                                           title="<?= __('reconnect') ?>">
                                                            <i class="bi bi-link-45deg"></i> <?= __('reconnect') ?>
                                                        </a>

                                                        <!-- Disconnect button -->
                                                        <form action="/accounts/disconnect" method="POST" class="m-0" onsubmit="return confirm('<?= addslashes(__('confirm_disconnect_account')) ?>');">
                                                            <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= __('disconnect_account') ?: 'Disconnect' ?>">
                                                                <i class="bi bi-trash3"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 bg-light">
                    <div class="card-header bg-transparent border-0 fw-bold pb-0 d-inline-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle text-primary"></i> <?= __('connect_new') ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($availablePlugins)): ?>
                            <p class="text-muted small"><?= __('no_plugins_installed') ?></p>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <?php foreach ($availablePlugins as $plugin): ?>
                                    <a href="/plugin/auth?id=<?= urlencode($plugin->getId()) ?>" class="btn btn-primary shadow-sm <?= $isLimitReached ? 'disabled' : '' ?> d-inline-flex align-items-center justify-content-center gap-2">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                        <?= __('connect_plugin_btn', ['plugin' => htmlspecialchars($plugin->getName())]) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- AJAX handler for live token checking -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-check-token').forEach(button => {
        button.addEventListener('click', function () {
            const accountId = this.getAttribute('data-account-id');
            const row = document.getElementById('account-row-' + accountId);
            const badge = row.querySelector('.status-badge');
            const originalHtml = this.innerHTML;

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> <?= addslashes(__("checking_status")) ?>';

            const formData = new FormData();
            formData.append('account_id', accountId);

            fetch('/accounts/verify-token', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.valid) {
                    badge.className = 'badge bg-success status-badge d-inline-flex align-items-center gap-1';
                    badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> <?= addslashes(__("token_valid_msg")) ?>';
                } else {
                    badge.className = 'badge bg-danger status-badge d-inline-flex align-items-center gap-1';
                    badge.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + (data.message || '<?= addslashes(__("token_status_expired")) ?>');
                }
            })
            .catch(() => {
                alert('<?= addslashes(__("token_verify_failed")) ?>');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalHtml;
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>