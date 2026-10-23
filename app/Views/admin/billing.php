<?php
// app/Views/admin/billing.php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') exit;

require_once __DIR__ . '/../../Models/Plan.php';
$plans = Plan::getAll();

$rawPacks = SystemSetting::get('ai_credit_packs');
$aiPacks = !empty($rawPacks) ? json_decode($rawPacks, true) : [
    ['credits' => 100,  'price' => 5.00],
    ['credits' => 500,  'price' => 20.00],
    ['credits' => 1000, 'price' => 35.00]
];
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<div class="d-flex main-wrapper">
    <?php 
        $activeMenu = 'admin'; 
        require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <div class="content-area w-100 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="m-0 fw-bold d-inline-flex align-items-center gap-2">
                <span>💳</span> <?= __('billing_and_plans_mgmt') ?? 'Billing & Plans Management' ?>
            </h2>
            <button type="button" class="btn btn-primary shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                <i class="bi bi-plus-circle"></i> <?= __('create_new_plan') ?? 'Create New Plan' ?>
            </button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- PLANS TABLE -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 fw-bold">
                <h5 class="mb-0 text-secondary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-card-checklist text-primary"></i> <?= __('existing_subscription_plans') ?? 'Existing Subscription Plans' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="plansTable" class="table table-striped table-hover align-middle dt-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th><?= __('table_id') ?? 'ID' ?></th>
                                <th><?= __('plan_name_header') ?? 'Plan Name' ?></th>
                                <th><?= __('group_code_header') ?? 'Group Code' ?></th>
                                <th><?= __('price_header') ?? 'Price' ?></th>
                                <th><?= __('cycle_header') ?? 'Cycle' ?></th>
                                <th><?= __('table_status') ?? 'Status' ?></th>
                                <th class="text-end"><?= __('actions') ?? 'Actions' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plans as $plan): ?>
                                <tr>
                                    <td><?= (int)$plan['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($plan['name']) ?></strong>
                                        <?php if (!empty($plan['is_default'])): ?>
                                            <span class="badge bg-success ms-1"><?= __('default_badge') ?? 'Default' ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($plan['is_hidden'])): ?>
                                            <span class="badge bg-secondary ms-1"><?= __('status_hidden') ?? 'Hidden' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($plan['plan_code'] ?? '-') ?></code></td>
                                    <td class="fw-bold">€<?= number_format($plan['price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars(__($plan['billing_cycle']) ?: ucfirst($plan['billing_cycle'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($plan['is_hidden'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= __('status_hidden') ?? 'Hidden' ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><?= __('status_visible') ?? 'Visible' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick='editPlan(<?= json_encode($plan) ?>)' data-bs-toggle="modal" data-bs-target="#editPlanModal" title="<?= __('edit') ?? 'Edit' ?>">
                                                <i class="bi bi-pencil"></i> <?= __('edit') ?? 'Edit' ?>
                                            </button>
                                            <form action="/admin/plans/delete" method="POST" class="d-inline m-0" onsubmit="return confirm('<?= addslashes(__('confirm_delete_plan') ?? 'Are you sure you want to delete this plan?') ?>');">
                                                <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= __('delete') ?? 'Delete' ?>">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AI CREDITS TOP-UP PACKAGES SECTION -->
        <div class="card mb-5 shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 fw-bold">
                <h5 class="mb-0 text-secondary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-stars text-primary"></i> <?= __('ai_credit_topup_packages') ?? 'AI Credit Top-Up Packages (One-Time Purchases)' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    <?= __('ai_credit_topup_desc') ?? 'Configure available credit packages that users can purchase using Stripe or PayPal to top up their account balance.' ?>
                </p>

                <form action="/admin/billing/ai-packs" method="POST">
                    <div id="ai-packs-container">
                        <?php foreach ($aiPacks as $idx => $pack): ?>
                            <div class="row g-3 align-items-end mb-3 pack-row">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold"><?= __('credits_amount') ?? 'AI Credits' ?></label>
                                    <input type="number" name="pack_credits[]" class="form-control" value="<?= (int)$pack['credits'] ?>" required min="10">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold"><?= __('price_eur') ?? 'Price (€)' ?></label>
                                    <input type="number" step="0.01" name="pack_prices[]" class="form-control" value="<?= number_format((float)$pack['price'], 2, '.', '') ?>" required min="0.50">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger w-100 remove-pack-btn">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-pack-btn">
                            <i class="bi bi-plus-circle me-1"></i> <?= __('add_package') ?? 'Add Package' ?>
                        </button>
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <?= __('save_packages') ?? 'Save Packages' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Modal Create Plan -->
<div class="modal fade" id="createPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="/admin/billing/plans/create" method="POST" class="modal-content text-start">
            <div class="modal-header">
                <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle text-primary"></i> <?= __('create_new_subscription_plan') ?? 'Create New Subscription Plan' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('close') ?? 'Close' ?>"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><?= __('plan_name_label') ?? 'Plan Name' ?></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Pro Plan">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('plan_group_code') ?? 'Plan Group Code' ?></label>
                        <input type="text" name="plan_code" class="form-control" placeholder="e.g. free, pro, ultra">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('stripe_price_id') ?? 'Stripe Price ID' ?></label>
                        <input type="text" name="stripe_price_id" class="form-control" placeholder="e.g. price_1Nxxx...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><?= __('price_currency') ?? 'Price (€)' ?></label>
                        <input type="number" step="0.01" name="price" class="form-control" required value="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('billing_cycle') ?? 'Billing Cycle' ?></label>
                        <select name="billing_cycle" class="form-select">
                            <option value="monthly"><?= __('monthly') ?? 'Monthly' ?></option>
                            <option value="yearly"><?= __('yearly') ?? 'Yearly' ?></option>
                            <option value="lifetime"><?= __('lifetime') ?? 'Lifetime' ?></option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_default" id="create_is_default" value="1">
                            <label class="form-check-label fw-bold" for="create_is_default">
                                🌟 <?= __('set_as_default') ?? 'Set as Default' ?>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_hidden" id="create_is_hidden" value="1">
                            <label class="form-check-label fw-bold" for="create_is_hidden">
                                🙈 <?= __('hide_from_public_views') ?? 'Hide from Public Views' ?>
                            </label>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">📌 <?= __('section_social_limits') ?? 'Social Posting & Feed Limits' ?></h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('max_social_accounts') ?? 'Max Social Accounts' ?></label>
                        <input type="number" name="max_accounts" class="form-control form-control-sm" value="5" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('max_posts') ?? 'Max Posts' ?></label>
                        <input type="number" name="max_posts" class="form-control form-control-sm" value="100" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold"><?= __('post_period') ?? 'Post Period' ?></label>
                        <select name="post_period" class="form-select form-select-sm">
                            <option value="daily"><?= __('daily') ?? 'Daily' ?></option>
                            <option value="monthly" selected><?= __('monthly') ?? 'Monthly' ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold"><?= __('max_rss_feeds') ?? 'Max RSS Feeds' ?></label>
                        <input type="number" name="max_rss_feeds" class="form-control form-control-sm" value="5" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold"><?= __('max_rss_posts') ?? 'Max RSS Posts' ?></label>
                        <input type="number" name="max_rss_posts" class="form-control form-control-sm" value="50" required>
                    </div>

                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold text-primary"><?= __('max_channel_automations') ?? 'Max Channel Automations' ?></label>
                        <input type="number" name="max_channel_automations" class="form-control form-control-sm" value="5" required>
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold text-primary"><?= __('max_channel_posts_label') ?? 'Max Channel Automation Posts' ?></label>
                        <input type="number" name="max_channel_posts" class="form-control form-control-sm" value="50" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">📧 <?= __('section_mailing_limits') ?? 'Mailing List Limits' ?></h6>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold"><?= __('max_mailing_lists') ?? 'Max Mailing Lists' ?></label>
                        <input type="number" name="max_mailing_lists" class="form-control form-control-sm" value="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold"><?= __('max_subscribers_per_list_label') ?? 'Max Subscribers / List' ?></label>
                        <input type="number" name="max_subscribers_per_list" class="form-control form-control-sm" value="500" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold"><?= __('max_emails_per_month') ?? 'Max Emails / Month' ?></label>
                        <input type="number" name="max_emails_per_month" class="form-control form-control-sm" value="1000" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">🤖 <?= __('section_ai_limits') ?? 'AI Studio Limits' ?></h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_monthly_credits') ?? 'AI Credits Allowance' ?></label>
                        <input type="number" name="ai_credits_month" class="form-control form-control-sm" value="50" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_credits_period') ?? 'Credits Period' ?></label>
                        <select name="ai_credits_period" class="form-select form-select-sm">
                            <option value="monthly" selected><?= __('monthly') ?? 'Monthly' ?></option>
                            <option value="daily"><?= __('daily') ?? 'Daily' ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_max_campaign_posts') ?? 'AI Max Campaign Posts' ?></label>
                        <input type="number" name="ai_max_campaign_posts" class="form-control form-control-sm" value="10" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_max_daily_posts') ?? 'AI Max Daily Posts' ?></label>
                        <input type="number" name="ai_max_daily_posts" class="form-control form-control-sm" value="2" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">📁 <?= __('section_storage_limits') ?? 'Storage & Upload Limits' ?></h6>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold"><?= __('max_storage_size') ?? 'Max Storage Size (e.g. 1GB, 500MB)' ?></label>
                        <input type="text" name="max_storage_size" class="form-control form-control-sm" value="1GB" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold"><?= __('max_upload_size') ?? 'Max Upload Size (e.g. 50MB, 10MB)' ?></label>
                        <input type="text" name="max_upload_size" class="form-control form-control-sm" value="50MB" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?? 'Cancel' ?></button>
                <button type="submit" class="btn btn-primary fw-bold"><?= __('save_plan') ?? 'Save Plan' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Plan -->
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="/admin/billing/plans/update" method="POST" class="modal-content text-start">
            <input type="hidden" name="id" id="edit_plan_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i> <?= __('edit_subscription_plan') ?? 'Edit Subscription Plan' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('close') ?? 'Close' ?>"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><?= __('plan_name_label') ?? 'Plan Name' ?></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('plan_group_code') ?? 'Plan Group Code' ?></label>
                        <input type="text" name="plan_code" id="edit_plan_code" class="form-control" placeholder="e.g. free, pro, ultra">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('stripe_price_id') ?? 'Stripe Price ID' ?></label>
                        <input type="text" name="stripe_price_id" id="edit_stripe_price_id" class="form-control" placeholder="e.g. price_1Nxxx...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><?= __('price_currency') ?? 'Price (€)' ?></label>
                        <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('billing_cycle') ?? 'Billing Cycle' ?></label>
                        <select name="billing_cycle" id="edit_billing_cycle" class="form-select">
                            <option value="monthly"><?= __('monthly') ?? 'Monthly' ?></option>
                            <option value="yearly"><?= __('yearly') ?? 'Yearly' ?></option>
                            <option value="lifetime"><?= __('lifetime') ?? 'Lifetime' ?></option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_default" id="edit_is_default" value="1">
                            <label class="form-check-label fw-bold" for="edit_is_default">
                                🌟 <?= __('set_as_default') ?? 'Set as Default' ?>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_hidden" id="edit_is_hidden" value="1">
                            <label class="form-check-label fw-bold" for="edit_is_hidden">
                                🙈 <?= __('hide_from_public_views') ?? 'Hide from Public Views' ?>
                            </label>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">📌 <?= __('section_social_limits') ?? 'Social Posting & Feed Limits' ?></h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('max_social_accounts') ?? 'Max Social Accounts' ?></label>
                        <input type="number" name="max_accounts" id="edit_max_accounts" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('max_posts') ?? 'Max Posts' ?></label>
                        <input type="number" name="max_posts" id="edit_max_posts" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold"><?= __('post_period') ?? 'Post Period' ?></label>
                        <select name="post_period" id="edit_post_period" class="form-select form-select-sm">
                            <option value="daily"><?= __('daily') ?? 'Daily' ?></option>
                            <option value="monthly"><?= __('monthly') ?? 'Monthly' ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold"><?= __('max_rss_feeds') ?? 'Max RSS Feeds' ?></label>
                        <input type="number" name="max_rss_feeds" id="edit_max_rss_feeds" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold"><?= __('max_rss_posts') ?? 'Max RSS Posts' ?></label>
                        <input type="number" name="max_rss_posts" id="edit_max_rss_posts" class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold text-primary"><?= __('max_channel_automations') ?? 'Max Channel Automations' ?></label>
                        <input type="number" name="max_channel_automations" id="edit_max_channel_automations" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold text-primary"><?= __('max_channel_posts_label') ?? 'Max Channel Automation Posts' ?></label>
                        <input type="number" name="max_channel_posts" id="edit_max_channel_posts" class="form-control form-control-sm" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">📧 <?= __('section_mailing_limits') ?? 'Mailing List Limits' ?></h6>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold"><?= __('max_mailing_lists') ?? 'Max Mailing Lists' ?></label>
                        <input type="number" name="max_mailing_lists" id="edit_max_mailing_lists" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold"><?= __('max_subscribers_per_list_label') ?? 'Max Subscribers / List' ?></label>
                        <input type="number" name="max_subscribers_per_list" id="edit_max_subscribers_per_list" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold"><?= __('max_emails_per_month') ?? 'Max Emails / Month' ?></label>
                        <input type="number" name="max_emails_per_month" id="edit_max_emails_per_month" class="form-control form-control-sm" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">🤖 <?= __('section_ai_limits') ?? 'AI Studio Limits' ?></h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_monthly_credits') ?? 'AI Credits Allowance' ?></label>
                        <input type="number" name="ai_credits_month" id="edit_ai_credits_month" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_credits_period') ?? 'Credits Period' ?></label>
                        <select name="ai_credits_period" id="edit_ai_credits_period" class="form-select form-select-sm">
                            <option value="monthly"><?= __('monthly') ?? 'Monthly' ?></option>
                            <option value="daily"><?= __('daily') ?? 'Daily' ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_max_campaign_posts') ?? 'AI Max Campaign Posts' ?></label>
                        <input type="number" name="ai_max_campaign_posts" id="edit_ai_max_campaign_posts" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold"><?= __('ai_max_daily_posts') ?? 'AI Max Daily Posts' ?></label>
                        <input type="number" name="ai_max_daily_posts" id="edit_ai_max_daily_posts" class="form-control form-control-sm" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary">📁 <?= __('section_storage_limits') ?? 'Storage & Upload Limits' ?></h6>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold"><?= __('max_storage_size') ?? 'Max Storage Size (e.g. 1GB, 500MB)' ?></label>
                        <input type="text" name="max_storage_size" id="edit_max_storage_size" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold"><?= __('max_upload_size') ?? 'Max Upload Size (e.g. 50MB, 10MB)' ?></label>
                        <input type="text" name="max_upload_size" id="edit_max_upload_size" class="form-control form-control-sm" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?? 'Cancel' ?></button>
                <button type="submit" class="btn btn-primary fw-bold"><?= __('update_plan') ?? 'Update Plan' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    function editPlan(plan) {
        document.getElementById('edit_plan_id').value = plan.id || '';
        document.getElementById('edit_name').value = plan.name || '';
        document.getElementById('edit_plan_code').value = plan.plan_code || '';
        document.getElementById('edit_stripe_price_id').value = plan.stripe_price_id || '';
        document.getElementById('edit_price').value = plan.price || 0;
        document.getElementById('edit_billing_cycle').value = plan.billing_cycle || 'monthly';
        document.getElementById('edit_max_accounts').value = plan.max_accounts || 1;
        document.getElementById('edit_max_posts').value = plan.max_posts || 10;
        document.getElementById('edit_post_period').value = plan.post_period || 'monthly';
        document.getElementById('edit_max_rss_feeds').value = plan.max_rss_feeds !== undefined ? plan.max_rss_feeds : 5;
        document.getElementById('edit_max_rss_posts').value = plan.max_rss_posts !== undefined ? plan.max_rss_posts : 50;
        document.getElementById('edit_max_channel_automations').value = plan.max_channel_automations !== undefined ? plan.max_channel_automations : 5;
        document.getElementById('edit_max_channel_posts').value = plan.max_channel_posts !== undefined ? plan.max_channel_posts : 50;
        document.getElementById('edit_ai_credits_month').value = plan.ai_credits_month || 0;
        document.getElementById('edit_ai_credits_period').value = plan.ai_credits_period || 'monthly';
        document.getElementById('edit_ai_max_campaign_posts').value = plan.ai_max_campaign_posts || 0;
        document.getElementById('edit_ai_max_daily_posts').value = plan.ai_max_daily_posts || 0;
        document.getElementById('edit_max_storage_size').value = plan.max_storage_size || '1GB';
        document.getElementById('edit_max_upload_size').value = plan.max_upload_size || '50MB';

        document.getElementById('edit_max_mailing_lists').value = plan.max_mailing_lists || 0;
        document.getElementById('edit_max_subscribers_per_list').value = plan.max_subscribers_per_list || 0;
        document.getElementById('edit_max_emails_per_month').value = plan.max_emails_per_month || 0;

        document.getElementById('edit_is_default').checked = parseInt(plan.is_default || 0) === 1;
        document.getElementById('edit_is_hidden').checked = parseInt(plan.is_hidden || 0) === 1;
    }

    // Dynamic Pack Adder
    document.getElementById('add-pack-btn').addEventListener('click', function() {
        const container = document.getElementById('ai-packs-container');
        const row = document.createElement('div');
        row.className = 'row g-3 align-items-end mb-3 pack-row';
        row.innerHTML = `
            <div class="col-md-5">
                <input type="number" name="pack_credits[]" class="form-control" placeholder="100" required min="10">
            </div>
            <div class="col-md-5">
                <input type="number" step="0.01" name="pack_prices[]" class="form-control" placeholder="5.00" required min="0.50">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100 remove-pack-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(row);
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-pack-btn')) {
            const rows = document.querySelectorAll('.pack-row');
            if (rows.length > 1) {
                e.target.closest('.pack-row').remove();
            } else {
                alert('At least one package is required.');
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
