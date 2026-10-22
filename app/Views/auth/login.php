<?php require_once __DIR__ . '/../partials/header.php';
// app/Views/auth/login.php

// Check if Turnstile is enabled from the admin panel and keys are available
$turnstileEnabled = ($settings['enable_turnstile'] ?? '0') === '1';
$turnstileSiteKey = $settings['turnstile_site_key'] ?? '';
?>

<?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4">Welcome Back</h2>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>

                        <form action="/login" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" name="remember_me" id="remember_me" value="1">
                                <label class="form-check-label" for="remember_me">Remember me (6 months)</label>
                            </div>

                            <?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
                                <div class="cf-turnstile mb-3" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Sign In</button>
                        </form>

                        <?php 
                        $hasGoogle = ($settings['enable_social_google'] ?? '0') === '1';
                        $hasFacebook = ($settings['enable_social_facebook'] ?? '0') === '1';
                        $hasMicrosoft = ($settings['enable_social_microsoft'] ?? '0') === '1';
                        $hasMyetv = ($settings['enable_social_myetv'] ?? '0') === '1';
                        ?>

                        <?php if ($hasGoogle || $hasFacebook || $hasMicrosoft || $hasMyetv): ?>
                            <div class="text-center my-3">
                                <span class="text-muted small">Or sign in with</span>
                            </div>
                            <div class="d-grid gap-2">
                                <?php if ($hasMyetv): ?>
                                    <button type="button" onclick="submitSocialAuth('myetv')" class="btn text-white" style="background-color: #ff6600;">Sign in with MYETV</button>
                                <?php endif; ?>
                                <?php if ($hasGoogle): ?>
                                    <button type="button" onclick="submitSocialAuth('google')" class="btn btn-outline-danger">Sign in with Google</button>
                                <?php endif; ?>
                                <?php if ($hasFacebook): ?>
                                    <button type="button" onclick="submitSocialAuth('facebook')" class="btn btn-outline-primary">Sign in with Facebook</button>
                                <?php endif; ?>
                                <?php if ($hasMicrosoft): ?>
                                    <button type="button" onclick="submitSocialAuth('microsoft')" class="btn btn-outline-dark">Sign in with Microsoft</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Don't have an account? <a href="/register">Register here</a></p>
                            <a href="/" class="text-decoration-none small">← Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
/**
 * Dynamically creates and submits a POST form for Social OAuth Authentication.
 * Ensures Cloudflare Turnstile token and the Remember Me state are preserved.
 *
 * @param {string} provider - The chosen OAuth provider (e.g., 'google', 'facebook', 'myetv')
 */
function submitSocialAuth(provider) {
    // Initialize a dynamic form
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/auth/social';
    
    // Create a hidden input for the OAuth provider parameter
    const providerInput = document.createElement('input');
    providerInput.type = 'hidden';
    providerInput.name = 'provider';
    providerInput.value = provider;
    form.appendChild(providerInput);

    // Forward the Remember Me checkbox selection if checked
    const rememberMeCheckbox = document.getElementById('remember_me');
    if (rememberMeCheckbox && rememberMeCheckbox.checked) {
        const rememberInput = document.createElement('input');
        rememberInput.type = 'hidden';
        rememberInput.name = 'remember_me';
        rememberInput.value = '1';
        form.appendChild(rememberInput);
    }

    // Capture the generated Turnstile token from the main form (if it exists on the page)
    const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
    if (turnstileResponse) {
        const tsInput = document.createElement('input');
        tsInput.type = 'hidden';
        tsInput.name = 'cf-turnstile-response';
        tsInput.value = turnstileResponse.value;
        form.appendChild(tsInput);
    }

    // Append the form to the document body and execute the POST request
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
