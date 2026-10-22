<?php
// app/Views/mailing_lists/subscribers.php
if (!isset($_SESSION['user_id'])) exit;
require_once __DIR__ . '/../partials/header.php';
?>

<div class="d-flex main-wrapper bg-light">
    <?php 
    $activeMenu = 'mailinglists'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="/mailinglists" class="text-decoration-none small d-inline-flex align-items-center gap-1 mb-1">
                    <i class="bi bi-arrow-left"></i> <?= __('back_to_lists') ?>
                </a>
                <h1 class="h2 m-0 fw-bold text-dark d-inline-flex align-items-center gap-2">
                    <i class="bi bi-people text-primary"></i> <?= __('subscribers_of') ?>: <?= htmlspecialchars($list['name']) ?>
                </h1>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= __('table_email') ?></th>
                                <th><?= __('payment_status_header') ?></th>
                                <th><?= __('sending_status_header') ?></th>
                                <th><?= __('expiration_date_header') ?></th>
                                <th class="text-end"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscribers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4"><?= __('no_subscribers_found') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscribers as $sub): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($sub['email']) ?></td>
                                        <td>
                                            <?php if ($sub['payment_status'] === 'paid'): ?>
                                                <span class="badge bg-success d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-check-circle-fill"></i> <?= __('status_paid') ?>
                                                </span>
                                            <?php elseif ($sub['payment_status'] === 'expired'): ?>
                                                <span class="badge bg-danger d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-x-circle-fill"></i> <?= __('status_expired') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= __('status_free') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['is_excluded'] == 1): ?>
                                                <span class="badge bg-danger d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-slash-circle"></i> <?= __('status_excluded') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-check2"></i> <?= __('status_active') ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $sub['expires_at'] ? htmlspecialchars($sub['expires_at']) : 'N/A' ?></td>
                                        <td class="text-end">
                                            <form action="/mailinglists/subscribers/toggle-exclusion" method="POST" class="d-inline me-1">
                                                <input type="hidden" name="subscriber_id" value="<?= $sub['id'] ?>">
                                                <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                                                <?php if ($sub['is_excluded'] == 1): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-check-circle"></i> <?= __('include_btn') ?>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-slash-circle"></i> <?= __('exclude_btn') ?>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <form action="/mailinglists/subscribers/delete" method="POST" class="d-inline" onsubmit="return confirm('<?= addslashes(__('confirm_remove_subscriber')) ?>');">
                                                <input type="hidden" name="subscriber_id" value="<?= $sub['id'] ?>">
                                                <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-trash3"></i> <?= __('delete') ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>