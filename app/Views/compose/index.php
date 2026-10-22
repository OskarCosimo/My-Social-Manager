<?php
// app/Views/compose/index.php
if (!isset($_SESSION['user_id'])) exit;

if (!function_exists('getProgressPercent')) {
    /**
     * Calculate progress percentage safely
     */
    function getProgressPercent(int $used, int $max): int {
        if ($max <= 0) return 100;
        return (int)min(100, round(($used / $max) * 100));
    }
}

$postsPercent = getProgressPercent($limits['posts_used'] ?? 0, $limits['max_posts'] ?? 1);

// Fetch dynamic post period label from limits
$postPeriodLabel = ucfirst(htmlspecialchars($limits['post_period'] ?? $limits['post_limit_period'] ?? 'monthly'));
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<style>
    .stat-card { transition: transform 0.2s ease; border-radius: 12px; }
    .stat-card:hover { transform: translateY(-3px); }
    .progress { height: 8px; border-radius: 4px; }
    .stat-icon-badge { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; flex-shrink: 0; }
</style>

<!-- Main flex wrapper to align sidebar and content side-by-side -->
<div class="d-flex main-wrapper bg-light">
    
    <!-- Include the sidebar and set the active menu item -->
    <?php 
    $activeMenu = 'compose'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <!-- Main content area taking up the remaining full width -->
    <main class="content-area w-100 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 m-0 fw-bold text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-pencil-square text-primary"></i> <?= __('compose') ?>
            </h1>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- POST STATISTICS ROW (4 CARDS HORIZONTALLY) -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
            
            <!-- 1. Dynamic Plan Limit -->
            <div class="col">
                <div class="card stat-card shadow-sm border-0 position-relative overflow-hidden bg-white h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-uppercase fw-bold text-muted mb-0 small">
                                <?= str_replace('{period}', $postPeriodLabel, __('posts_period_title')) ?>
                            </h6>
                            <div class="stat-icon-badge bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-file-earmark-text fs-5"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-end mb-2">
                            <h2 class="fs-3 fw-bold mb-0 text-dark"><?= $limits['posts_used'] ?? 0 ?></h2>
                            <span class="text-muted ms-2 small">/ <?= $limits['max_posts'] ?? 10 ?></span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $postsPercent ?>%"></div>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <?= str_replace(['{percent}', '{period}'], [$postsPercent, strtolower($postPeriodLabel)], __('posts_stat_limit_used')) ?>
                        </small>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #0d6efd;"></div>
                </div>
            </div>

            <!-- 2. Published Posts -->
            <div class="col">
                <div class="card stat-card shadow-sm border-0 position-relative overflow-hidden bg-white h-100">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="stat-icon-badge bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle fs-4"></i>
                        </div>
                        <div>
                            <div class="text-uppercase fw-bold text-muted small"><?= __('stat_published') ?></div>
                            <div class="fs-3 fw-bold text-success"><?= (int)($stats['published'] ?? 0) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;"><?= __('stat_published_sub') ?></small>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #198754;"></div>
                </div>
            </div>

            <!-- 3. Scheduled Posts -->
            <div class="col">
                <div class="card stat-card shadow-sm border-0 position-relative overflow-hidden bg-white h-100">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="stat-icon-badge bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock-history fs-4"></i>
                        </div>
                        <div>
                            <div class="text-uppercase fw-bold text-muted small"><?= __('stat_scheduled') ?></div>
                            <div class="fs-3 fw-bold text-warning"><?= (int)($stats['scheduled'] ?? 0) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;"><?= __('stat_scheduled_sub') ?></small>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #ffc107;"></div>
                </div>
            </div>

            <!-- 4. Failed Posts -->
            <div class="col">
                <div class="card stat-card shadow-sm border-0 position-relative overflow-hidden bg-white h-100">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="stat-icon-badge bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </div>
                        <div>
                            <div class="text-uppercase fw-bold text-muted small"><?= __('stat_failed') ?></div>
                            <div class="fs-3 fw-bold text-danger"><?= (int)($stats['failed'] ?? 0) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;"><?= __('stat_failed_sub') ?></small>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background-color: #dc3545;"></div>
                </div>
            </div>

        </div>

        <form action="/compose/publish" method="POST" id="compose-form">
            <div class="row g-4">
                
                <!-- Left Column: Editor, AI & Account Selection -->
                <div class="col-md-8">
                    
                    <!-- Post Content Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                            <h5 class="fw-bold text-secondary mb-0"><?= __('post_content') ?></h5>
                        </div>
                        <div class="card-body p-4">
                            
                            <!-- AI Prompt Input Group -->
                            <div class="input-group mb-4 shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 text-primary fs-5">
                                    <i class="bi bi-robot"></i>
                                </span>
                                <input type="text" id="ai-prompt" class="form-control border-start-0 border-end-0 py-2" placeholder="<?= __('ai_prompt_placeholder') ?>">
                                <button class="btn btn-primary px-4 fw-bold d-inline-flex align-items-center gap-1" type="button" id="btn-ai-generate">
                                    <i class="bi bi-stars"></i> <?= __('generate') ?>
                                </button>
                            </div>

                            <!-- WYSIWYG Editor -->
                            <div class="mb-4">
                                <textarea id="my-wysiwyg-editor" name="content" class="form-control" rows="10" placeholder="<?= __('write_your_post') ?>"></textarea>
                            </div>

                            <!-- Link Attachment Section -->
                            <div class="p-3 bg-light border rounded shadow-sm mt-3">
                                <label class="form-label fw-bold text-dark mb-2 d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-link-45deg"></i> <?= __('direct_link_optional') ?>
                                </label>
                                <input type="url" name="source_link" id="source-link-input" class="form-control" placeholder="https://example.com/article">
                                <small class="text-muted"><?= __('direct_link_help') ?></small>
                            </div>

                            <!-- Media Attachments Section -->
                            <div class="p-3 bg-light border rounded shadow-sm mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label fw-bold text-dark mb-0 d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-paperclip"></i> <?= __('attached_media') ?>
                                    </label>
                                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#fileManagerModal">
                                        <i class="bi bi-images"></i> <?= __('select_media') ?>
                                    </button>
                                </div>
                                
                                <div id="attachments-list" class="d-flex flex-wrap gap-2"></div>
                                <div id="hidden-media-inputs"></div>
                            </div>

                        </div>
                    </div>

                    <!-- Account Selection Card -->
                    <div class="card shadow-sm border-0 bg-white mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-dark mb-4"><?= __('select_accounts') ?></h5>
                            
                            <?php if (!empty($groups)): ?>
                                <!-- Group Selection Dropdown -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-secondary small text-uppercase mb-2 d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-folder2"></i> <?= __('quick_selection') ?>
                                    </label>
                                    <div class="input-group shadow-sm rounded" style="max-width: 400px;">
                                        <select class="form-select border-secondary-subtle" id="group-select">
                                            <option value=""><?= __('select_a_group') ?></option>
                                            <?php foreach ($groups as $group): ?>
                                                <?php 
                                                    $jsonValue = isset($group['account_ids']) ? json_encode($group['account_ids']) : ($group['account_ids_json'] ?? '[]'); 
                                                ?>
                                                <option value="<?= htmlspecialchars($jsonValue, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($group['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline-danger px-3" type="button" id="clear-accounts-btn" title="<?= __('clear_selections_title') ?>">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Social Accounts DataTable -->
                            <div class="d-flex flex-column">
                                <label class="form-label fw-bold text-secondary small text-uppercase mb-2 d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-share"></i> <?= __('connected_accounts_title') ?>
                                </label>
                                <?php if (empty($connectedAccounts)): ?>
                                    <div class="alert alert-warning border-warning shadow-sm">
                                        <p class="mb-2 small">
                                            <strong><?= __('no_accounts_found') ?></strong><br>
                                            <?= __('no_accounts_compose') ?>
                                        </p>
                                        <a href="/accounts" class="btn btn-sm btn-primary fw-bold d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-link-45deg"></i> <?= __('connect_now') ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive bg-light border rounded p-2 shadow-sm">
                                        <table class="table table-hover align-middle mb-0 dt-table w-100" id="compose-accounts-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40px;" class="text-center">✓</th>
                                                    <th><?= __('account_details') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($connectedAccounts as $acc): ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input m-0 border-secondary" type="checkbox" name="target_accounts[]" value="<?= $acc['id'] ?>" id="chk_acc_<?= $acc['id'] ?>">
                                                        </td>
                                                        <td>
                                                            <label class="form-check-label w-100 cursor-pointer d-flex flex-row align-items-center gap-2" for="chk_acc_<?= $acc['id'] ?>">
                                                                <span class="fw-bold text-dark"><?= htmlspecialchars($acc['platform_name']) ?></span>
                                                                <span class="badge bg-secondary" style="font-size: 0.7em;">
                                                                    <?= htmlspecialchars(ucfirst($acc['plugin_id'])) ?>
                                                                </span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($mailingLists)): ?>
                                    <hr class="text-muted opacity-25">
                                    <div class="mt-3">
                                        <label class="form-label fw-semibold text-secondary d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-envelope-paper"></i> <?= __('mailing_lists') ?>
                                        </label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($mailingLists as $list): ?>
                                                <div class="form-check border rounded px-3 py-2 bg-white shadow-sm">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="target_mailing_lists[]" 
                                                           value="<?= $list['id'] ?>" 
                                                           id="ml_<?= $list['id'] ?>">
                                                    <label class="form-check-label d-flex align-items-center gap-2" for="ml_<?= $list['id'] ?>">
                                                        <span class="badge bg-warning text-dark">Mailing List</span>
                                                        <?= htmlspecialchars($list['name']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                            
                        </div>
                    </div>

                </div>

                <!-- Right Column: Publishing & Scheduling -->
                <div class="col-md-4">
                    
                    <div class="card shadow-sm border-0 bg-white mb-4 position-sticky" style="top: 1.5rem;">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-dark mb-4 d-inline-flex align-items-center gap-2">
                                <i class="bi bi-send text-primary"></i> <?= __('publish_card_title') ?>
                            </h5>
                            
                            <label class="form-label fw-bold text-dark mb-2 d-inline-flex align-items-center gap-1">
                                <i class="bi bi-clock"></i> <?= __('schedule_optional') ?>
                            </label>
                            <input type="datetime-local" name="scheduled_at" class="form-control form-control-lg shadow-sm mb-2">
                            <div class="text-muted small text-center mb-4">
                                <i class="bi bi-geo-alt"></i> <?= __('timezone_label') ?> <strong><?= htmlspecialchars($_SESSION['user_timezone'] ?? 'UTC') ?></strong> 
                                (<a href="/settings" class="text-decoration-none"><?= __('change') ?></a>)
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow d-inline-flex align-items-center justify-content-center gap-2" <?= (empty($connectedAccounts) && empty($mailingLists)) ? 'disabled' : '' ?>>
                                <i class="bi bi-send-fill"></i> <?= __('publish_now') ?>
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </form>
    </main>
</div>

<!-- Modal for File Manager Iframe -->
<div class="modal fade" id="fileManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="height: 85vh;">
        <div class="modal-content h-100 shadow-lg border-0">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-folder2-open text-warning"></i> <?= __('file_manager') ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 h-100 bg-light">
                <iframe src="/filemanager/standalone" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.myetv.tv/my-wysiwyg/my_wysiwyg.js?v=11"></script>
<script src="https://cdn.myetv.tv/my-wysiwyg/my_wysiwyg_i18n.js?v=11"></script>
<link rel="stylesheet" href="https://cdn.myetv.tv/my-wysiwyg/my_wysiwyg.css?v=11" />

<script>
document.addEventListener('DOMContentLoaded', function() {

    const storageKey = 'compose_selected_accounts';
    const savedAccounts = JSON.parse(localStorage.getItem(storageKey)) || [];
    
    function updateSavedAccounts() {
        const checkedBoxes = document.querySelectorAll('input[name="target_accounts[]"]:checked');
        const checkedIds = Array.from(checkedBoxes).map(cb => cb.value);
        localStorage.setItem(storageKey, JSON.stringify(checkedIds));
    }

    document.querySelectorAll('input[name="target_accounts[]"]').forEach(checkbox => {
        if (savedAccounts.includes(checkbox.value)) {
            checkbox.checked = true;
        }
        checkbox.addEventListener('change', updateSavedAccounts);
    });

    const groupSelect = document.getElementById('group-select');
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            if (!this.value) return; 
            
            let accountIds = [];
            try {
                let parsed = JSON.parse(this.value);
                if (typeof parsed === 'string') parsed = parsed.split(','); 
                accountIds = Array.isArray(parsed) ? parsed.map(id => String(id).trim()) : [String(parsed).trim()];
            } catch(e) {
                console.error("Failed to parse accounts JSON:", e);
                return;
            }

            document.querySelectorAll('input[name="target_accounts[]"]').forEach(cb => {
                if (accountIds.includes(String(cb.value))) {
                    cb.checked = true;
                }
            });

            try {
                const dt = $('#compose-accounts-table').DataTable();
                dt.$('input[name="target_accounts[]"]').each(function() {
                    if (accountIds.includes(String(this.value))) {
                        $(this).prop('checked', true);
                    }
                });
            } catch (error) {}
            
            updateSavedAccounts();
            this.value = ""; 
        });
    }

    const clearBtn = document.getElementById('clear-accounts-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="target_accounts[]"]').forEach(cb => {
                cb.checked = false;
            });

            try {
                $('#compose-accounts-table').DataTable().$('input[name="target_accounts[]"]').prop('checked', false);
            } catch(e) {}

            localStorage.removeItem(storageKey);
        });
    }

    $('#compose-form').on('submit', function(e) {
        let dt;
        try {
            dt = $('#compose-accounts-table').DataTable();
        } catch(err) {
            return;
        }

        const form = $(this);
        const checkedInputs = dt.$('input[name="target_accounts[]"]:checked');
        
        $('.dt-hidden-inputs').remove();
        
        checkedInputs.each(function() {
            if (!$.contains(document, this)) {
                form.append(
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'target_accounts[]')
                        .val($(this).val())
                        .addClass('dt-hidden-inputs')
                );
            }
        });
        
        localStorage.removeItem(storageKey);
    });

    let editor;
    const appLanguage = "<?= htmlspecialchars($_SESSION['app_language'] ?? 'en') ?>";
    const editorPlaceholder = "<?= __('write_your_post') ?>";

    if (typeof MyWysiwyg === 'function') {
        try {
            editor = new MyWysiwyg('#my-wysiwyg-editor', {
                lang: appLanguage,
                maxChars: 1000,
                minHeight: '200px',
                maxHeight: '600px',
                placeholder: editorPlaceholder,
                toolbar: [
                    'ul', 'ol', '|', 
                    'link', 'emoji', '|', 
                    'undo', 'redo'
                ]
            });
        } catch (e) {
            console.error("My-WYSIWYG initialization failed: ", e);
        }
    }

    const pendingFile = localStorage.getItem('pending_file_url');
    if (pendingFile) {
        addAttachmentToUI(pendingFile);
        localStorage.removeItem('pending_file_url');
    }

    const appBaseUrl = "<?= rtrim(Config::APP_URL, '/') ?>";
    const currentUserId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;

    function addAttachmentToUI(fileUrl) {
        const list = document.getElementById('attachments-list');
        const hiddenContainer = document.getElementById('hidden-media-inputs');
        const uniqueId = 'media-' + Date.now();
        
        let finalUrl = fileUrl;

        if (finalUrl.includes('mysocialmanager')) {
            finalUrl = finalUrl.replace(/https?:\/\/[^\/]+/, appBaseUrl);
        }

        if (currentUserId > 0 && !finalUrl.includes('/user_' + currentUserId + '/')) {
            const userFolder = 'user_' + currentUserId;
            if (finalUrl.includes('/files/')) {
                finalUrl = finalUrl.replace('/files/', '/files/' + userFolder + '/');
            }
        }

        if (!finalUrl.startsWith('http')) {
            finalUrl = appBaseUrl + '/' + finalUrl.replace(/^\/+/, '');
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'media_urls[]'; 
        input.value = finalUrl;
        input.id = 'input-' + uniqueId;
        hiddenContainer.appendChild(input);
        
        const badge = document.createElement('div');
        badge.className = 'badge bg-primary text-white d-flex align-items-center p-2 shadow-sm';
        badge.id = 'badge-' + uniqueId;
        
        let icon = '<i class="bi bi-file-earmark me-1"></i>';
        if (finalUrl.match(/\.(jpeg|jpg|gif|png|webp)$/i)) icon = '<i class="bi bi-image me-1"></i>';
        else if (finalUrl.match(/\.(mp4|webm|avi|mov)$/i)) icon = '<i class="bi bi-camera-video me-1"></i>';
        
        const fileName = finalUrl.split('/').pop();
        
        badge.innerHTML = `
            <span class="me-2 d-inline-flex align-items-center">${icon} ${fileName}</span>
            <button type="button" class="btn-close btn-close-white" aria-label="Remove" 
                    style="font-size: 0.6rem; cursor: pointer;" 
                    onclick="removeAttachment('${uniqueId}')"></button>
        `;
        
        list.appendChild(badge);
    }

    window.removeAttachment = function(id) {
        const badgeEl = document.getElementById('badge-' + id);
        const inputEl = document.getElementById('input-' + id);
        if (badgeEl) badgeEl.remove();
        if (inputEl) inputEl.remove();
    };

    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'file_selected' && event.data.url) {
            addAttachmentToUI(event.data.url);
            const fmModalEl = document.getElementById('fileManagerModal');
            if (fmModalEl && typeof bootstrap !== 'undefined') {
                const modalInstance = bootstrap.Modal.getInstance(fmModalEl) || new bootstrap.Modal(fmModalEl);
                if (modalInstance) modalInstance.hide();
            }
        }
    });

    const btnAi = document.getElementById('btn-ai-generate');
    const promptInput = document.getElementById('ai-prompt');

    if (btnAi && promptInput) {
        btnAi.addEventListener('click', async function() {
            const prompt = promptInput.value.trim();
            if (!prompt) {
                alert("<?= __('please_enter_prompt') ?>");
                return;
            }

            const originalText = btnAi.innerHTML;
            btnAi.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> <?= __("generating_wait") ?>';
            btnAi.disabled = true;

            try {
                const response = await fetch('/api/ai/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt: prompt })
                });

                const data = await response.json();

                if (data.success) {
                    const generatedText = data.text;
                    editor.setHtml(generatedText); 
                    promptInput.value = ''; 
                } else {
                    alert("AI Error: " + data.error);
                }
            } catch (error) {
                console.error("Fetch error:", error);
                alert("Failed to connect to the internal AI API.");
            } finally {
                btnAi.innerHTML = originalText;
                btnAi.disabled = false;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>