<?php
// app/Views/newsletter/index.php
if (!isset($_SESSION['user_id'])) exit;
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<!-- Main flex wrapper to align sidebar and content side-by-side -->
<div class="d-flex main-wrapper bg-light">
    
    <!-- Include the sidebar and set the active menu item -->
    <?php 
    $activeMenu = 'newsletter'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <!-- Main content area taking up the remaining full width -->
    <main class="content-area w-100 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 fw-bold text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-send text-primary"></i> <?= __('send_newsletter') ?>
            </h1>
        </div>

        <!-- Display success message if any -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Display error message if any -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-4">
                <form action="/newsletter/send" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark"><?= __('email_subject') ?></label>
                        <input type="text" name="subject" class="form-control form-control-lg shadow-sm border-secondary-subtle" placeholder="<?= __('email_subject_placeholder') ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark"><?= __('email_body_html') ?></label>
                        <textarea id="my-wysiwyg-editor" name="body" class="form-control" rows="15" placeholder="<?= __('email_body_placeholder') ?>" required></textarea>
                        <div class="form-text mt-2 text-muted small">
                            <i class="bi bi-info-circle me-1"></i><?= __('newsletter_recipients_note') ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow d-inline-flex align-items-center gap-2" onclick="return confirm('<?= addslashes(__('confirm_broadcast_newsletter')) ?>');">
                        <i class="bi bi-broadcast"></i> <?= __('broadcast_newsletter_btn') ?>
                    </button>
                </form>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>