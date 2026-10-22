<?php
// app/Views/accounts/manual_setup.php
if (!isset($_SESSION['user_id']) || !isset($platform) || !isset($pluginFormHtml)) {
    header('Location: /login');
    exit;
}

$platformName = htmlspecialchars(ucfirst($platform));
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<div class="d-flex main-wrapper">
    
    <?php 
    $activeMenu = 'accounts'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold d-inline-flex align-items-center gap-2">
                <i class="bi bi-link-45deg text-primary"></i> 
                <?= __('connect_platform_title', ['platform' => $platformName]) ?: ("Connect " . $platformName) ?>
            </h1>
            <a href="/accounts" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-arrow-left"></i> <?= __('back_to_accounts') ?? 'Back to Accounts' ?>
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_GET['forced_byok']) && $_GET['forced_byok'] == '1'): ?>
            <div class="alert alert-warning shadow-sm border-0 border-start border-warning border-4 mb-4" style="max-width: 600px;">
                <h5 class="alert-heading fw-bold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= __('custom_app_required_title') ?? 'Custom App Required' ?>
                </h5>
                <p class="mb-0 small">
                    <?= __('custom_app_required_desc') ?? 'The global system app for this network is currently not configured by the administrator. To proceed, you must link your own Developer App credentials below.' ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0" style="max-width: 600px;">
            <div class="card-body p-4">
                
                <form action="/plugin/callback?state=<?= htmlspecialchars($platform) ?>" method="POST">
                    
                    <?= $pluginFormHtml ?>

                    <button type="submit" class="btn btn-primary w-100 py-2 mt-4 fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-box-arrow-in-right"></i> 
                        <?= __('connect_platform_account_btn', ['platform' => $platformName]) ?: ("Connect " . $platformName . " Account") ?>
                    </button>
                </form>
                
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>