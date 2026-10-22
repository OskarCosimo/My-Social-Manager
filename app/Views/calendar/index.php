<?php
// app/Views/calendar/index.php
if (!isset($_SESSION['user_id'])) exit;

// Retrieve the active interface language code (e.g. 'it', 'en', 'es')
$currentLang = $_COOKIE['lang'] ?? $_SESSION['app_language'] ?? Config::DEFAULT_LANG ?? 'en';
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<style>
/* ==========================================================================
   FullCalendar - Minimalist Clean Layout Styles
   ========================================================================== */

/* Prevent scrollbar overflow on event containers in grid views */
.fc-dayGridMonth-view .fc-event,
.fc-dayGridDay-view .fc-event {
    overflow: hidden !important;
}

/* Base event container style */
.fc-event-main {
    position: relative !important;
    padding: 1px 0 !important;
}

/* Minimalist plugin color indicator dot */
.plugin-dot {
    width: 8px !important;
    height: 8px !important;
    min-width: 8px !important;
    border-radius: 50% !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
    box-shadow: 0 0 2px rgba(0, 0, 0, 0.3);
}

/* Custom mini badge styling for Day Grid View */
.fc-grid-badge {
    font-size: 0.65rem !important;
    padding: 1px 4px !important;
    border-radius: 3px !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    line-height: 1.1 !important;
}

/* Time display formatting */
.fc-event-time-text {
    font-size: 0.68rem !important;
    font-weight: 700 !important;
    color: #495057 !important;
    margin-right: 2px !important;
    flex-shrink: 0 !important;
}

/* Title text truncation helper */
.fc-event-title-text {
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    font-size: 0.72rem !important;
}

/* Status icon spacing */
.fc-status-icon {
    font-size: 0.65rem !important;
    line-height: 1 !important;
    flex-shrink: 0 !important;
}

/* Remove default FullCalendar background for custom HTML rendering */
.fc-v-event, .fc-h-event {
    background-color: transparent !important;
    border: none !important;
}
</style>

