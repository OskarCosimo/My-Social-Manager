<?php
// app/Views/mailing_lists/subscribe.php
require_once __DIR__ . '/../partials/header.php';

if (file_exists(__DIR__ . '/../../Models/SystemSetting.php')) {
    require_once __DIR__ . '/../../Models/SystemSetting.php';
}

if (class_exists('SystemSetting')) {
    $settings = $settings ?? SystemSetting::getAll();
} elseif (class_exists('App\Models\SystemSetting')) {
    $settings = $settings ?? \App\Models\SystemSetting::getAll();
} else {
    $settings = $settings ?? [];
}

$turnstileEnabled = ($settings['enable_turnstile'] ?? '0') === '1';
$turnstileSiteKey = $settings['turnstile_site_key'] ?? '';
?>

<?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

<div class="container py-5" style="max-width: 450px;">
    <div class="card shadow-sm border-0 bg-white">
        <div class="card-body p-4 text-center">
            <h3 class="fw-bold mb-1 d-inline-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-envelope-paper text-primary"></i> <?= __('subscribe_title') ?>
            </h3>
            <p class="text-muted mb-3"><?= htmlspecialchars($list['name']) ?></p>

            <?php if (!empty($list['is_paid'])): ?>
                <div class="alert alert-info py-2 fw-bold mb-3 d-inline-flex align-items-center justify-content-center gap-1 w-100">
                    <i class="bi bi-credit-card"></i>
                    Price: <?= number_format($list['price'], 2) ?> <?= htmlspecialchars($list['currency']) ?>
                    <?= $list['payment_type'] === 'subscription' ? '/ ' . htmlspecialchars($list['billing_period']) : '(' . __('one_time_fee') . ')' ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success shadow-sm"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($message)): ?>
                <form action="" method="POST">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control form-control-lg" placeholder="<?= __('enter_email_address') ?>" required>
                    </div>

                    <?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
                        <div class="cf-turnstile mb-3 d-flex justify-content-center" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2">
                        <?php if (!empty($list['is_paid'])): ?>
                            <i class="bi bi-credit-card-2-front"></i> <?= __('proceed_to_payment') ?>
                        <?php else: ?>
                            <i class="bi bi-check2-circle"></i> <?= __('subscribe_now_btn') ?>
                        <?php endif; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>