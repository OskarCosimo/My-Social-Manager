<?php
// app/Views/admin/index.php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') exit;
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<!-- Declare showAdminTab in global window scope immediately before rendering UI -->
<script>
window.showAdminTab = function(tabId, buttonElement) {
    // 1. Remove active state from all tab buttons
    var allButtons = document.querySelectorAll('#adminTabs button');
    allButtons.forEach(function(btn) {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });

    // 2. Activate clicked button
    if (buttonElement) {
        buttonElement.classList.add('active');
        buttonElement.setAttribute('aria-selected', 'true');
    } else {
        var matchingBtn = document.querySelector('#adminTabs button[data-tab-id="' + tabId + '"]');
        if (matchingBtn) {
            matchingBtn.classList.add('active');
            matchingBtn.setAttribute('aria-selected', 'true');
        }
    }

    // 3. Hide all tab panes
    var allPanes = document.querySelectorAll('#adminTabsContent > .tab-pane');
    allPanes.forEach(function(pane) {
        pane.classList.remove('show', 'active');
        pane.style.display = 'none';
    });

    // 4. Show and display target pane
    var targetPane = document.getElementById(tabId);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
        targetPane.style.display = 'block';
    }

    // 5. Update URL hash without jumping page
    if (history.replaceState) {
        history.replaceState(null, null, '#' + tabId);
    }
};

window.checkAdminHash = function() {
    var rawHash = (window.location.hash || '').replace('#', '');
    if (rawHash && document.getElementById(rawHash)) {
        var btn = document.querySelector('#adminTabs button[data-tab-id="' + rawHash + '"]');
        window.showAdminTab(rawHash, btn);
    }
};

window.addEventListener('hashchange', window.checkAdminHash);
document.addEventListener('DOMContentLoaded', window.checkAdminHash);
setTimeout(window.checkAdminHash, 50);
</script>