<!-- Main flex wrapper to align sidebar and content side-by-side -->
<div class="d-flex main-wrapper">
    
    <!-- Include sidebar with active calendar item -->
    <?php 
    $activeMenu = 'calendar'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <!-- Main content area -->
    <main class="content-area w-100 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h2 m-0 fw-bold d-inline-flex align-items-center gap-2">
                <i class="bi bi-calendar3 text-primary"></i> <?= __('calendar') ?? 'Calendar' ?>
            </h1>
            <span class="badge bg-secondary d-inline-flex align-items-center gap-1 py-2 px-3 fs-6">
                <i class="bi bi-clock me-1"></i>
                <?= __('timezone_label') ?? 'Timezone:' ?> <?= htmlspecialchars($userTimezone ?? $_SESSION['user_timezone'] ?? 'Europe/Rome') ?>
            </span>
        </div>

        <div id="calendar-container" class="card shadow-sm border-0 p-3 bg-white">
            <div id="calendar"></div>
        </div>
    </main>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="eventModalLabel">
                    <span id="modalPlatform"></span> <?= __('post_details') ?? 'Post Details' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('close') ?? 'Close' ?>"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <!-- Plugin Name Badge with custom plugin color -->
                        <span class="badge fs-6 text-white px-3 py-2" id="modalPlugin"></span>
                        
                        <!-- Distinct White Background Status Badge -->
                        <span class="badge bg-white border fs-6 px-3 py-2 shadow-sm" id="modalStatus"></span>
                    </div>
                    <small class="text-muted fw-bold" id="modalDateText"></small>
                </div>

                <div id="viewModeBox" class="p-3 bg-light rounded border">
                    <div id="modalContent"></div>
                </div>

                <div id="editModeBox" class="d-none">
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('post_content') ?? 'Post Content' ?></label>
                        <textarea id="editContent" class="form-control" rows="5"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('scheduled_date_time') ?? 'Scheduled Date & Time' ?></label>
                        <input type="datetime-local" id="editScheduledFor" class="form-control">
                    </div>
                </div>

                <div id="readOnlyNotice" class="alert alert-info mt-3 d-none mb-0">
                    <i class="bi bi-info-circle me-1"></i> <?= __('post_already_published_notice') ?? 'This post has already been published and cannot be modified or deleted.' ?>
                </div>
            </div>

            <div class="modal-footer justify-content-between">
                <div>
                    <div id="scheduledActions" class="d-none">
                        <button type="button" class="btn btn-outline-danger" id="btnDeletePost">
                            <i class="bi bi-trash3 me-1"></i> <?= __('delete_post') ?? 'Delete Post' ?>
                        </button>
                        <button type="button" class="btn btn-outline-primary ms-2" id="btnEditPost">
                            <i class="bi bi-pencil me-1"></i> <?= __('edit_post') ?? 'Edit Post' ?>
                        </button>
                    </div>

                    <div id="editActions" class="d-none">
                        <button type="button" class="btn btn-success" id="btnSavePost">
                            <i class="bi bi-floppy me-1"></i> <?= __('save_changes') ?? 'Save Changes' ?>
                        </button>
                        <button type="button" class="btn btn-secondary ms-2" id="btnCancelEdit">
                            <?= __('cancel') ?? 'Cancel' ?>
                        </button>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('close') ?? 'Close' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar Core and Multilingual Locales Bundle -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/locales-all.global.min.js'></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const rawEvents = <?= $eventsJson ?>;
        const currentLang = '<?= htmlspecialchars($currentLang) ?>';
        
        let currentEvent = null;
        let eventModalInstance = new bootstrap.Modal(document.getElementById('eventModal'));

        // Retrieve last active view from localStorage or fallback to month view
        const savedView = localStorage.getItem('fc_current_view') || 'dayGridMonth';

        // Translated status labels
        const labelPublished = "<?= addslashes(__('stat_published') ?? 'Published') ?>";
        const labelFailed    = "<?= addslashes(__('stat_failed') ?? 'Failed') ?>";
        const labelScheduled = "<?= addslashes(__('stat_scheduled') ?? 'Scheduled') ?>";
        const labelPlugin    = "<?= addslashes(__('plugin') ?? 'Plugin') ?>";

        // Initialize FullCalendar instance with active locale
        const calendar = new FullCalendar.Calendar(calendarEl, {
            locale: currentLang,
            initialView: savedView,
            dayMaxEvents: 4, // Limit month cell entries and render clean "+more" popover link
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridDay,listMonth,listDay'
            },
            buttonText: {
                today:     "<?= addslashes(__('fc_today') ?? 'Today') ?>",
                month:     "<?= addslashes(__('fc_month') ?? 'Month') ?>",
                dayGridDay:"<?= addslashes(__('fc_day_grid') ?? 'Day (Grid)') ?>",
                listMonth: "<?= addslashes(__('fc_list_month') ?? 'List (Month)') ?>",
                listDay:   "<?= addslashes(__('fc_list_day') ?? 'List (Day)') ?>"
            },
            // Save state when user changes views
            datesSet: function(dateInfo) {
                localStorage.setItem('fc_current_view', dateInfo.view.type);
            },
            // Dynamically render event content per view type
            eventContent: function(arg) {
                const props = arg.event.extendedProps;
                const viewType = arg.view.type;
                const pluginColor = props.pluginColor || '#6c757d';

                // Format time string (e.g. "23:00")
                let timeStr = '';
                if (arg.event.start) {
                    const hours = String(arg.event.start.getHours()).padStart(2, '0');
                    const minutes = String(arg.event.start.getMinutes()).padStart(2, '0');
                    timeStr = `${hours}:${minutes}`;
                }

                // Format date string for list views with user language (e.g. "03 ott 23:00")
                let dateStr = '';
                if (arg.event.start) {
                    const day = String(arg.event.start.getDate()).padStart(2, '0');
                    const month = arg.event.start.toLocaleString(currentLang, { month: 'short' });
                    dateStr = `${day} ${month} ${timeStr}`;
                }

                // Plain status icon symbol
                let statusIconHtml = '<span class="fc-status-icon">⏳</span>';
                if (props.statusRaw === 'published') {
                    statusIconHtml = '<span class="fc-status-icon">✅</span>';
                } else if (props.statusRaw === 'failed') {
                    statusIconHtml = '<span class="fc-status-icon">❌</span>';
                }

                // 1. LIST VIEWS: Extended inline layout with full plugin badge, date, time and status
                if (viewType.startsWith('list')) {
                    const pluginBadgeHtml = `<span class="badge text-white p-1" style="background-color: ${pluginColor}; font-size: 0.65rem;">${props.pluginName || labelPlugin}</span>`;
                    return {
                        html: `
                            <div class="d-flex align-items-center gap-1 text-truncate">
                                ${statusIconHtml}
                                ${pluginBadgeHtml}
                                <span class="fw-bold text-muted small ms-1">${dateStr}</span>
                                <span class="fw-bold text-truncate ms-1">${arg.event.title}</span>
                            </div>
                        `
                    };
                }

                // 2. DAY GRID VIEW: Show full plugin text badge + status icon + time + title
                if (viewType === 'dayGridDay') {
                    const pluginBadgeHtml = `<span class="badge text-white fc-grid-badge" style="background-color: ${pluginColor};">${props.pluginName || labelPlugin}</span>`;
                    return {
                        html: `
                            <div class="d-flex align-items-center w-100 gap-1 px-1 py-1 text-truncate" style="line-height: 1.2;">
                                ${pluginBadgeHtml}
                                ${statusIconHtml}
                                ${timeStr ? `<span class="fc-event-time-text">${timeStr}</span>` : ''}
                                <span class="fw-bold fc-event-title-text text-dark">${arg.event.title}</span>
                            </div>
                        `
                    };
                }

                // 3. MONTH VIEW: Clean plugin color dot + status icon + time + title
                return {
                    html: `
                        <div class="d-flex align-items-center w-100 gap-1 px-1 py-1 text-truncate" style="line-height: 1.2;">
                            <span class="plugin-dot" style="background-color: ${pluginColor};" title="${props.pluginName || labelPlugin}"></span>
                            ${statusIconHtml}
                            ${timeStr ? `<span class="fc-event-time-text">${timeStr}</span>` : ''}
                            <span class="fw-bold fc-event-title-text text-dark">${arg.event.title}</span>
                        </div>
                    `
                };
            },
            events: rawEvents,
            eventClick: function(info) {
                info.jsEvent.preventDefault(); 
                currentEvent = info.event;
                
                const props = currentEvent.extendedProps;
                
                // Populate View Modal Data
                document.getElementById('modalPlatform').innerText = props.platform || '';
                
                // Render Status badge with distinct background and border color matching its state
                const statusBadge = document.getElementById('modalStatus');
                if (props.statusRaw === 'published') {
                    statusBadge.innerHTML = '✅ ' + labelPublished;
                    statusBadge.className = 'badge bg-white border border-success text-success fs-6 px-3 py-2 shadow-sm';
                } else if (props.statusRaw === 'failed') {
                    statusBadge.innerHTML = '❌ ' + labelFailed;
                    statusBadge.className = 'badge bg-white border border-danger text-danger fs-6 px-3 py-2 shadow-sm';
                } else {
                    statusBadge.innerHTML = '⏳ ' + labelScheduled;
                    statusBadge.className = 'badge bg-white border border-primary text-primary fs-6 px-3 py-2 shadow-sm';
                }

                // Render Plugin badge with dynamic background color
                const pluginBadge = document.getElementById('modalPlugin');
                pluginBadge.innerText = '🔌 ' + (props.pluginName || labelPlugin);
                pluginBadge.style.backgroundColor = props.pluginColor || '#6c757d';

                document.getElementById('modalContent').innerHTML = props.fullContent;
                document.getElementById('modalDateText').innerText = props.scheduledFor || '';

                // Reset view/edit UI state
                switchViewMode('view');

                if (props.statusRaw === 'scheduled') {
                    document.getElementById('scheduledActions').classList.remove('d-none');
                    document.getElementById('readOnlyNotice').classList.add('d-none');
                } else {
                    document.getElementById('scheduledActions').classList.add('d-none');
                    document.getElementById('readOnlyNotice').classList.remove('d-none');
                }

                eventModalInstance.show();
            }
        });

        calendar.render();

        // Helper to switch modal between view and edit modes
        function switchViewMode(mode) {
            if (mode === 'edit') {
                document.getElementById('viewModeBox').classList.add('d-none');
                document.getElementById('editModeBox').classList.remove('d-none');
                document.getElementById('scheduledActions').classList.add('d-none');
                document.getElementById('editActions').classList.remove('d-none');

                const props = currentEvent.extendedProps;
                document.getElementById('editContent').value = props.fullContent;
                
                if (props.scheduledFor) {
                    const formattedDate = props.scheduledFor.replace(' ', 'T');
                    document.getElementById('editScheduledFor').value = formattedDate;
                }
            } else {
                document.getElementById('viewModeBox').classList.remove('d-none');
                document.getElementById('editModeBox').classList.add('d-none');
                document.getElementById('scheduledActions').classList.remove('d-none');
                document.getElementById('editActions').classList.add('d-none');
            }
        }

        document.getElementById('btnEditPost').addEventListener('click', function() {
            switchViewMode('edit');
        });

        document.getElementById('btnCancelEdit').addEventListener('click', function() {
            switchViewMode('view');
        });

        // Delete Post AJAX
        document.getElementById('btnDeletePost').addEventListener('click', async function() {
            if (!currentEvent) return;

            if (!confirm("<?= addslashes(__('confirm_delete_scheduled_post') ?? 'Are you sure you want to delete this scheduled post?') ?>")) return;

            const postId = currentEvent.extendedProps.postId;
            const formData = new FormData();
            formData.append('id', postId);

            try {
                const response = await fetch('/calendar/delete', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    currentEvent.remove();
                    eventModalInstance.hide();
                } else {
                    alert("Error: " + data.error);
                }
            } catch (err) {
                alert("<?= addslashes(__('failed_delete_post_connection') ?? 'Failed to delete post. Connection error.') ?>");
            }
        });

        // Save Edit Post AJAX
        document.getElementById('btnSavePost').addEventListener('click', async function() {
            if (!currentEvent) return;

            const postId = currentEvent.extendedProps.postId;
            const newContent = document.getElementById('editContent').value;
            const newDate = document.getElementById('editScheduledFor').value;

            if (!newContent.trim()) {
                alert("<?= addslashes(__('content_cannot_be_empty') ?? 'Content cannot be empty.') ?>");
                return;
            }

            const formData = new FormData();
            formData.append('id', postId);
            formData.append('content', newContent);
            formData.append('scheduled_for', newDate);

            try {
                const response = await fetch('/calendar/update', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    currentEvent.setExtendedProp('fullContent', newContent);
                    currentEvent.setExtendedProp('scheduledFor', newDate.replace('T', ' '));
                    
                    let truncatedTitle = newContent.replace(/<[^>]*>?/gm, '');
                    if (truncatedTitle.length > 30) truncatedTitle = truncatedTitle.substring(0, 30) + '...';
                    currentEvent.setProp('title', currentEvent.extendedProps.platform + ': ' + truncatedTitle);
                    
                    if (newDate) {
                        currentEvent.setStart(newDate);
                    }

                    eventModalInstance.hide();
                } else {
                    alert("Error: " + data.error);
                }
            } catch (err) {
                alert("<?= addslashes(__('failed_update_post_connection') ?? 'Failed to update post. Connection error.') ?>");
            }
        });
    });
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>