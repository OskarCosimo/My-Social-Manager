<?php
// app/Views/mailing_lists/index.php
if (!isset($_SESSION['user_id'])) exit;

/**
 * Helper function identical to Dashboard
 */
function getProgressPercent(int $used, int $max): int {
    if ($max <= 0) return 100;
    return (int)min(100, round(($used / $max) * 100));
}

$currentListCount = count($lists ?? []);
$maxLists         = $limits['max_mailing_lists'] ?? 0;
$isListLimitReached = ($maxLists > 0 && $currentListCount >= $maxLists);

$emailsSent     = $limits['emails_sent_month'] ?? 0;
$maxEmails      = $limits['max_emails_per_month'] ?? 0;
$emailsPercent  = getProgressPercent($emailsSent, $maxEmails);

require_once __DIR__ . '/../partials/header.php';
$currentUser = User::findById((int)$_SESSION['user_id']);
?>

<div class="d-flex main-wrapper bg-light">
    <?php 
    $activeMenu = 'mailinglists'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        
        <!-- Header & Info Badge -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-envelope-paper text-danger"></i> <?= __('mailing_lists') ?>
            </h1>
            
            <div class="shadow-sm border rounded bg-white px-3 py-2 fs-6 fw-semibold d-inline-flex align-items-center gap-2 flex-wrap">
                <span>
                    <i class="bi bi-tag-fill text-muted me-1"></i>
                    <strong><?= str_replace('{name}', htmlspecialchars($limits['plan_name'] ?? 'Free'), __('plan_badge_name')) ?></strong>
                </span>
                <span class="text-primary border-start ps-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-card-list"></i>
                    <strong><?= str_replace(['{current}', '{max}'], [$currentListCount, $maxLists], __('mailing_lists_ratio')) ?></strong>
                </span>
                <span class="text-secondary border-start ps-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-people"></i>
                    <strong><?= str_replace('{count}', number_format($limits['max_subscribers_per_list'] ?? 0), __('max_subs_per_list_ratio')) ?></strong>
                </span>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Monthly Emails Sent Progress Bar -->
        <div class="card stat-card shadow-sm border-0 mb-4 position-relative overflow-hidden">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase fw-bold text-muted mb-0"><?= __('monthly_emails_sent_title') ?></h6>
                    <div class="p-2 rounded d-inline-flex align-items-center justify-content-center" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1; width: 38px; height: 38px;">
                        <i class="bi bi-send fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-end mb-2">
                    <h2 class="display-6 fw-bold mb-0"><?= number_format($emailsSent) ?></h2>
                    <span class="text-muted ms-2">/ <?= number_format($maxEmails) ?></span>
                </div>
                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                    <div class="progress-bar" role="progressbar" style="width: <?= $emailsPercent ?>%; background-color: #6f42c1;"></div>
                </div>
                <small class="text-muted">
                    <?= str_replace('{percent}', $emailsPercent, __('monthly_email_limit_used')) ?>
                </small>
            </div>
            <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #6f42c1;"></div>
        </div>

        <?php if ($isListLimitReached): ?>
            <div class="alert alert-warning mb-4 border-warning shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <?= str_replace(['{current}', '{max}', '{plan}'], [$currentListCount, $maxLists, htmlspecialchars($limits['plan_name'] ?? 'Free')], __('list_limit_reached_alert')) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create New List Form Card -->
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold text-dark mb-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle text-primary"></i> <?= __('create_new_list') ?>
                </h5>
                <form action="/mailinglists/store" method="POST" id="createListForm">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><?= __('list_name_field') ?></label>
                            <input type="text" name="name" class="form-control" placeholder="<?= __('list_name_placeholder') ?>" required <?= $isListLimitReached ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-md-4">
                                <input class="form-check-input" type="checkbox" name="require_confirmation" value="1" id="requireConfirmation" <?= $isListLimitReached ? 'disabled' : '' ?>>
                                <label class="form-check-label small text-dark" for="requireConfirmation">
                                    <?= __('require_double_optin') ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Options Setup -->
                    <div class="border p-3 rounded bg-light mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" value="1" onchange="togglePaymentFields();" <?= $isListLimitReached ? 'disabled' : '' ?>>
                            <label class="form-check-label fw-bold text-dark" for="is_paid"><?= __('require_paid_access') ?></label>
                        </div>

                        <div class="row g-3 mt-1" id="paid_options" style="display: none;">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold"><?= __('gateway_field') ?></label>
                                <select name="payment_gateway" id="payment_gateway" class="form-select form-select-sm" onchange="togglePaymentFields();">
                                    <option value="stripe">Stripe</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold"><?= __('payment_type_field') ?></label>
                                <select name="payment_type" class="form-select form-select-sm">
                                    <option value="one_time"><?= __('one_time_fee') ?></option>
                                    <option value="subscription"><?= __('recurring_subscription') ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold"><?= __('period_field') ?></label>
                                <select name="billing_period" class="form-select form-select-sm">
                                    <option value="monthly"><?= __('monthly') ?></option>
                                    <option value="yearly"><?= __('yearly') ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold"><?= __('price_field') ?></label>
                                <input type="number" step="0.01" min="0" name="price" class="form-control form-control-sm" value="5.00">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold"><?= __('currency_field') ?></label>
                                <select name="currency" class="form-select form-select-sm">
                                    <option value="EUR">EUR (€)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>

                            <!-- Stripe Account ID -->
                            <div class="col-md-12 mt-2" id="stripe_cred">
                                <label class="form-label small fw-bold text-danger"><?= __('stripe_account_id_field') ?></label>
                                <input type="text" name="creator_stripe_account_id" id="creator_stripe_account_id" class="form-control form-control-sm" placeholder="e.g. acct_1NXXXXXXXXXXXXXX" value="<?= htmlspecialchars($currentUser['creator_stripe_account_id'] ?? '') ?>">
                                <div class="form-text small"><?= __('stripe_account_id_help') ?></div>
                            </div>

                            <!-- PayPal Payout Email -->
                            <div class="col-md-12 mt-2" id="paypal_cred" style="display: none;">
                                <label class="form-label small fw-bold text-danger"><?= __('paypal_payout_email_field') ?></label>
                                <input type="email" name="creator_paypal_email" id="creator_paypal_email" class="form-control form-control-sm" placeholder="e.g. creator@example.com" value="<?= htmlspecialchars($currentUser['creator_paypal_email'] ?? '') ?>">
                                <div class="form-text small"><?= __('paypal_payout_email_help') ?></div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1" <?= $isListLimitReached ? 'disabled' : '' ?>>
                        <i class="bi bi-plus-circle"></i> <?= __('add_list_btn') ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Mailing Lists Table -->
        <div class="card shadow-sm border-0 bg-white mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= __('name_header') ?></th>
                                <th><?= __('access_model_header') ?></th>
                                <th><?= __('public_link_header') ?></th>
                                <th><?= __('created_at_header') ?></th>
                                <th class="text-end"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lists)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4"><?= __('no_mailing_lists_yet') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lists as $list): 
                                    $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/subscribe/" . $list['public_hash'];
                                ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <a href="/mailinglists/<?= $list['id'] ?>/subscribers" class="text-decoration-none">
                                                <?= htmlspecialchars($list['name']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($list['is_paid'])): ?>
                                                <span class="badge bg-success d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-credit-card"></i> 
                                                    <?= number_format($list['price'], 2) ?> <?= $list['currency'] ?> 
                                                    (<?= $list['payment_type'] === 'subscription' ? ucfirst($list['billing_period']) : __('one_time_fee') ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= __('status_free') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 350px;">
                                                <input type="text" class="form-control" value="<?= $publicUrl ?>" readonly id="link_<?= $list['id'] ?>">
                                                <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" type="button" onclick="navigator.clipboard.writeText(document.getElementById('link_<?= $list['id'] ?>').value); alert('<?= addslashes(__('link_copied_alert')) ?>');">
                                                    <i class="bi bi-clipboard"></i> <?= __('copy_link') ?>
                                                </button>
                                            </div>
                                        </td>
                                        <td><?= $list['created_at'] ?></td>
                                        <td class="text-end">
                                            <a href="/mailinglists/<?= $list['id'] ?>/subscribers" class="btn btn-sm btn-outline-primary me-1 d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-people"></i> <?= __('view_subscribers') ?>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editListModal_<?= $list['id'] ?>">
                                                <i class="bi bi-pencil"></i> <?= __('edit') ?>
                                            </button>
                                            <form action="/mailinglist/delete" method="POST" class="d-inline" onsubmit="return confirm('<?= addslashes(__('confirm_delete_list')) ?>');">
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

        <!-- EDIT MODALS (PLACED OUTSIDE TABLE FOR VALID HTML DOM) -->
        <?php if (!empty($lists)): ?>
            <?php foreach ($lists as $list): ?>
                <div class="modal fade" id="editListModal_<?= $list['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg text-start">
                        <div class="modal-content">
                            <form action="/mailinglist/update" method="POST" id="editListForm_<?= $list['id'] ?>">
                                <input type="hidden" name="list_id" value="<?= (int)$list['id'] ?>">

                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                                        <i class="bi bi-pencil-square text-primary"></i> <?= __('edit') ?>: <?= htmlspecialchars($list['name']) ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body p-4">
                                    <div class="row g-3 align-items-end mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-dark"><?= __('list_name_field') ?></label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($list['name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check mt-md-4">
                                                <input class="form-check-input" type="checkbox" name="require_confirmation" value="1" id="edit_req_confirm_<?= $list['id'] ?>" <?= !empty($list['require_confirmation']) ? 'checked' : '' ?>>
                                                <label class="form-check-label small text-dark" for="edit_req_confirm_<?= $list['id'] ?>">
                                                    <?= __('require_double_optin') ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Payment Options Setup -->
                                    <div class="border p-3 rounded bg-light mb-3">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="edit_is_paid_<?= $list['id'] ?>" name="is_paid" value="1" onchange="toggleEditPaymentFields(<?= $list['id'] ?>);" <?= !empty($list['is_paid']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold text-dark" for="edit_is_paid_<?= $list['id'] ?>"><?= __('require_paid_access') ?></label>
                                        </div>

                                        <div class="row g-3 mt-1" id="edit_paid_options_<?= $list['id'] ?>" style="<?= !empty($list['is_paid']) ? 'display: flex;' : 'display: none;' ?>">
                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold"><?= __('gateway_field') ?></label>
                                                <select name="payment_gateway" id="edit_payment_gateway_<?= $list['id'] ?>" class="form-select form-select-sm" onchange="toggleEditPaymentFields(<?= $list['id'] ?>);">
                                                    <option value="stripe" <?= ($list['payment_gateway'] === 'stripe') ? 'selected' : '' ?>>Stripe</option>
                                                    <option value="paypal" <?= ($list['payment_gateway'] === 'paypal') ? 'selected' : '' ?>>PayPal</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold"><?= __('payment_type_field') ?></label>
                                                <select name="payment_type" class="form-select form-select-sm">
                                                    <option value="one_time" <?= ($list['payment_type'] === 'one_time') ? 'selected' : '' ?>><?= __('one_time_fee') ?></option>
                                                    <option value="subscription" <?= ($list['payment_type'] === 'subscription') ? 'selected' : '' ?>><?= __('recurring_subscription') ?></option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold"><?= __('period_field') ?></label>
                                                <select name="billing_period" class="form-select form-select-sm">
                                                    <option value="monthly" <?= ($list['billing_period'] === 'monthly') ? 'selected' : '' ?>><?= __('monthly') ?></option>
                                                    <option value="yearly" <?= ($list['billing_period'] === 'yearly') ? 'selected' : '' ?>><?= __('yearly') ?></option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold"><?= __('price_field') ?></label>
                                                <input type="number" step="0.01" min="0" name="price" class="form-control form-control-sm" value="<?= (!empty($list['price']) && (float)$list['price'] > 0) ? number_format((float)$list['price'], 2, '.', '') : '5.00' ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold"><?= __('currency_field') ?></label>
                                                <select name="currency" class="form-select form-select-sm">
                                                    <option value="EUR" <?= ($list['currency'] === 'EUR') ? 'selected' : '' ?>>EUR (€)</option>
                                                    <option value="USD" <?= ($list['currency'] === 'USD') ? 'selected' : '' ?>>USD ($)</option>
                                                </select>
                                            </div>

                                            <!-- Stripe Account ID -->
                                            <div class="col-md-12 mt-2" id="edit_stripe_cred_<?= $list['id'] ?>" style="<?= ($list['payment_gateway'] === 'stripe') ? 'display: block;' : 'display: none;' ?>">
                                                <label class="form-label small fw-bold text-danger"><?= __('stripe_account_id_field') ?></label>
                                                <input type="text" name="creator_stripe_account_id" id="edit_creator_stripe_account_id_<?= $list['id'] ?>" class="form-control form-control-sm" placeholder="e.g. acct_1NXXXXXXXXXXXXXX" value="<?= htmlspecialchars($currentUser['creator_stripe_account_id'] ?? '') ?>">
                                                <div class="form-text small"><?= __('stripe_account_id_help') ?></div>
                                            </div>

                                            <!-- PayPal Payout Email -->
                                            <div class="col-md-12 mt-2" id="edit_paypal_cred_<?= $list['id'] ?>" style="<?= ($list['payment_gateway'] === 'paypal') ? 'display: block;' : 'display: none;' ?>">
                                                <label class="form-label small fw-bold text-danger"><?= __('paypal_payout_email_field') ?></label>
                                                <input type="email" name="creator_paypal_email" id="edit_creator_paypal_email_<?= $list['id'] ?>" class="form-control form-control-sm" placeholder="e.g. creator@example.com" value="<?= htmlspecialchars($currentUser['creator_paypal_email'] ?? '') ?>">
                                                <div class="form-text small"><?= __('paypal_payout_email_help') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                                    <button type="submit" form="editListForm_<?= $list['id'] ?>" class="btn btn-primary fw-bold"><?= __('save_changes') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

<script>
    function togglePaymentFields() {
        const isPaid = document.getElementById('is_paid').checked;
        const gateway = document.getElementById('payment_gateway').value;
        
        const paidOptions = document.getElementById('paid_options');
        const stripeCred = document.getElementById('stripe_cred');
        const paypalCred = document.getElementById('paypal_cred');

        if (isPaid) {
            paidOptions.style.display = 'flex';
            if (gateway === 'stripe') {
                stripeCred.style.display = 'block';
                paypalCred.style.display = 'none';
            } else {
                stripeCred.style.display = 'none';
                paypalCred.style.display = 'block';
            }
        } else {
            paidOptions.style.display = 'none';
        }
    }

    function toggleEditPaymentFields(listId) {
        const isPaid = document.getElementById('edit_is_paid_' + listId).checked;
        const gateway = document.getElementById('edit_payment_gateway_' + listId).value;
        
        const paidOptions = document.getElementById('edit_paid_options_' + listId);
        const stripeCred = document.getElementById('edit_stripe_cred_' + listId);
        const paypalCred = document.getElementById('edit_paypal_cred_' + listId);

        if (isPaid) {
            paidOptions.style.display = 'flex';
            if (gateway === 'stripe') {
                stripeCred.style.display = 'block';
                paypalCred.style.display = 'none';
            } else {
                stripeCred.style.display = 'none';
                paypalCred.style.display = 'block';
            }
        } else {
            paidOptions.style.display = 'none';
        }
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
