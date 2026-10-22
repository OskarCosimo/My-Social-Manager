<?php
// app/Views/auth/register.php
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<?php 
// Retrieve system settings directly if not passed by the controller
require_once __DIR__ . '/../../Models/SystemSetting.php';
$settings = $settings ?? \SystemSetting::getAll();

// Check if Turnstile is enabled from the admin panel and keys are available
$turnstileEnabled = ($settings['enable_turnstile'] ?? '0') === '1';
$turnstileSiteKey = $settings['turnstile_site_key'] ?? '';
?>

<?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4">Create an Account</h2>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <form action="/register" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>

                            <?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
    <div class="cf-turnstile mb-3" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
<?php endif; ?>

                            <button type="submit" class="btn btn-primary w-100 py-2">Register</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p class="text-muted mb-0">Already have an account? <a href="/login">Log in</a></p>
                            <a href="/" class="text-decoration-none small">← Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>