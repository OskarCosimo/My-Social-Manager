<?php
// app/Views/mailing_lists/confirm.php
require_once __DIR__ . '/../partials/header.php';
?>

<div class="container py-5" style="max-width: 500px;">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body p-4">
            <h3 class="fw-bold mb-3">📧 Subscription Status</h3>
            <p class="fs-5"><?= htmlspecialchars($message) ?></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>