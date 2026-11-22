<?php
// app/Views/accounts/index.php
if (!isset($_SESSION['user_id'])) exit;

function getProgressPercent(int $used, int $max): int {
    if ($max <= 0) return 100;
    return (int)min(100, round(($used / $max) * 100));
}

$planName        = htmlspecialchars($limits['plan_name'] ?? 'Free');
$currentAccounts = (int)($limits['current_accounts'] ?? count($connectedAccounts ?? []));
$maxAccounts     = (int)($limits['max_accounts'] ?? 1);
$accountsPercent = getProgressPercent($currentAccounts, $maxAccounts);
$isLimitReached  = ($maxAccounts > 0 && $currentAccounts >= $maxAccounts);

$expiredCount = 0;
$expiringCount = 0;
foreach ($connectedAccounts ?? [] as $acc) {
    if (($acc['status_badge'] ?? '') === 'expired') $expiredCount++;
    if (($acc['status_badge'] ?? '') === 'expiring') $expiringCount++;
}

require_once __DIR__ . '/../partials/header.php'; 
?>

<div class="d-flex main-wrapper">
    
    <?php 
    $activeMenu = 'accounts'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold text-body-emphasis d-inline-flex align-items-center gap-2">
                <i class="bi bi-share text-primary"></i> <?= __('social_accounts') ?>
            </h1>
            
            <div class="shadow-sm border rounded bg-body px-3 py-2 fs-6 fw-semibold d-inline-flex align-items-center gap-2">
                <span class="text-body-emphasis">
                    <i class="bi bi-tag-fill text-body-secondary me-1"></i>
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

        <div class="card stat-card shadow-sm border mb-4 position-relative overflow-hidden">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase fw-bold text-body-secondary mb-0"><?= __('connected_social_accounts') ?></h6>
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-end mb-2">
                    <h2 class="display-6 fw-bold mb-0 text-body-emphasis"><?= $currentAccounts ?></h2>
                    <span class="text-body-secondary ms-2">/ <?= $maxAccounts ?></span>
                </div>
                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $accountsPercent ?>%;"></div>
                </div>
                <small class="text-body-secondary">
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
                <div class="card shadow-sm border h-100">
                    <div class="card-header bg-transparent fw-bold d-inline-flex align-items-center gap-2 text-body-emphasis">
                        <i class="bi bi-hdd-network text-primary"></i> <?= __('active_connections') ?>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($connectedAccounts)): ?>
                            <p class="text-body-secondary mb-0"><?= __('no_accounts_connected_yet') ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 dt-table">
                                    <thead>
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
                                                    <strong class="d-block text-body-emphasis"><?= htmlspecialchars($account['platform_name'] ?? '') ?></strong>
                                                    <?php if (!empty($account['default_media'])): ?>
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle mt-1" style="font-size: 0.7rem;" title="<?= htmlspecialchars($account['default_media']) ?>">
                                                            <i class="bi <?= $account['is_video_network'] ? 'bi-camera-video' : 'bi-image' ?>"></i> Custom <?= $account['is_video_network'] ? 'Video' : 'Image' ?> Set
                                                        </span>
                                                    <?php endif; ?>
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
                                                        <span class="badge bg-body-secondary text-body-secondary border status-badge d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> <?= __('token_status_active') ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($account['token_expires_at'])): ?>
                                                        <small class="text-body-secondary d-block mt-1" style="font-size: 11px;">
                                                            <?= __('token_expires_label', ['date' => date('M d, Y', strtotime($account['token_expires_at']))]) ?: ('Exp: ' . date('M d, Y', strtotime($account['token_expires_at']))) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-1">
                                                        
                                                        <!-- Custom Fallback Media Button (for Video or Image mandated platforms) -->
                                                        <?php if (!empty($account['supports_media_fallback'])): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-info btn-account-media d-inline-flex align-items-center gap-1"
                                                                    data-account-id="<?= (int)$account['id'] ?>"
                                                                    data-account-name="<?= htmlspecialchars($account['platform_name'] ?? '') ?>"
                                                                    data-plugin-id="<?= htmlspecialchars($account['plugin_id'] ?? '') ?>"
                                                                    data-is-video="<?= $account['is_video_network'] ? '1' : '0' ?>"
                                                                    data-current-media="<?= htmlspecialchars($account['default_media'] ?? '') ?>"
                                                                    data-global-default="<?= htmlspecialchars($account['global_admin_default'] ?? '') ?>"
                                                                    title="Configure Default Fallback <?= $account['is_video_network'] ? 'Video' : 'Image' ?>">
                                                                <i class="bi <?= $account['is_video_network'] ? 'bi-camera-reels' : 'bi-image' ?>"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Live test token button -->
                                                        <?php if (!empty($account['supports_live_check'])): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-secondary btn-check-token d-inline-flex align-items-center gap-1" 
                                                                    data-account-id="<?= (int)$account['id'] ?>" 
                                                                    title="<?= __('check_token') ?>">
                                                                <i class="bi bi-arrow-repeat"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Reconnect button -->
                                                        <a href="/plugin/auth?id=<?= urlencode($account['plugin_id']) ?>" 
                                                           class="btn btn-sm d-inline-flex align-items-center gap-1 <?= in_array($account['status_badge'], ['expired', 'expiring']) ? 'btn-warning' : 'btn-outline-primary' ?>" 
                                                           title="<?= __('reconnect') ?>">
                                                            <i class="bi bi-link-45deg"></i>
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
                <div class="card shadow-sm border h-100 bg-body-tertiary">
                    <div class="card-header bg-transparent border-0 fw-bold pb-0 d-inline-flex align-items-center gap-2 text-body-emphasis">
                        <i class="bi bi-plus-circle text-primary"></i> <?= __('connect_new') ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($availablePlugins)): ?>
                            <p class="text-body-secondary small"><?= __('no_plugins_installed') ?></p>
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

