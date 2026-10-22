<?php
// app/Views/settings/index.php
if (!isset($_SESSION['user_id'])) exit;
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<div class="d-flex main-wrapper bg-light">
    
    <?php 
    $activeMenu = 'settings'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        <h1 class="h2 mb-4 fw-bold text-dark d-inline-flex align-items-center gap-2">
            <i class="bi bi-gear-fill text-primary"></i> <?= __('settings') ?>
        </h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Preferences Card -->
        <div class="card shadow-sm border-0 mb-4 bg-white" style="max-width: 540px;">
            <div class="card-body p-4">
                <form action="/settings/save" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                            <i class="bi bi-translate"></i> <?= __('interface_language') ?>
                        </label>
                        <select name="language" class="form-select">
                            <?php foreach ($availableLanguages as $lang): ?>
                                <option value="<?= $lang ?>" <?= $currentLang === $lang ? 'selected' : '' ?>>
                                    <?= strtoupper($lang) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                            <i class="bi bi-globe"></i> <?= __('timezone') ?>
                        </label>
                        <select name="timezone" class="form-select">
                            <?php 
                            $userTz = $_SESSION['user_timezone'] ?? ($user['timezone'] ?? 'UTC');
                            $timezones = timezone_identifiers_list();
                            foreach ($timezones as $tz): 
                                $selected = ($tz === $userTz) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($tz) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($tz) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i><?= __('timezone_help') ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-floppy"></i> <?= __('save_preferences') ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Account & Security (Password) Card -->
        <div class="card shadow-sm border-0 mb-4 bg-white" style="max-width: 540px;">
            <div class="card-header bg-white fw-bold border-bottom-0 pt-4 pb-0 d-inline-flex align-items-center gap-2">
                <i class="bi bi-shield-lock text-primary"></i> <?= __('account_security') ?>
            </div>
            <div class="card-body p-4">
                <form action="/settings/password" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?= __('current_password') ?></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?= __('new_password') ?></label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1">
                        <i class="bi bi-key"></i> <?= __('change_password') ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Two-Factor Authentication (2FA) Card -->
        <div class="card border-0 shadow-sm bg-white" style="max-width: 540px;">
            <div class="card-body p-4">
                <h4 class="fw-bold fs-5 mb-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-success"></i> <?= __('two_factor_authentication') ?>
                </h4>

                <?php if (!empty($user['two_factor_enabled'])): ?>
                    <p class="text-success fw-semibold d-inline-flex align-items-center gap-1 mb-3">
                        <i class="bi bi-check-circle-fill"></i> <?= __('status_label') ?> <strong><?= __('enabled') ?></strong>
                    </p>
                    <form action="/auth/2fa/toggle" method="POST" class="m-0">
                        <input type="hidden" name="action" value="disable">
                        <button type="submit" class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-shield-x"></i> <?= __('disable_2fa') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-muted mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-circle text-muted"></i> <?= __('status_label') ?> <strong><?= __('disabled') ?></strong>
                    </p>
                    
                    <?php
                    require_once __DIR__ . '/../../Core/TwoFactor.php';
                    
                    if (empty($_SESSION['temp_2fa_secret'])) {
                        $_SESSION['temp_2fa_secret'] = TwoFactor::generateSecret();
                    }
                    $secret = $_SESSION['temp_2fa_secret'];
                    
                    $userEmail = !empty($user['email']) ? $user['email'] : ($_SESSION['user_email'] ?? 'user@mysocialmanager.com');
                    $qrAuthUri = "otpauth://totp/MySocialManager:" . urlencode($userEmail) . "?secret=" . $secret . "&issuer=MySocialManager";
                    ?>
                    
                    <div class="mb-3 bg-light p-3 rounded border">
                        <p class="small mb-2 text-dark fw-semibold"><?= __('scan_qr_code_instruction') ?></p>
                        
                        <!-- Guaranteed minimum dimensions container for the Canvas/Table generated by qrcode.js -->
                        <div class="d-flex justify-content-center align-items-center mb-2 bg-white p-3 border rounded shadow-sm" style="min-width: 180px; min-height: 180px; margin: 0 auto;">
                            <div id="qrcode-container"></div>
                        </div>
                        
                        <p class="small mb-0 text-center text-muted">
                            <?= __('secret_key') ?>: <code class="fs-6 text-dark"><?= htmlspecialchars($secret) ?></code>
                        </p>
                    </div>
                    
                    <form action="/auth/2fa/toggle" method="POST">
                        <input type="hidden" name="action" value="enable">
                        <div class="input-group">
                            <input type="text" name="code" class="form-control" placeholder="<?= __('enter_6_digit_code') ?>" required autocomplete="off" maxlength="6">
                            <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-1">
                                <i class="bi bi-shield-check"></i> <?= __('activate_2fa') ?>
                            </button>
                        </div>
                    </form>

                    <!-- qrcode.js library from CDNJS with integrity -->
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
                    
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var qrContainer = document.getElementById("qrcode-container");
                            if (qrContainer) {
                                if (typeof QRCode !== 'undefined') {
                                    try {
                                        // json_encode prevents any JS syntax errors
                                        new QRCode(qrContainer, {
                                            text: <?= json_encode($qrAuthUri) ?>,
                                            width: 160,
                                            height: 160,
                                            colorDark : "#000000",
                                            colorLight : "#ffffff",
                                            correctLevel : QRCode.CorrectLevel.M
                                        });
                                    } catch (err) {
                                        console.error("Error creating QR code:", err);
                                    }
                                } else {
                                    console.error("QRCode library failed to load properly.");
                                }
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>