<div class="d-flex main-wrapper">
    
    <?php 
        $activeMenu = 'admin'; 
        require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        <h1 class="h2 mb-4 text-danger fw-bold d-inline-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill"></i> <?= __('admin_area_title') ?>
        </h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success shadow-sm border-0">
                <i class="bi bi-check-circle-fill me-2"></i> <?= __('system_updated_success') ?? 'System updated successfully and database migrations executed!' ?>
            </div>
        <?php endif; ?>

        <!-- Main Navigation Tabs with Direct Switch -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link active fw-bold text-dark d-inline-flex align-items-center gap-2" id="users-tab" data-tab-id="users" onclick="showAdminTab('users', this)">
                    <i class="bi bi-people text-primary"></i> <?= __('admin_tab_users') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="branding-tab" data-tab-id="branding" onclick="showAdminTab('branding', this)">
                    <i class="bi bi-palette text-danger"></i> <?= __('admin_tab_branding') ?? 'Branding & Logo' ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="auth-tab" data-tab-id="auth" onclick="showAdminTab('auth', this)">
                    <i class="bi bi-shield-check text-success"></i> <?= __('admin_tab_security') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="plugins-tab" data-tab-id="plugins" onclick="showAdminTab('plugins', this)">
                    <i class="bi bi-plugin text-warning"></i> <?= __('admin_tab_plugins') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="payments-tab" data-tab-id="payments" onclick="showAdminTab('payments', this)">
                    <i class="bi bi-credit-card-2-front text-info"></i> <?= __('admin_tab_payments') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="translations-tab" data-tab-id="translations" onclick="showAdminTab('translations', this)">
                    <i class="bi bi-globe text-secondary"></i> <?= __('admin_tab_translations') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="ai-tab" data-tab-id="ai" onclick="showAdminTab('ai', this)">
                    <i class="bi bi-robot text-primary"></i> <?= __('admin_tab_ai') ?? 'AI & Translation APIs' ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="cron-tab" data-tab-id="cron" onclick="showAdminTab('cron', this)">
                    <i class="bi bi-clock-history text-danger"></i> <?= __('admin_tab_cronjobs') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="email-tab" data-tab-id="email" onclick="showAdminTab('email', this)">
                    <i class="bi bi-envelope-at text-dark"></i> <?= __('admin_tab_email') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="scripts-tab" data-tab-id="scripts" onclick="showAdminTab('scripts', this)">
                    <i class="bi bi-code-slash" style="color: #6f42c1;"></i> <?= __('admin_tab_scripts') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link fw-bold text-dark d-inline-flex align-items-center gap-2" id="updater-tab" data-tab-id="updater" onclick="showAdminTab('updater', this)">
                    <i class="bi bi-cloud-arrow-down-fill text-primary"></i> <?= __('admin_tab_updater') ?? 'System Updates' ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            
            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel" style="display: block;">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= __('table_id') ?></th>
                                        <th><?= __('table_email') ?></th>
                                        <th><?= __('table_role') ?></th>
                                        <th><?= __('table_status') ?></th>
                                        <th><?= __('table_plan') ?></th>
                                        <th class="text-end"><?= __('table_action') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $plansMap = [];
                                        foreach ($plans as $p) {
                                            $plansMap[$p['id']] = $p['name'];
                                        }
                                    ?>
                                    <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= (int)$u['id'] ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($u['email']) ?></span>
                                            <?php if ((int)($u['email_verified'] ?? 0) === 1): ?>
                                                <i class="bi bi-patch-check-fill text-success ms-1" title="Email Verified"></i>
                                            <?php else: ?>
                                                <i class="bi bi-exclamation-circle text-warning ms-1" title="Email Not Verified"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>"><?= strtoupper($u['role']) ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'dark' ?>">
                                                <?= strtoupper($u['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($plansMap[$u['plan_id']] ?? 'Default') ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick='openUserManageModal(<?= json_encode($u) ?>, <?= (int)$_SESSION['user_id'] ?>)'>
                                                <i class="bi bi-gear-fill"></i> <?= __('manage') ?? 'Manage' ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branding & Logo Tab -->
            <div class="tab-pane fade" id="branding" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-palette text-danger"></i> <?= __('branding_logo_favicon') ?? 'Branding, Logo & Favicon' ?>
                    </div>
                    <div class="card-body">
                        <?php
                            $currentLogo = $sysSettings['app_logo'] ?? '';
                            $defaultLogo = '/public/assets/images/mysocialmanager_logo.png';
                            $displayLogo = !empty($currentLogo) ? $currentLogo : $defaultLogo;

                            $currentFavicon = $sysSettings['app_favicon'] ?? '';
                            $defaultFavicon = '/public/assets/images/favicon.png';
                            $displayFavicon = !empty($currentFavicon) ? $currentFavicon : $defaultFavicon;
                        ?>
                        <form action="/admin/settings/branding" method="POST" enctype="multipart/form-data">
                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-image text-primary"></i> <?= __('app_header_logo') ?? 'Application Header Logo' ?>
                                </h6>
                                <div class="row align-items-center g-3 mb-3">
                                    <div class="col-md-4 text-center">
                                        <div class="p-3 bg-dark rounded d-inline-block">
                                            <img src="<?= htmlspecialchars($displayLogo) ?>" alt="Current Logo Preview" style="max-width: 200px; max-height: 60px; object-fit: contain;">
                                        </div>
                                        <small class="d-block text-muted mt-1">Default: <code>mysocialmanager_logo.png</code></small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold"><?= __('upload_new_logo') ?? 'Upload New Logo (Saved to /public/assets/images/, Max: 2MB)' ?></label>
                                            <input type="file" name="logo_file" class="form-control form-control-sm" accept=".png,.jpg,.jpeg,.svg,.webp,.ico">
                                            <div class="form-text"><?= __('allowed_image_formats') ?? 'Allowed: PNG, JPG, JPEG, SVG, WEBP, ICO (Max size: 2MB).' ?></div>
                                        </div>
                                        <div>
                                            <label class="form-label small fw-bold"><?= __('current_path_or_url') ?? 'Current Path or External Direct URL' ?></label>
                                            <input type="text" name="logo_url" class="form-control form-control-sm" placeholder="https://example.com/logo.png or /public/assets/images/..." value="<?= htmlspecialchars($currentLogo) ?>">
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($currentLogo)): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="reset_logo" value="1" id="resetLogoCheck">
                                        <label class="form-check-label small text-danger fw-semibold" for="resetLogoCheck">
                                            <?= __('reset_logo_default') ?? 'Reset Logo to Default (/public/assets/images/mysocialmanager_logo.png)' ?>
                                        </label>
                                    </div>
                                <?php endif; ?>

                                <div class="form-check form-switch pt-2 border-top">
                                    <input class="form-check-input" type="checkbox" name="hide_sidebar_logo" id="hideSidebarLogo" value="1" <?= ($sysSettings['hide_sidebar_logo'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark small" for="hideSidebarLogo">
                                        <?= __('hide_sidebar_logo') ?? 'Hide Logo in Sidebar' ?>
                                    </label>
                                    <div class="form-text small"><?= __('hide_sidebar_logo_desc') ?? 'If checked, the logo image will not be rendered in the sidebar header.' ?></div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-browser-chrome text-success"></i> <?= __('browser_favicon') ?? 'Browser Favicon (.png / .ico)' ?>
                                </h6>
                                <div class="row align-items-center g-3 mb-3">
                                    <div class="col-md-4 text-center">
                                        <div class="p-3 bg-white border rounded d-inline-block shadow-sm">
                                            <img src="<?= htmlspecialchars($displayFavicon) ?>" alt="Current Favicon Preview" style="width: 48px; height: 48px; object-fit: contain;">
                                        </div>
                                        <small class="d-block text-muted mt-1">Default: <code>favicon.png</code></small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold"><?= __('upload_new_favicon') ?? 'Upload New Favicon (Saved to /public/assets/images/, Max: 2MB)' ?></label>
                                            <input type="file" name="favicon_file" class="form-control form-control-sm" accept=".png,.ico,.svg,.webp,.jpg">
                                            <div class="form-text"><?= __('allowed_image_formats') ?? 'Allowed: PNG, ICO, SVG, WEBP (Max size: 2MB).' ?></div>
                                        </div>
                                        <div>
                                            <label class="form-label small fw-bold"><?= __('current_path_or_url') ?? 'Current Path or External Direct URL' ?></label>
                                            <input type="text" name="favicon_url" class="form-control form-control-sm" placeholder="https://example.com/favicon.png or /public/assets/images/..." value="<?= htmlspecialchars($currentFavicon) ?>">
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($currentFavicon)): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reset_favicon" value="1" id="resetFaviconCheck">
                                        <label class="form-check-label small text-danger fw-semibold" for="resetFaviconCheck">
                                            <?= __('reset_favicon_default') ?? 'Reset Favicon to Default (/public/assets/images/favicon.png)' ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-floppy me-1"></i> <?= __('save_branding_settings') ?? 'Save Branding Settings' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security & Login Tab -->
            <div class="tab-pane fade" id="auth" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-primary"></i> <?= __('social_login_security_config') ?>
                    </div>
                    <div class="card-body">
                        <form action="/admin/settings/auth" method="POST">
                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="enable_social_google" id="enableGoogle" value="1" <?= ($sysSettings['enable_social_google'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="enableGoogle"><?= __('enable_google_login') ?></label>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <input type="text" name="google_client_id" class="form-control form-control-sm" placeholder="Google Client ID" value="<?= htmlspecialchars($sysSettings['google_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="google_client_secret" class="form-control form-control-sm" placeholder="Google Client Secret" value="<?= htmlspecialchars($sysSettings['google_client_secret'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="alert alert-secondary p-2 mb-0">
                                    <small class="fw-bold d-block mb-1"><?= __('authorized_redirect_uri') ?></small>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control bg-light" readonly value="<?= Config::APP_URL ?>/auth/callback/google">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="enable_social_facebook" id="enableFacebook" value="1" <?= ($sysSettings['enable_social_facebook'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="enableFacebook"><?= __('enable_facebook_login') ?></label>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <input type="text" name="facebook_client_id" class="form-control form-control-sm" placeholder="Facebook App ID" value="<?= htmlspecialchars($sysSettings['facebook_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="facebook_client_secret" class="form-control form-control-sm" placeholder="Facebook App Secret" value="<?= htmlspecialchars($sysSettings['facebook_client_secret'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="alert alert-secondary p-2 mb-0">
                                    <small class="fw-bold d-block mb-1"><?= __('authorized_redirect_uri') ?></small>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control bg-light" readonly value="<?= Config::APP_URL ?>/auth/callback/facebook">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="enable_social_microsoft" id="enableMicrosoft" value="1" <?= ($sysSettings['enable_social_microsoft'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="enableMicrosoft"><?= __('enable_microsoft_login') ?></label>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <input type="text" name="microsoft_client_id" class="form-control form-control-sm" placeholder="Microsoft Client ID" value="<?= htmlspecialchars($sysSettings['microsoft_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="microsoft_client_secret" class="form-control form-control-sm" placeholder="Microsoft Client Secret" value="<?= htmlspecialchars($sysSettings['microsoft_client_secret'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="alert alert-secondary p-2 mb-0">
                                    <small class="fw-bold d-block mb-1"><?= __('authorized_redirect_uri') ?></small>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control bg-light" readonly value="<?= Config::APP_URL ?>/auth/callback/microsoft">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-3 bg-light border-warning">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="enable_social_myetv" id="enableMyetv" value="1" <?= ($sysSettings['enable_social_myetv'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="enableMyetv"><?= __('enable_myetv_login') ?></label>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <input type="text" name="myetv_client_id" class="form-control form-control-sm" placeholder="MYETV Client ID" value="<?= htmlspecialchars($sysSettings['myetv_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="myetv_client_secret" class="form-control form-control-sm" placeholder="MYETV Client Secret" value="<?= htmlspecialchars($sysSettings['myetv_client_secret'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="alert alert-warning p-2 mb-0">
                                    <small class="fw-bold d-block mb-1"><?= __('authorized_redirect_uri') ?></small>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control bg-light" readonly value="<?= Config::APP_URL ?>/auth/callback/myetv">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light border-info">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="enable_turnstile" id="enableTurnstile" value="1" <?= ($sysSettings['enable_turnstile'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="enableTurnstile"><?= __('enable_turnstile_antibot') ?></label>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <input type="text" name="turnstile_site_key" class="form-control form-control-sm" placeholder="Turnstile Site Key" value="<?= htmlspecialchars($sysSettings['turnstile_site_key'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="password" name="turnstile_secret_key" class="form-control form-control-sm" placeholder="Turnstile Secret Key" value="<?= htmlspecialchars($sysSettings['turnstile_secret_key'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold"><?= __('save_auth_security_settings') ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Plugins Config Tab -->
            <div class="tab-pane fade" id="plugins" role="tabpanel" style="display: none;">
                <div class="row">
                    <div class="container-fluid mb-3">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        <?= str_replace('{url}', '<code>' . Config::APP_URL . '/plugin/callback</code>', __('callback_url_notice')) ?>
                    </div>

                    <?php foreach ($plugins as $plugin): ?>
                        <?php 
                            $settings = $pluginSavedSettings[$plugin->getId()] ?? []; 
                            $hasGuide = method_exists($plugin, 'getAdminInstructionsHtml');
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <form action="/admin/plugin/save" method="POST" class="d-flex flex-column h-100 m-0">
                                    <input type="hidden" name="plugin_id" value="<?= htmlspecialchars($plugin->getId()) ?>">
                                    
                                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title m-0 fw-bold"><?= htmlspecialchars($plugin->getName()) ?></h5>
                                        
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($hasGuide): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 fw-semibold d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#guideModal_<?= htmlspecialchars($plugin->getId()) ?>" title="<?= __('guide') ?>">
                                                    <i class="bi bi-question-circle"></i> <?= __('guide') ?>
                                                </button>
                                            <?php endif; ?>

                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="active_<?= htmlspecialchars($plugin->getId()) ?>" value="1" <?= ($settings['is_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label small text-white" for="active_<?= htmlspecialchars($plugin->getId()) ?>">
                                                    <?= __('token_status_active') ?? 'Active' ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column justify-content-between">
                                        <div>
                                            <?php $fields = $plugin->getSettingsFields(); ?>
                                            <?php if (empty($fields)): ?>
                                                <p class="text-muted small mb-0"><?= __('no_global_api_settings') ?></p>
                                            <?php else: ?>
                                                <?php foreach ($fields as $key => $field): ?>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold"><?= htmlspecialchars($field['label']) ?></label>
                                                        <input type="<?= htmlspecialchars($field['type']) ?>" name="<?= htmlspecialchars($key) ?>" class="form-control" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold"><?= __('save_config') ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if ($hasGuide): ?>
                            <div class="modal fade" id="guideModal_<?= htmlspecialchars($plugin->getId()) ?>" tabindex="-1" aria-labelledby="guideModalLabel_<?= htmlspecialchars($plugin->getId()) ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2" id="guideModalLabel_<?= htmlspecialchars($plugin->getId()) ?>">
                                                <i class="bi bi-book"></i> <?= htmlspecialchars($plugin->getName()) ?> — <?= __('guide') ?>
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= __('close') ?? 'Close' ?>"></button>
                                        </div>
                                        <div class="modal-body p-4 fs-6">
                                            <?= $plugin->getAdminInstructionsHtml() ?>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal"><?= __('close') ?? 'Close' ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Payments & Fees Tab -->
            <div class="tab-pane fade" id="payments" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-credit-card-2-front text-info"></i> <?= __('master_payment_gateways') ?>
                    </div>
                    <div class="card-body">
                        <form action="/admin/settings/payments" method="POST">
                            <div class="mb-4 p-3 border rounded bg-light">
                                <h6 class="fw-bold text-primary mb-2"><?= __('platform_commission') ?></h6>
                                <div class="input-group" style="max-width: 250px;">
                                    <input type="number" step="0.1" name="platform_commission_percent" class="form-control" value="<?= htmlspecialchars($sysSettings['platform_commission_percent'] ?? '5.0') ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted d-block mt-1"><?= __('platform_commission_help') ?></small>
                            </div>

                            <div class="mb-4 p-3 border rounded bg-light">
                                <h6 class="fw-bold text-dark mb-2"><?= __('stripe_master_credentials') ?></h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small"><?= __('stripe_secret_key') ?? 'Secret Key' ?></label>
                                        <input type="password" name="stripe_secret_key" class="form-control form-control-sm" value="<?= htmlspecialchars($sysSettings['stripe_secret_key'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small"><?= __('stripe_public_key') ?? 'Public Key' ?></label>
                                        <input type="text" name="stripe_public_key" class="form-control form-control-sm" value="<?= htmlspecialchars($sysSettings['stripe_public_key'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <label class="form-label small"><?= __('stripe_webhook_secret') ?? 'Webhook Secret' ?></label>
                                        <input type="password" name="stripe_webhook_secret" class="form-control form-control-sm" value="<?= htmlspecialchars($sysSettings['stripe_webhook_secret'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 p-3 border rounded bg-light">
                                <h6 class="fw-bold text-dark mb-2"><?= __('paypal_master_credentials') ?></h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <label class="form-label small"><?= __('environment_mode') ?? 'Environment Mode' ?></label>
                                        <select name="paypal_mode" class="form-select form-select-sm">
                                            <option value="sandbox" <?= ($sysSettings['paypal_mode'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                                            <option value="live" <?= ($sysSettings['paypal_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live (Production)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small"><?= __('client_id') ?? 'Client ID' ?></label>
                                        <input type="text" name="paypal_client_id" class="form-control form-control-sm" value="<?= htmlspecialchars($sysSettings['paypal_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small"><?= __('client_secret') ?? 'Client Secret' ?></label>
                                        <input type="password" name="paypal_secret" class="form-control form-control-sm" value="<?= htmlspecialchars($sysSettings['paypal_secret'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold"><?= __('save_payment_configurations') ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Translations Tab -->
            <div class="tab-pane fade" id="translations" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4 bg-white">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2 border-bottom-0 pt-4 pb-0">
                        <i class="bi bi-eye text-primary"></i> <?= __('enabled_languages_title') ?? 'Visible Languages in Selectors' ?>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-3"><?= __('enabled_languages_desc') ?? 'Select which installed languages will appear in the language selection dropdowns across the platform.' ?></p>

                        <form action="/admin/settings/languages" method="POST">
                            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-2 mb-3">
                                <?php 
                                    $activeLangList = $languages ?? $availableLanguages ?? [];
                                    $curEnabled = $enabledLanguages ?? ['en'];
                                ?>
                                <?php foreach ($activeLangList as $lCode): ?>
                                    <div class="col">
                                        <div class="form-check p-2 border rounded bg-light d-flex align-items-center">
                                            <input class="form-check-input ms-1 me-2" type="checkbox" name="enabled_languages[]" value="<?= htmlspecialchars($lCode) ?>" id="enabled_lang_<?= htmlspecialchars($lCode) ?>" <?= in_array($lCode, $curEnabled, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold font-monospace text-uppercase cursor-pointer" for="enabled_lang_<?= htmlspecialchars($lCode) ?>">
                                                <?= htmlspecialchars($lCode) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary px-4 fw-bold">
                                <i class="bi bi-floppy me-1"></i> <?= __('save_enabled_languages') ?? 'Save Visible Languages' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4 bg-white">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2 border-bottom-0 pt-4 pb-0">
                        <i class="bi bi-globe text-secondary"></i> <?= __('auto_translator_title') ?>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-3"><?= __('auto_translator_desc') ?></p>

                        <div class="alert alert-light border mb-4 p-3 d-flex align-items-center gap-2 flex-wrap">
                            <span class="small fw-bold text-dark"><i class="bi bi-folder-check text-success"></i> <?= __('languages_installed_in_folder') ?? 'Languages installed in the folder /lang/:' ?></span>
                            <?php if (empty($activeLangList)): ?>
                                <span class="badge bg-warning text-dark"><?= __('no_json_file_found') ?? 'No .json file found' ?></span>
                            <?php else: ?>
                                <?php foreach ($activeLangList as $activeLang): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace text-uppercase">
                                        <?= htmlspecialchars($activeLang) ?>.json
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="btn-select-all-langs">
                                    <i class="bi bi-check-all"></i> <?= __('select_all') ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-deselect-all-langs">
                                    <i class="bi bi-x"></i> <?= __('deselect_all') ?>
                                </button>
                            </div>

                            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 shadow-sm fw-bold" id="btn-start-batch-translate">
                                <i class="bi bi-translate"></i> <?= __('translate_selected') ?> (<span id="selected-lang-count">0</span>)
                            </button>
                        </div>

                        <div id="translation-progress-box" class="mb-4 p-3 bg-light border rounded d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-dark" id="progress-status-text"><?= __('translating') ?></span>
                                <span class="small fw-bold text-primary" id="progress-percent-text">0%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div id="translation-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <div id="translation-log" class="small mt-2 text-muted font-monospace" style="max-height: 120px; overflow-y: auto;"></div>
                        </div>

                        <?php 
                        $supportedLangs = [
                            'it' => ['name' => 'Italian', 'flag' => '🇮🇹'],
                            'es' => ['name' => 'Spanish', 'flag' => '🇪🇸'],
                            'fr' => ['name' => 'French', 'flag' => '🇫🇷'],
                            'de' => ['name' => 'German', 'flag' => '🇩🇪'],
                            'pt' => ['name' => 'Portuguese', 'flag' => '🇵🇹'],
                            'ru' => ['name' => 'Russian', 'flag' => '🇷🇺'],
                            'zh' => ['name' => 'Chinese', 'flag' => '🇨🇳'],
                            'ja' => ['name' => 'Japanese', 'flag' => '🇯🇵'],
                            'ar' => ['name' => 'Arabic', 'flag' => '🇸🇦'],
                            'nl' => ['name' => 'Dutch', 'flag' => '🇳🇱'],
                            'pl' => ['name' => 'Polish', 'flag' => '🇵🇱'],
                            'tr' => ['name' => 'Turkish', 'flag' => '🇹🇷'],
                            'hi' => ['name' => 'Hindi', 'flag' => '🇮🇳'],
                            'sv' => ['name' => 'Swedish', 'flag' => '🇸🇪'],
                            'el' => ['name' => 'Greek', 'flag' => '🇬🇷'],
                            'ro' => ['name' => 'Romanian', 'flag' => '🇷🇴'],
                            'cs' => ['name' => 'Czech', 'flag' => '🇨🇿'],
                            'uk' => ['name' => 'Ukrainian', 'flag' => '🇺🇦'],
                            'ko' => ['name' => 'Korean', 'flag' => '🇰🇷'],
                            'hu' => ['name' => 'Hungarian', 'flag' => '🇭🇺'],
                        ];
                        ?>

                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-2">
                            <?php foreach ($supportedLangs as $code => $info): ?>
                                <?php $alreadyExists = in_array($code, $activeLangList, true); ?>
                                <div class="col">
                                    <div class="form-check p-2 border rounded bg-light h-100 d-flex align-items-center justify-content-between">
                                        <div>
                                            <input class="form-check-input lang-checkbox ms-1 me-2" type="checkbox" value="<?= $code ?>" id="chk_lang_<?= $code ?>">
                                            <label class="form-check-label cursor-pointer fw-semibold small" for="chk_lang_<?= $code ?>">
                                                <?= $info['flag'] ?> <?= $info['name'] ?> <span class="text-muted">(<?= $code ?>)</span>
                                            </label>
                                        </div>
                                        <?php if ($alreadyExists): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">
                                                <i class="bi bi-check2"></i> <?= __('file_exists') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>

            <!-- AI & Translation APIs Tab -->
            <div class="tab-pane fade" id="ai" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-robot text-primary"></i> <?= __('ai_and_translation_settings') ?? 'Artificial Intelligence & Machine Translation Settings' ?>
                    </div>
                    <div class="card-body">
                        <form id="aiSettingsForm" action="/admin/settings/ai" method="POST">
                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-sliders text-primary"></i> <?= __('active_ai_provider') ?? 'Active AI Provider' ?>
                                </h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold"><?= __('select_active_engine') ?? 'Select Active Engine (AI_PROVIDER)' ?></label>
                                    <select name="ai_provider" class="form-select form-select-sm" style="max-width: 320px;">
                                        <option value="openai-chat" <?= Config::AI_PROVIDER === 'openai-chat' ? 'selected' : '' ?>>OpenAI / OpenAI-Compatible (Chat Completions)</option>
                                        <option value="ollama" <?= Config::AI_PROVIDER === 'ollama' ? 'selected' : '' ?>>Ollama Native API (/api/generate)</option>
                                        <option value="gemini" <?= Config::AI_PROVIDER === 'gemini' ? 'selected' : '' ?>>Google Gemini API</option>
                                    </select>
                                    <div class="form-text small"><?= __('select_ai_backend_desc') ?? 'Selects which AI back-end is used throughout the platform for automated content creation and captions.' ?></div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-cpu text-success"></i> <?= __('openai_compatible_endpoints') ?? 'OpenAI & Compatible Endpoints' ?>
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold"><?= __('api_endpoint_url') ?? 'API Endpoint URL' ?></label>
                                        <input type="text" name="openai_endpoint" class="form-control form-control-sm" placeholder="https://api.openai.com/v1/chat/completions" value="<?= htmlspecialchars(Config::OPENAI_ENDPOINT) ?>">
                                        <div class="form-text small">Default: <code>http://localhost:11435/v1/chat/completions</code></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold"><?= __('model_name') ?? 'Model Name' ?></label>
                                        <input type="text" name="openai_model" class="form-control form-control-sm" placeholder="gpt-4o-mini, gpt-4o, llama3..." value="<?= htmlspecialchars(Config::OPENAI_MODEL) ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small fw-bold"><?= __('api_key_auth') ?? 'API Key (Authorization Bearer)' ?></label>
                                        <input type="password" name="openai_api_key" class="form-control form-control-sm" placeholder="sk-..." value="<?= htmlspecialchars(Config::OPENAI_API_KEY) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-hdd-network text-warning"></i> <?= __('ollama_native_api') ?? 'Ollama Native API' ?>
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold"><?= __('api_endpoint_url') ?? 'API Endpoint URL' ?></label>
                                        <input type="text" name="ollama_endpoint" class="form-control form-control-sm" placeholder="http://localhost:11434/api/generate" value="<?= htmlspecialchars(Config::OLLAMA_ENDPOINT) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold"><?= __('model_name') ?? 'Model Name' ?></label>
                                        <input type="text" name="ollama_model" class="form-control form-control-sm" placeholder="llama3, mistral, qwen2..." value="<?= htmlspecialchars(Config::OLLAMA_MODEL) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-stars text-danger"></i> <?= __('google_gemini_api') ?? 'Google Gemini API' ?>
                                </h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold"><?= __('api_key_auth') ?? 'Gemini API Key' ?></label>
                                    <input type="password" name="gemini_api_key" class="form-control form-control-sm" placeholder="AIzaSy..." value="<?= htmlspecialchars(Config::GEMINI_API_KEY) ?>">
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-translate text-info"></i> <?= __('libretranslate_server_config') ?? 'LibreTranslate Server Configuration' ?>
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold"><?= __('libretranslate_url') ?? 'LibreTranslate URL' ?></label>
                                        <input type="text" name="libretranslate_url" class="form-control form-control-sm" placeholder="http://localhost:5000/translate" value="<?= htmlspecialchars(Config::LIBRETRANSLATE_URL) ?>">
                                        <div class="form-text small"><?= __('libretranslate_url_desc') ?? 'Endpoint used by the automatic translation generator.' ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold"><?= __('libretranslate_api_key') ?? 'LibreTranslate API Key' ?></label>
                                        <input type="password" name="libretranslate_api_key" class="form-control form-control-sm" placeholder="<?= __('leave_empty_local') ?? 'Leave empty if local/unauthenticated' ?>" value="<?= htmlspecialchars(Config::LIBRETRANSLATE_API_KEY) ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-floppy me-1"></i> <?= __('save_ai_translation_settings') ?? 'Save AI & Translation Settings' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cron Tab -->
            <div class="tab-pane fade" id="cron" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span class="d-inline-flex align-items-center gap-2">
                            <i class="bi bi-cpu text-primary"></i> <?= __('internal_php_cron') ?? 'Internal PHP Cron Runner (Pseudo-Cron)' ?>
                        </span>
                        <span class="badge bg-<?= ($sysSettings['enable_internal_cron'] ?? '0') === '1' ? 'success' : 'secondary' ?>">
                            <?= ($sysSettings['enable_internal_cron'] ?? '0') === '1' ? (__('token_status_active') ?? 'ACTIVE') : (__('disabled') ?? 'DISABLED') ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            <?= __('internal_cron_desc') ?? 'When enabled, tasks will be triggered automatically during web visits. Disable this if you are already using system Crontab to prevent duplicate executions.' ?>
                        </p>

                        <form action="/admin/cron/settings" method="POST" class="d-flex align-items-center gap-3">
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="enable_internal_cron" id="enableInternalCron" value="1" <?= ($sysSettings['enable_internal_cron'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="enableInternalCron"><?= __('enable_internal_cron_label') ?? 'Enable Internal PHP Cron' ?></label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary fw-bold"><?= __('save') ?></button>
                        </form>

                        <hr class="my-3">

                        <h6 class="fw-bold mb-2"><?= __('internal_tasks_status') ?? 'Worker Tasks Status' ?></h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= __('task') ?? 'Task' ?></th>
                                        <th><?= __('interval') ?? 'Interval' ?></th>
                                        <th><?= __('last_execution') ?? 'Last Execution' ?></th>
                                        <th><?= __('next_estimated_run') ?? 'Next Estimated Run' ?></th>
                                        <th class="text-end"><?= __('manual_action') ?? 'Manual Action' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cronTasks as $key => $t): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($t['name']) ?></td>
                                        <td><?= round($t['interval'] / 60) ?> min</td>
                                        <td><?= $t['last_run'] ?></td>
                                        <td>
                                            <?php if ($t['is_due']): ?>
                                                <span class="badge bg-warning text-dark"><?= __('due_now') ?? 'Due Now' ?></span>
                                            <?php else: ?>
                                                <?= $t['next_run'] ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form action="/admin/cron/run" method="POST" class="d-inline">
                                                <input type="hidden" name="task_key" value="<?= $key ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-primary py-0 px-2 small">
                                                    <i class="bi bi-play-fill"></i> <?= __('run_now') ?? 'Run Now' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-terminal text-dark"></i> <?= __('system_crontab_config') ?? 'Server Crontab Configuration' ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3"><?= __('cron_instructions') ?? 'Add the following lines to your server crontab (crontab -e) to run tasks directly via the CLI.' ?></p>
                        
                        <?php 
                            $cronPath = realpath(__DIR__ . '/../../../cron');
                        ?>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-primary"><i class="bi bi-send me-1"></i> Publisher Worker (Every minute)</span>
                            </div>
                            <div class="bg-dark text-light p-2 rounded font-monospace small" style="user-select: all;">
                                * * * * * /usr/bin/php <?= $cronPath ?>/publisher_worker.php >> <?= $cronPath ?>/publisher.log 2>&1
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-info"><i class="bi bi-rss me-1"></i> RSS Feeds Parser (Every 15 minutes)</span>
                            </div>
                            <div class="bg-dark text-light p-2 rounded font-monospace small" style="user-select: all;">
                                */15 * * * * /usr/bin/php <?= $cronPath ?>/rss_worker.php >> <?= $cronPath ?>/rss.log 2>&1
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-warning text-dark"><i class="bi bi-robot me-1"></i> AI Campaign Generator (Every minute)</span>
                            </div>
                            <div class="bg-dark text-light p-2 rounded font-monospace small" style="user-select: all;">
                                * * * * * /usr/bin/php <?= $cronPath ?>/ai_campaign_worker.php >> <?= $cronPath ?>/ai_campaign.log 2>&1
                            </div>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-danger"><i class="bi bi-arrow-repeat me-1"></i> Usage & Quota Reset (Daily / Hourly check)</span>
                            </div>
                            <div class="bg-dark text-light p-2 rounded font-monospace small" style="user-select: all;">
                                0 0 * * * /usr/bin/php <?= $cronPath ?>/reset_monthly_usage.php >> <?= $cronPath ?>/reset.log 2>&1
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-success"><i class="bi bi-broadcast-pin me-1"></i> Channel Automations Worker (Every 15 minutes)</span>
                            </div>
                            <div class="bg-dark text-light p-2 rounded font-monospace small" style="user-select: all;">
                                */15 * * * * /usr/bin/php <?= $cronPath ?>/channel_worker.php >> <?= $cronPath ?>/channel.log 2>&1
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span class="d-inline-flex align-items-center gap-2">
                            <i class="bi bi-hourglass-split text-info"></i> <?= __('upcoming_queued_posts') ?? 'Upcoming Queued Posts (Top 100)' ?>
                        </span>
                        <span class="badge bg-primary"><?= __('pending_badge', ['count' => count($queuedJobs)]) ?: (count($queuedJobs) . ' Pending') ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-hover table-striped table-sm align-middle mb-0 font-sans">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th><?= __('table_id') ?? 'ID' ?></th>
                                        <th><?= __('user_header') ?? 'User' ?></th>
                                        <th><?= __('account_details') ?? 'Account / Target' ?></th>
                                        <th><?= __('scheduled_for_header') ?? 'Scheduled For' ?></th>
                                        <th><?= __('origin') ?? 'Origin' ?></th>
                                        <th><?= __('content_preview') ?? 'Content Preview' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($queuedJobs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted small"><?= __('no_scheduled_posts_queue') ?? 'No scheduled posts currently waiting in queue.' ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($queuedJobs as $job): ?>
                                            <tr>
                                                <td class="fw-bold">#<?= $job['id'] ?></td>
                                                <td class="small"><?= htmlspecialchars($job['user_email'] ?? 'ID: ' . $job['user_id']) ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($job['platform_name'] ?? $job['plugin_id'] ?? 'N/A') ?></span>
                                                </td>
                                                <td class="font-monospace small text-primary fw-semibold"><?= $job['scheduled_for'] ?></td>
                                                <td>
                                                    <?php if (!empty($job['is_rss'])): ?>
                                                        <span class="badge bg-info-subtle text-info border">RSS</span>
                                                    <?php elseif (!empty($job['campaign_id'])): ?>
                                                        <span class="badge bg-warning-subtle text-warning border">AI Campaign #<?= $job['campaign_id'] ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border"><?= __('manual') ?? 'Manual' ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($job['content']) ?>">
                                                    <?= htmlspecialchars(mb_substr($job['content'], 0, 70)) ?>...
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4 border-danger-subtle">
                    <div class="card-header bg-danger-subtle fw-bold d-flex justify-content-between align-items-center">
                        <span class="d-inline-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-exclamation-octagon-fill"></i> <?= __('failed_jobs_explanations') ?? 'Failed Jobs & Error Explanations (Top 100)' ?>
                        </span>
                        <span class="badge bg-danger"><?= __('failed_badge', ['count' => count($failedJobs)]) ?: (count($failedJobs) . ' Failed') ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th><?= __('table_id') ?? 'ID' ?></th>
                                        <th><?= __('user_header') ?? 'User' ?></th>
                                        <th><?= __('account_details') ?? 'Target' ?></th>
                                        <th><?= __('scheduled_for_header') ?? 'Scheduled For' ?></th>
                                        <th><?= __('error_cause_header') ?? 'Error Cause / API Response' ?></th>
                                        <th class="text-end"><?= __('table_action') ?? 'Action' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($failedJobs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-success small">
                                                <i class="bi bi-check-circle me-1"></i> <?= __('no_failed_jobs') ?? 'No failed jobs recorded. Everything is running smoothly.' ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($failedJobs as $fJob): ?>
                                            <tr>
                                                <td class="fw-bold text-danger">#<?= $fJob['id'] ?></td>
                                                <td class="small"><?= htmlspecialchars($fJob['user_email'] ?? 'ID: ' . $fJob['user_id']) ?></td>
                                                <td>
                                                    <span class="badge bg-dark"><?= htmlspecialchars($fJob['platform_name'] ?? $fJob['plugin_id'] ?? 'N/A') ?></span>
                                                </td>
                                                <td class="small font-monospace"><?= $fJob['scheduled_for'] ?></td>
                                                <td>
                                                    <div class="alert alert-danger p-2 mb-0 font-monospace small" style="white-space: pre-wrap; max-height: 80px; overflow-y: auto;">
                                                        <?= htmlspecialchars($fJob['error_log'] ?? 'Unknown error occurred.') ?>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <form action="/admin/cron/retry-post" method="POST" class="d-inline">
                                                        <input type="hidden" name="post_id" value="<?= $fJob['id'] ?>">
                                                        <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2 small">
                                                            <i class="bi bi-arrow-clockwise"></i> <?= __('retry') ?? 'Retry' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email / SMTP Tab -->
            <div class="tab-pane fade" id="email" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-envelope-at text-dark"></i> <?= __('mail_server_config') ?>
                    </div>
                    <div class="card-body">
                        <form action="/admin/settings/mail" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= __('mail_method') ?></label>
                                <select name="mail_method" class="form-select" id="mailMethodSelect">
                                    <option value="local" <?= ($sysSettings['mail_method'] ?? '') === 'local' ? 'selected' : '' ?>><?= __('local_server_php_mail') ?></option>
                                    <option value="smtp" <?= ($sysSettings['mail_method'] ?? '') === 'smtp' ? 'selected' : '' ?>><?= __('external_smtp_server') ?></option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= __('default_sender_address') ?></label>
                                <input type="email" name="smtp_sender" class="form-control" placeholder="noreply@myetv.tv" value="<?= htmlspecialchars($sysSettings['smtp_sender'] ?? '') ?>">
                                <div class="form-text"><?= __('default_sender_help') ?></div>
                            </div>

                            <div id="smtpFields" class="bg-light p-3 rounded border mb-3">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label small"><?= __('smtp_host') ?></label>
                                        <input type="text" name="smtp_host" class="form-control form-control-sm" placeholder="smtp.example.com" value="<?= htmlspecialchars($sysSettings['smtp_host'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small"><?= __('smtp_port') ?></label>
                                        <input type="text" name="smtp_port" class="form-control form-control-sm" placeholder="587" value="<?= htmlspecialchars($sysSettings['smtp_port'] ?? '587') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small"><?= __('smtp_username') ?></label>
                                        <input type="text" name="smtp_user" class="form-control form-control-sm" value="<?= htmlspecialchars($sysSettings['smtp_user'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small"><?= __('smtp_password') ?></label>
                                        <input type="password" name="smtp_pass" class="form-control form-control-sm" placeholder="<?= __('leave_blank_keep_current') ?>">
                                    </div>
                                    <div class="col-md-12 mt-3 border-top pt-3">
                                        <label class="form-label small fw-bold"><?= __('encryption_protocol') ?></label>
                                        <select name="smtp_encryption" class="form-select form-select-sm">
                                            <option value="none" <?= ($sysSettings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="tls" <?= ($sysSettings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (Usually Port 587)</option>
                                            <option value="ssl" <?= ($sysSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (Usually Port 465)</option>
                                        </select>
                                    </div>
                                    <div class="mb-4 pb-3 border-bottom">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="require_email_verification" id="requireVerification" value="1" <?= ($sysSettings['require_email_verification'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="requireVerification"><?= __('require_email_verification') ?></label>
                                            <div class="form-text small"><?= __('require_email_verification_help') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold"><?= __('save_mail_settings') ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Scripts & Ads Tab -->
            <div class="tab-pane fade" id="scripts" role="tabpanel" style="display: none;">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-code-slash" style="color: #6f42c1;"></i> <?= __('custom_scripts_ads') ?>
                    </div>
                    <div class="card-body">
                        <form action="/admin/settings/scripts" method="POST">
                            <div class="alert alert-info small d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle fs-5 flex-shrink-0"></i>
                                <div><?= __('custom_scripts_warning') ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= __('header_scripts') ?></label>
                                <textarea name="custom_header_script" class="form-control font-monospace bg-light" rows="5" placeholder="<script>...</script>"><?= htmlspecialchars($sysSettings['custom_header_script'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= __('footer_scripts') ?></label>
                                <textarea name="custom_footer_script" class="form-control font-monospace bg-light" rows="5" placeholder="<script>...</script>"><?= htmlspecialchars($sysSettings['custom_footer_script'] ?? '') ?></textarea>
                            </div>

                            <hr class="my-4">

                            <div class="mb-3 border rounded p-3 bg-light">
                                <label class="form-label fw-bold text-primary d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-megaphone"></i> <?= __('advertisement_banner_script') ?>
                                </label>
                                <textarea name="custom_ads_script" class="form-control font-monospace bg-white" rows="5" placeholder="<!-- AdSense Code / Banner Script -->"><?= htmlspecialchars($sysSettings['custom_ads_script'] ?? '') ?></textarea>
                                
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" name="ads_free_only" id="adsFreeOnly" value="1" <?= ($sysSettings['ads_free_only'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="adsFreeOnly"><?= __('show_ads_free_only') ?></label>
                                    <div class="form-text small"><?= __('show_ads_free_only_help') ?></div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold"><?= __('save_scripts_ads_settings') ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Updates Tab -->
            <div class="tab-pane fade" id="updater" role="tabpanel" style="display: none;">
                <?php
                    require_once __DIR__ . '/../../Services/UpdaterService.php';
                    $updaterService = new UpdaterService();
                    $installedVer   = $updaterService->getCurrentVersion();
                    $updateCheck    = $updaterService->checkForUpdates();
                    $permCheck      = $updaterService->checkWritePermissions();
                ?>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-info-circle text-primary me-2"></i><?= __('version_details') ?? 'Version Details' ?></span>
                                <span class="badge bg-secondary"><?= __('current_version_badge', ['version' => htmlspecialchars($installedVer)]) ?: ('Current: v' . htmlspecialchars($installedVer)) ?></span>
                            </div>
                            <div class="card-body">
                                <p class="mb-3 text-muted small"><?= __('auto_updates_desc') ?? 'Automatic updates fetch the latest GitHub release, extract files, and run database migrations automatically.' ?></p>

                                <?php if ($updateCheck === null): ?>
                                    <div class="alert alert-warning small mb-0">
                                        <i class="bi bi-exclamation-triangle me-1"></i> <?= __('github_conn_error') ?? 'Unable to connect to GitHub API. Please check server internet connectivity or rate limits.' ?>
                                    </div>
                                <?php elseif ($updateCheck['has_update']): ?>
                                    <div class="alert alert-info border mb-3">
                                        <h5 class="fw-bold mb-1 text-primary"><?= __('new_version_available', ['version' => htmlspecialchars($updateCheck['version'])]) ?: ('New Version Available: v' . htmlspecialchars($updateCheck['version'])) ?></h5>
                                        <small class="text-muted d-block mb-2"><?= __('published_date', ['date' => htmlspecialchars($updateCheck['published_at'] ?? 'N/A')]) ?: ('Published: ' . htmlspecialchars($updateCheck['published_at'] ?? 'N/A')) ?></small>
                                        <div class="p-2 bg-white rounded small mb-3 border font-monospace" style="max-height: 140px; overflow-y: auto;">
                                            <?= nl2br(htmlspecialchars($updateCheck['release_notes'])) ?>
                                        </div>

                                        <?php if ($permCheck['is_ready']): ?>
                                            <form method="POST" action="/admin/updater/apply" onsubmit="return confirm('<?= addslashes(__('confirm_install_update') ?? 'Do you want to download and install this update now?') ?>');">
                                                <input type="hidden" name="zip_url" value="<?= htmlspecialchars($updateCheck['zip_url']) ?>">
                                                <button type="submit" class="btn btn-success fw-bold w-100">
                                                    <i class="bi bi-cloud-arrow-down-fill me-1"></i> <?= __('install_update_btn', ['version' => htmlspecialchars($updateCheck['version'])]) ?: ('Install Update v' . htmlspecialchars($updateCheck['version'])) ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary w-100" disabled><?= __('cannot_update_perm_failed') ?? 'Cannot update: permissions check failed' ?></button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success d-flex align-items-center mb-0">
                                        <i class="bi bi-check-circle-fill text-success fs-4 me-2"></i>
                                        <div><strong><?= __('system_up_to_date') ?? 'Your system is up to date!' ?></strong><br><small class="text-muted"><?= __('running_version', ['version' => htmlspecialchars($installedVer)]) ?: ('Running version v' . htmlspecialchars($installedVer)) ?></small></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white fw-bold py-3">
                                <i class="bi bi-shield-check text-success me-2"></i><?= __('directory_permissions') ?? 'Directory Permissions' ?>
                            </div>
                            <div class="card-body">
                                <?php if ($permCheck['is_ready']): ?>
                                    <div class="alert alert-success d-flex align-items-center mb-0">
                                        <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
                                        <div><?= __('all_dirs_writable') ?? 'All required directories are writable. The system is ready to receive automated updates.' ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger mb-3">
                                        <strong><?= __('webserver_cannot_write_paths') ?? 'The webserver cannot write to these paths:' ?></strong>
                                        <ul class="mb-0 mt-2 small">
                                            <?php foreach ($permCheck['failed_paths'] as $path): ?>
                                                <li><?= htmlspecialchars($path) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="bg-dark text-white p-2 rounded small">
                                        <code>sudo chown -R www-data:www-data <?= htmlspecialchars(dirname(__DIR__, 2)) ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Modal: Manage User -->
<div class="modal fade" id="manageUserModal" tabindex="-1" aria-labelledby="manageUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2" id="manageUserModalLabel">
                    <i class="bi bi-person-gear"></i> <?= __('manage_user') ?? 'Manage User Account' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="/admin/user/update" method="POST" id="manageUserForm">
                <input type="hidden" name="user_id" id="modal_user_id">

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold"><?= __('table_email') ?? 'Email Address' ?></label>
                            <input type="email" id="modal_user_email" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold"><?= __('registered_at') ?? 'Registered On' ?></label>
                            <input type="text" id="modal_user_created_at" class="form-control bg-light" readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold"><?= __('table_role') ?? 'Role' ?></label>
                            <select name="role" id="modal_user_role" class="form-select">
                                <option value="user">USER</option>
                                <option value="admin">ADMIN</option>
                            </select>
                            <div class="form-text small text-danger" id="role_protected_hint" style="display:none;">Admin role locked for your own account.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold"><?= __('table_status') ?? 'Status' ?></label>
                            <select name="status" id="modal_user_status" class="form-select">
                                <option value="active">ACTIVE</option>
                                <option value="banned">BANNED</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold"><?= __('table_plan') ?? 'Subscription Plan' ?></label>
                            <select name="plan_id" id="modal_user_plan_id" class="form-select">
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= (int)$plan['id'] ?>">
                                        <?= htmlspecialchars($plan['name']) ?> (€<?= htmlspecialchars($plan['price']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_verified" id="modal_user_verified" value="1">
                            <label class="form-check-label fw-bold small" for="modal_user_verified">
                                <?= __('email_verified') ?? 'Email Address Verified' ?>
                            </label>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Password Recovery Action Box -->
                    <div class="p-3 border rounded bg-light d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-key-fill text-warning"></i> <?= __('password_recovery_title') ?? 'Password Recovery' ?>
                            </h6>
                            <p class="text-muted small mb-0">
                                <?= __('password_recovery_desc') ?? 'Send a secure reset link to the registered email so the user can define a new password.' ?>
                            </p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-danger btn-sm fw-bold d-inline-flex align-items-center gap-1" id="btnSendPasswordReset">
                                <i class="bi bi-envelope-arrow-up"></i> <?= __('send_reset_link') ?? 'Send Reset Link' ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal"><?= __('cancel') ?? 'Cancel' ?></button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-floppy me-1"></i> <?= __('save_changes') ?? 'Save Changes' ?>
                    </button>
                </div>
            </form>

            <!-- Hidden form used to submit reset email request -->
            <form action="/admin/user/send-reset" method="POST" id="sendResetPasswordForm" style="display:none;">
                <input type="hidden" name="user_id" id="reset_user_id">
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<!-- Manage User Modal Script -->
<script>
function openUserManageModal(user, currentAdminId) {
    document.getElementById('modal_user_id').value = user.id;
    document.getElementById('reset_user_id').value = user.id;
    document.getElementById('modal_user_email').value = user.email || '';
    document.getElementById('modal_user_created_at').value = user.created_at || 'N/A';
    document.getElementById('modal_user_role').value = user.role || 'user';
    document.getElementById('modal_user_status').value = user.status || 'active';
    document.getElementById('modal_user_plan_id').value = user.plan_id || 1;
    document.getElementById('modal_user_verified').checked = parseInt(user.email_verified || 0) === 1;

    var roleSelect = document.getElementById('modal_user_role');
    var statusSelect = document.getElementById('modal_user_status');
    var roleHint = document.getElementById('role_protected_hint');

    // Protect current logged-in admin from self-demotion or self-ban
    if (parseInt(user.id) === parseInt(currentAdminId)) {
        roleSelect.disabled = true;
        statusSelect.disabled = true;
        roleHint.style.display = 'block';
    } else {
        roleSelect.disabled = false;
        statusSelect.disabled = false;
        roleHint.style.display = 'none';
    }

    var modal = new bootstrap.Modal(document.getElementById('manageUserModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    var btnReset = document.getElementById('btnSendPasswordReset');
    if (btnReset) {
        btnReset.addEventListener('click', function() {
            var email = document.getElementById('modal_user_email').value;
            if (confirm('Send password reset email to: ' + email + '?')) {
                document.getElementById('sendResetPasswordForm').submit();
            }
        });
    }
});
</script>
