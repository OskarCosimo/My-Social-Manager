<?php
// app/Views/auth/2fa-verify.php
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <h3 class="mb-3">Two-Factor Verification</h3>
                    <p class="text-muted small">Enter the 6-digit code from your authenticator app.</p>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger small"><?= htmlspecialchars($_SESSION['error']) ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form action="/auth/2fa" method="POST">
                        <div class="mb-3">
                            <input type="text" name="code" class="form-control text-center fs-4" placeholder="000000" maxlength="6" required autofocus autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Verify Code</button>
                    </form>
                    <div class="mt-3">
                        <a href="/logout" class="small text-muted">Cancel and Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>