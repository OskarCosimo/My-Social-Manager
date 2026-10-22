<?php
// app/Views/groups/index.php
if (!isset($_SESSION['user_id'])) exit;
require_once __DIR__ . '/../partials/header.php'; 
?>

<div class="d-flex main-wrapper bg-light">
    
    <?php 
    $activeMenu = 'groups'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <main class="content-area w-100 p-4">
        <h1 class="h2 mb-4 fw-bold text-dark d-inline-flex align-items-center gap-2">
            <i class="bi bi-collection text-primary"></i> <?= __('groups_management') ?>
        </h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Left Column: Form to Create/Edit Groups -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 position-sticky" style="top: 1.5rem;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-secondary d-inline-flex align-items-center gap-2" id="form-title">
                            <i class="bi bi-plus-circle text-primary"></i> <?= __('create_new_group') ?>
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="cancel-edit-btn">
                            <?= __('cancel_edit') ?>
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <form action="/groups/save" method="POST" id="group-form">
                            <!-- Hidden input for updates -->
                            <input type="hidden" name="group_id" id="form-group-id" value="">

                            <div class="mb-4">
                                <label for="groupName" class="form-label fw-bold text-dark"><?= __('group_name') ?></label>
                                <input type="text" class="form-control form-control-lg shadow-sm border-secondary-subtle" id="groupName" name="name" required placeholder="<?= __('group_name_placeholder') ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-share"></i> <?= __('select_social_accounts') ?>
                                </label>
                                
                                <?php if (empty($accounts)): ?>
                                    <div class="alert alert-warning border-warning shadow-sm">
                                        <p class="text-dark small mb-0">
                                            <?= __('no_accounts_connected_yet') ?><br>
                                            <a href="/accounts" class="fw-bold"><?= __('connect_one_now') ?></a>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <!-- DataTable for Account Selection -->
                                    <div class="table-responsive bg-light border rounded p-2 shadow-sm">
                                        <table class="table table-hover align-middle mb-0 dt-table w-100" id="group-accounts-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40px;" class="text-center">✓</th>
                                                    <th><?= __('account_details') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($accounts as $account): ?>
                                                    <?php 
                                                        $acc = is_object($account) ? (array)$account : (array)$account;
                                                        $accId = $acc['id'] ?? 0;
                                                        $platformName = $acc['platform_name'] ?? 'Social Account';
                                                        $pluginId = $acc['plugin_id'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input m-0 border-secondary" type="checkbox" name="accounts[]" value="<?= $accId ?>" id="acc_<?= $accId ?>">
                                                        </td>
                                                        <td>
                                                            <label class="form-check-label w-100 cursor-pointer d-flex flex-row align-items-center gap-2" for="acc_<?= $accId ?>">
                                                                <span class="fw-bold text-dark"><?= htmlspecialchars($platformName) ?></span>
                                                                <?php if (!empty($pluginId)): ?>
                                                                    <span class="badge bg-secondary" style="font-size: 0.7em;">
                                                                        <?= htmlspecialchars(ucfirst($pluginId)) ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="form-text mt-2 text-muted small">
                                        <?= __('group_accounts_help') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow d-inline-flex align-items-center justify-content-center gap-2" id="submit-btn">
                                <i class="bi bi-floppy"></i> <?= __('save_group') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Existing Groups List -->
            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="card-title mb-0 fw-bold text-secondary d-inline-flex align-items-center gap-2">
                            <i class="bi bi-list-task text-primary"></i> <?= __('your_existing_groups') ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($groups)): ?>
                            <div class="text-center text-muted p-5 bg-light rounded border border-dashed">
                                <h4><?= __('no_groups_found') ?></h4>
                                <p class="mb-0"><?= __('no_groups_found_sub') ?></p>
                            </div>
                        <?php else: ?>
                            <!-- DataTable for Group List -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 dt-table w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?= __('group_name') ?></th>
                                            <th><?= __('accounts_linked') ?></th>
                                            <th class="text-end"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groups as $group): ?>
                                            <?php 
                                                // Prepare JSON payload for the Edit button
                                                $groupData = json_encode([
                                                    'id' => $group['id'],
                                                    'name' => $group['name'],
                                                    'accounts' => $group['account_ids']
                                                ]); 
                                            ?>
                                            <tr>
                                                <td class="fw-bold text-primary">
                                                    <?= htmlspecialchars($group['name']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark rounded-pill px-3 shadow-sm">
                                                        <?= str_replace('{count}', count($group['account_ids']), __('accounts_count_badge')) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <!-- Edit Button -->
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-group-btn shadow-sm me-1 d-inline-flex align-items-center gap-1" data-group="<?= htmlspecialchars($groupData, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-pencil"></i> <?= __('edit') ?>
                                                    </button>
                                                    
                                                    <!-- Delete Button -->
                                                    <form action="/groups/delete" method="POST" class="d-inline m-0" onsubmit="return confirm('<?= addslashes(__('confirm_delete_group')) ?>');">
                                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-trash3"></i> <?= __('delete') ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // UI Elements
    const formTitle = document.getElementById('form-title');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const groupIdInput = document.getElementById('form-group-id');
    const groupNameInput = document.getElementById('groupName');
    const submitBtn = document.getElementById('submit-btn');

    // --- EDIT BUTTON LOGIC ---
    const editButtons = document.querySelectorAll('.edit-group-btn');
    
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            let groupData = {};
            
            try {
                groupData = JSON.parse(this.getAttribute('data-group'));
            } catch (e) {
                console.error("Failed to parse group data", e);
                return;
            }

            // Populate form fields
            groupIdInput.value = groupData.id;
            groupNameInput.value = groupData.name;
            
            // Update UI to reflect Edit Mode with Bootstrap icons
            formTitle.innerHTML = '<i class="bi bi-pencil-square text-primary me-1"></i> <?= addslashes(__("edit_group")) ?>';
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> <?= addslashes(__("update_group")) ?>';
            cancelEditBtn.classList.remove('d-none');
            
            // Highlight the name input gently
            groupNameInput.focus();

            // Handle DataTables Checkboxes
            try {
                const dt = $('#group-accounts-table').DataTable();
                
                // 1. Uncheck everything first
                dt.$('input[name="accounts[]"]').prop('checked', false);
                
                // 2. Check the ones belonging to the group
                const accountIds = Array.isArray(groupData.accounts) ? groupData.accounts.map(String) : [];
                dt.$('input[name="accounts[]"]').each(function() {
                    if (accountIds.includes(String(this.value))) {
                        $(this).prop('checked', true);
                    }
                });
            } catch (error) {
                console.warn("DataTable not initialized yet or table missing.");
            }
        });
    });

    // --- CANCEL EDIT LOGIC ---
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            // Reset form fields
            groupIdInput.value = '';
            groupNameInput.value = '';
            
            // Reset UI
            formTitle.innerHTML = '<i class="bi bi-plus-circle text-primary me-1"></i> <?= addslashes(__("create_new_group")) ?>';
            submitBtn.innerHTML = '<i class="bi bi-floppy me-1"></i> <?= addslashes(__("save_group")) ?>';
            this.classList.add('d-none');
            
            // Clear all checkboxes
            try {
                const dt = $('#group-accounts-table').DataTable();
                dt.$('input[name="accounts[]"]').prop('checked', false);
            } catch (e) {}
        });
    }

    // --- FORM SUBMISSION INTERCEPT (DataTables Pagination Support) ---
    $('#group-form').on('submit', function(e) {
        let dt;
        try {
            dt = $('#group-accounts-table').DataTable();
        } catch(err) {
            return; // Fallback to standard HTML submission if DataTables fails
        }

        const form = $(this);
        const checkedInputs = dt.$('input[name="accounts[]"]:checked');
        
        // Remove any previously injected hidden inputs
        $('.dt-hidden-inputs').remove();
        
        checkedInputs.each(function() {
            // If the element is detached from the DOM (e.g., hidden page), manually append it
            if (!$.contains(document, this)) {
                form.append(
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'accounts[]')
                        .val($(this).val())
                        .addClass('dt-hidden-inputs')
                );
            }
        });
    });

});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>