<!-- Modal: Account Custom Fallback Media -->
<div class="modal fade" id="accountMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form action="/accounts/save-default-media" method="POST">
                <input type="hidden" name="account_id" id="modal_account_id">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-sliders text-warning"></i> Fallback Media Configuration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <p class="text-body-secondary small mb-3">
                        Configure a custom fallback media file for <strong id="modal_account_name" class="text-body-emphasis"></strong>.
                        If specified, this file overrides the system admin default whenever a post or RSS article has no media attached.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-body-emphasis" id="modal_media_label">Default Media URL / Path</label>
                        <div class="input-group">
                            <input type="text" name="default_media" id="modal_default_media" class="form-control" placeholder="https://example.com/file.mp4 or /public/assets/...">
                            <button type="button" class="btn btn-outline-primary fw-bold" id="btn-browse-fm" data-bs-toggle="modal" data-bs-target="#fileManagerModal">
                                <i class="bi bi-folder2-open"></i> Browse
                            </button>
                        </div>
                        <div class="form-text small" id="modal_media_hint">Enter a direct URL or pick a file from your File Manager.</div>
                    </div>

                    <div class="p-3 bg-body-tertiary border rounded mb-2">
                        <span class="small fw-bold text-body-secondary d-block mb-1">Global Admin Fallback:</span>
                        <div class="text-truncate font-monospace small" id="modal_global_default" style="max-width: 100%;">
                            <span class="text-body-secondary">None configured in Admin</span>
                        </div>
                        <small class="text-body-secondary d-block mt-1">If the field above is empty, this platform default will be used automatically.</small>
                    </div>
                </div>

                <div class="modal-footer bg-body-tertiary">
                    <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="btn-clear-media">Clear Override</button>
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for File Manager Iframe -->
<div class="modal fade" id="fileManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="height: 85vh;">
        <div class="modal-content h-100 shadow-lg border-0">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-folder2-open text-warning"></i> File Manager
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 h-100 bg-body-tertiary">
                <iframe src="/filemanager/standalone" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Live token verification
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

    // 2. Account custom media fallback modal logic
    const mediaModal = new bootstrap.Modal(document.getElementById('accountMediaModal'));
    const inputAccountId = document.getElementById('modal_account_id');
    const inputDefaultMedia = document.getElementById('modal_default_media');
    const labelAccountName = document.getElementById('modal_account_name');
    const labelMedia = document.getElementById('modal_media_label');
    const hintMedia = document.getElementById('modal_media_hint');
    const labelGlobalDefault = document.getElementById('modal_global_default');
    const btnClearMedia = document.getElementById('btn-clear-media');

    document.querySelectorAll('.btn-account-media').forEach(btn => {
        btn.addEventListener('click', function () {
            const accId = this.getAttribute('data-account-id');
            const accName = this.getAttribute('data-account-name');
            const isVideo = this.getAttribute('data-is-video') === '1';
            const currentMedia = this.getAttribute('data-current-media');
            const globalDefault = this.getAttribute('data-global-default');

            inputAccountId.value = accId;
            labelAccountName.textContent = accName;
            inputDefaultMedia.value = currentMedia || '';

            if (isVideo) {
                labelMedia.textContent = 'Account Fallback Video';
                hintMedia.textContent = 'Required for video-only posts on YouTube / TikTok. Select an MP4/WebM video.';
            } else {
                labelMedia.textContent = 'Account Fallback Image';
                hintMedia.textContent = 'Required for image-only posts on Instagram / Pinterest. Select a JPG/PNG/WebP image.';
            }

            if (globalDefault) {
                labelGlobalDefault.innerHTML = '<code class="text-primary">' + globalDefault + '</code>';
            } else {
                labelGlobalDefault.innerHTML = '<span class="text-body-secondary">None configured in Admin settings</span>';
            }

            mediaModal.show();
        });
    });

    if (btnClearMedia) {
        btnClearMedia.addEventListener('click', function () {
            inputDefaultMedia.value = '';
        });
    }

    // 3. Listen for file selection from standalone FileManager iframe
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'file_selected' && event.data.url) {
            inputDefaultMedia.value = event.data.url;
            const fmModalEl = document.getElementById('fileManagerModal');
            if (fmModalEl && typeof bootstrap !== 'undefined') {
                const fmInstance = bootstrap.Modal.getInstance(fmModalEl);
                if (fmInstance) fmInstance.hide();
            }
            // Return focus to the account settings modal
            mediaModal.show();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
