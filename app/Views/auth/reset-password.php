<?php
// app/Views/auth/reset-password.php
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h3 class="text-center mb-3 fw-bold">Reset Password</h3>
                    <p class="text-muted small text-center mb-4">Enter a new secure password for your account.</p>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger small"><?= htmlspecialchars($_SESSION['error']) ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form action="/reset-password" method="POST">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-bold">New Password</label>
                            <input type="password" name="password" id="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label small fw-bold">Confirm New Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat your new password">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Update Password</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/login" class="small text-decoration-none">← Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>