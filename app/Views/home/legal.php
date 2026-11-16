<?php
// app/Views/home/legal.php

require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../Models/SystemSetting.php';

$customLogo = SystemSetting::get('app_logo');
$appLogoUrl = !empty($customLogo) ? $customLogo : '/public/assets/images/mysocialmanager_logo.png';
?>

<style>
    .legal-content h1,
    .legal-content h2,
    .legal-content h3,
    .legal-content h4 {
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        font-weight: 700;
    }
    .legal-content h1:first-child,
    .legal-content h2:first-child {
        margin-top: 0;
    }
    .legal-content p {
        margin-bottom: 1rem;
        line-height: 1.7;
    }
    .legal-content ul,
    .legal-content ol {
        margin-bottom: 1rem;
        padding-left: 1.5rem;
    }
    .legal-content li {
        margin-bottom: 0.4rem;
        line-height: 1.6;
    }
</style>

<nav class="navbar navbar-expand-lg shadow-sm" style="background-color:#DAA520;">
    <div class="container">
        <a class="navbar-brand p-0" href="/">
            <img style="width:200px; height:50px; object-fit: contain;" src="<?= htmlspecialchars($appLogoUrl) ?>" alt="Logo" />
        </a>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="/" class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> <?= __('back_to_home') ?: 'Back to Home' ?>
            </a>
            <?php if (!empty($isLoggedIn)): ?>
                <a href="/dashboard" class="btn btn-dark btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-speedometer2"></i> <?= __('go_to_dashboard') ?: 'Go to Dashboard' ?>
                </a>
            <?php else: ?>
                <a href="/login" class="btn btn-dark btn-sm"><?= __('login') ?: 'Login' ?></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-body-tertiary py-3 px-4 border-bottom d-flex align-items-center gap-2">
                    <i class="bi <?= htmlspecialchars($pageIcon ?? 'bi-file-earmark-text') ?> text-primary fs-4"></i>
                    <h1 class="h3 mb-0 fw-bold"><?= htmlspecialchars($pageTitle ?? 'Legal Information') ?></h1>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($isNotAvailable)): ?>
                        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?= __('legal_page_unavailable') ?: 'This legal document is currently not available or has been disabled by the administrator.' ?></div>
                        </div>
                        <a href="/" class="btn btn-primary">
                            <i class="bi bi-house me-1"></i> <?= __('back_to_home') ?: 'Back to Home' ?>
                        </a>
                    <?php elseif (trim((string)($legalContent ?? '')) === ''): ?>
                        <p class="text-muted mb-0">
                            <?= __('legal_content_empty') ?: 'No content has been published for this page yet.' ?>
                        </p>
                    <?php else: ?>
                        <div class="legal-content">
                            <?php
                                $raw = (string)$legalContent;
                                // Check whether the administrator entered HTML markup or plain text
                                if (strip_tags($raw) !== $raw) {
                                    // Defense-in-depth: strip executable tags and inline event handlers
                                    $safeHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw);
                                    $safeHtml = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $safeHtml);
                                    $safeHtml = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safeHtml);
                                    $safeHtml = preg_replace('/javascript\s*:/i', '', $safeHtml);
                                    echo $safeHtml;
                                } else {
                                    echo '<p>' . nl2br(htmlspecialchars($raw)) . '</p>';
                                }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
