<?php
// app/Views/filemanager/standalone.php
if (!isset($_SESSION['user_id'])) exit;

require_once __DIR__ . '/../../Core/Config.php';

$currentLang = $_COOKIE['lang'] ?? $_SESSION['app_language'] ?? (class_exists('Config') ? Config::DEFAULT_LANG : 'en');
$validToken  = "rh4h45yh4hyà4y]hà45yhà45yh4r5thyò4r@5thyàòe4r5thye4r5thygòe4r5ty#hge5r";
$currentUserId = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('file_manager') ?? 'File Manager' ?></title>
    <link rel="stylesheet" href="/public/assets/My-File-Manager/css/myfilemanager.min.css" />
    <style>
        * {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #ffffff;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        html[data-bs-theme="dark"],
        html[data-bs-theme="dark"] body {
            background-color: #212529;
        }
        /* Fullscreen container without extra outer scrollbars */
        #fm-container {
            width: 100%;
            height: 100vh;
            overflow: hidden;
            text-align: left;
        }

        /* Media Preview Modal Styles (Light & Dark theme aware) */
        .fm-preview-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .fm-preview-backdrop.open {
            display: flex;
        }
        .fm-preview-dialog {
            background: #ffffff;
            color: #212529;
            border-radius: 10px;
            max-width: 90vw;
            max-height: 90vh;
            width: 820px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.15);
        }
        html[data-bs-theme="dark"] .fm-preview-dialog {
            background: #212529;
            color: #f8f9fa;
            border-color: rgba(255, 255, 255, 0.15);
        }
        .fm-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid rgba(128, 128, 128, 0.25);
        }
        .fm-preview-title {
            font-weight: 600;
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-right: 12px;
        }
        .fm-preview-close {
            background: transparent;
            border: none;
            color: inherit;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            opacity: 0.75;
            padding: 2px 6px;
        }
        .fm-preview-close:hover {
            opacity: 1;
        }
        .fm-preview-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: #f1f3f5;
            overflow: auto;
            min-height: 260px;
        }
        html[data-bs-theme="dark"] .fm-preview-body {
            background: #16181b;
        }
        .fm-preview-body img,
        .fm-preview-body video {
            max-width: 100%;
            max-height: 68vh;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }
        .fm-preview-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 12px 18px;
            border-top: 1px solid rgba(128, 128, 128, 0.25);
        }
        .fm-btn {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .fm-btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        .fm-btn-secondary:hover {
            background: #5c636a;
        }
        .fm-btn-primary {
            background: #0d6efd;
            color: #fff;
        }
        .fm-btn-primary:hover {
            background: #0b5ed7;
        }
    </style>
    <script>
        // Resolve the effective theme ('dark' or 'light') from Bootstrap 5 / localStorage / OS preference
        function getResolvedTheme() {
            try {
                if (window.parent && window.parent !== window && window.parent.document) {
                    const parentTheme = window.parent.document.documentElement.getAttribute('data-bs-theme');
                    if (parentTheme === 'dark' || parentTheme === 'light') {
                        return parentTheme;
                    }
                }
            } catch (e) {}

            const storedTheme = localStorage.getItem('theme') 
                             || localStorage.getItem('bs-theme') 
                             || localStorage.getItem('app_theme') 
                             || 'auto';

            if (storedTheme === 'dark' || storedTheme === 'light') {
                return storedTheme;
            }

            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        // Apply initial theme to <html> immediately to prevent white flash
        document.documentElement.setAttribute('data-bs-theme', getResolvedTheme());
    </script>
</head>
<body>

    <div id="fm-container" name="fm-container"></div>

    <!-- Media Preview Modal -->
    <div id="mediaPreviewModal" class="fm-preview-backdrop" role="dialog" aria-modal="true" aria-labelledby="previewModalTitle">
        <div class="fm-preview-dialog">
            <div class="fm-preview-header">
                <div id="previewModalTitle" class="fm-preview-title">Preview</div>
                <button type="button" class="fm-preview-close" id="btnClosePreviewTop" aria-label="Close">&times;</button>
            </div>
            <div class="fm-preview-body" id="previewModalBody"></div>
            <div class="fm-preview-footer">
                <button type="button" class="fm-btn fm-btn-secondary" id="btnClosePreviewBottom"><?= __('close') ?? 'Close' ?></button>
                <button type="button" class="fm-btn fm-btn-primary" id="btnUseInPostPreview"><?= __('use_in_post') ?? 'Use in Post' ?></button>
            </div>
        </div>
    </div>

    <script src="/public/assets/My-File-Manager/js/myfilemanager.min.js"></script>
    <script src="/public/assets/My-File-Manager/js/i18n.min.js"></script>

    <script>
    const appBaseUrl = '<?= rtrim(Config::APP_URL, "/") ?>';
    const currentUserId = '<?= $currentUserId ?>';
    let currentPreviewFile = null;

    // Build public file URL including current subdirectory path if present
    function getPublicFileUrl(file) {
        const relativePath = file.path ? file.path.replace(/^\/+/, '') : file.name;
        const encodedPath = relativePath.split('/').map(encodeURIComponent).join('/');
        return appBaseUrl + '/public/assets/My-File-Manager/files/user_' + currentUserId + '/' + encodedPath;
    }

    // Helper function to send the selected file to the Post Composer
    function sendToComposer(fileOrName) {
        const fileUrl = (typeof fileOrName === 'object' && fileOrName !== null)
            ? getPublicFileUrl(fileOrName)
            : appBaseUrl + '/public/assets/My-File-Manager/files/user_' + currentUserId + '/' + encodeURIComponent(fileOrName);
        
        // If running inside modal iframe, dispatch postMessage to parent container
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'file_selected',
                url: fileUrl
            }, '*');
        } else {
            // Direct tab fallback
            localStorage.setItem('pending_file_url', fileUrl);
            window.location.href = '/compose';
        }
    }

    // Open image or video preview modal
    function openMediaPreview(file) {
        const modal = document.getElementById('mediaPreviewModal');
        const titleEl = document.getElementById('previewModalTitle');
        const bodyEl = document.getElementById('previewModalBody');
        const fileUrl = getPublicFileUrl(file);
        const mime = (file.mime || '').toLowerCase();
        const ext = (file.name || '').split('.').pop().toLowerCase();

        const isImage = mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext);
        const isVideo = mime.startsWith('video/') || ['mp4', 'webm', 'ogg', 'mov', 'm4v'].includes(ext);

        currentPreviewFile = file;
        titleEl.textContent = file.name;
        bodyEl.innerHTML = '';

        if (isImage) {
            const img = document.createElement('img');
            img.src = fileUrl;
            img.alt = file.name;
            bodyEl.appendChild(img);
        } else if (isVideo) {
            const video = document.createElement('video');
            video.src = fileUrl;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            bodyEl.appendChild(video);
        }

        modal.classList.add('open');
    }

    // Close preview modal and stop any playing video
    function closeMediaPreview() {
        const modal = document.getElementById('mediaPreviewModal');
        const bodyEl = document.getElementById('previewModalBody');
        const video = bodyEl.querySelector('video');
        if (video) {
            video.pause();
            video.src = '';
        }
        bodyEl.innerHTML = '';
        currentPreviewFile = null;
        modal.classList.remove('open');
    }

    // Modal event listeners
    document.getElementById('btnClosePreviewTop').addEventListener('click', closeMediaPreview);
    document.getElementById('btnClosePreviewBottom').addEventListener('click', closeMediaPreview);
    document.getElementById('mediaPreviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeMediaPreview();
        }
    });
    document.getElementById('btnUseInPostPreview').addEventListener('click', function() {
        if (currentPreviewFile) {
            const fileToSend = currentPreviewFile;
            closeMediaPreview();
            sendToComposer(fileToSend);
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('mediaPreviewModal').classList.contains('open')) {
            closeMediaPreview();
        }
    });

    const token_bearer_header = '<?= $validToken ?>';
    const appLanguage = '<?= htmlspecialchars($currentLang) ?>';
    const initialTheme = getResolvedTheme();

    var fm = new MyFileManager('#fm-container', {
        // Backend settings
        url: '/public/assets/My-File-Manager/src/connector.php',
        debug: false,
        autoRefresh: 8000,
        homeLabel: '<?= addslashes(strtoupper(__('home') ?? 'HOME')) ?>',
        
        // UI settings with localized user language and synced Bootstrap 5 theme
        lang: appLanguage,
        theme: initialTheme,
        viewMode: 'list',

        // Hide sensitive and runtime file extensions
        hideExtensions: ['.tmp', '.log', '.cache', '.bak', '.sh'],

        // Branding configuration
        brandLogo: '/public/assets/My-File-Manager/assets/myfilemanager_logo_icon.jpeg',
        brandLink: 'https://github.com/OskarCosimo/My-Social-Manager',
        brandTarget: '_blank',
        
        // Upload limits
        maxFileSize: '524288000', // 500MB in bytes
        chunkSize: 1048576,        // 1MB chunks

        // Feature toggles
        features: {
            upload: true,
            download: true,
            delete: true,
            rename: true,
            copy: true,
            cut: true,
            paste: true,
            mkdir: true,
            search: true,
            info: true,
            contextMenu: true
        },
        
        // Custom context menu item translated
        customContextMenu: [
            {
                label: '<?= addslashes(__('use_in_post') ?? 'Use in Post') ?>',
                action: 'custom-edit'
            }
        ],

        // Custom navigation menus translated
        customMenus: [
            {
                id: 'settings',
                label: '<?= addslashes(__('settings') ?? 'Settings') ?>',
                position: 3,
                items: [
                    {
                        id: 'space-settings',
                        label: '<?= addslashes(__('storage_settings') ?? 'Storage Space') ?>',
                        icon: '💾',
                        action: 'spaceSettings'
                    },
                    {
                        id: 'options',
                        label: '<?= addslashes(__('options') ?? 'Options') ?>',
                        icon: '⚙️',
                        action: 'options'
                    }
                ]
            }
        ],
        
        // Callback for custom menu actions
        onCustomMenuAction: function(action, item) {
            if (action === 'spaceSettings' && typeof openspaceSettingsModal === 'function') {
                openspaceSettingsModal();
            } else if (action === 'options' && typeof openoptionSettingsModal === 'function') {
                openoptionSettingsModal();
            }
        },
        
        // Event listeners
        onInit: function() {
            console.log('File manager initialized in locale: ' + appLanguage + ' | theme: ' + initialTheme);
        },
        onUploadStart: function(data) {
            console.log('Upload started:', data.file.name);
        },
        onUploadProgress: function(data) {
            console.log('Progress:', data.totalProgress + '%');
        },
        onUploadComplete: function(data) {
            console.log('Upload completed:', data.file.name);
        },
        onUploadError: function(data) {
            console.error('Upload error:', data.error);
        },
        onError: function(error) {
            console.error('File manager error:', error);
        },
        onContextMenuAction: function(data) {
            const file = data.files[0];
            if (data.action === 'custom-edit') {
                sendToComposer(file);
            }
        },
        
        // Custom file opener (double-click handler)
        customFileOpener: function(file) {
            const mime = (file.mime || '').toLowerCase();
            const ext = (file.name || '').split('.').pop().toLowerCase();
            const isImage = mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext);
            const isVideo = mime.startsWith('video/') || ['mp4', 'webm', 'ogg', 'mov', 'm4v'].includes(ext);

            if (isImage || isVideo) {
                openMediaPreview(file);
            } else {
                sendToComposer(file);
            }
        }
    });

    // Sync File Manager theme dynamically when Bootstrap theme changes
    function syncFileManagerTheme() {
        const newTheme = getResolvedTheme();
        document.documentElement.setAttribute('data-bs-theme', newTheme);
        
        if (fm && fm.options && fm.options.theme !== newTheme) {
            fm.options.theme = newTheme;
            if (typeof fm.applyTheme === 'function') {
                fm.applyTheme();
            }
        }
    }

    window.addEventListener('storage', function(e) {
        if (!e.key || e.key === 'theme' || e.key === 'bs-theme' || e.key === 'app_theme') {
            syncFileManagerTheme();
        }
    });

    try {
        if (window.parent && window.parent !== window && window.parent.document) {
            const parentHtml = window.parent.document.documentElement;
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'data-bs-theme') {
                        syncFileManagerTheme();
                    }
                });
            });
            observer.observe(parentHtml, { attributes: true, attributeFilter: ['data-bs-theme'] });
        }
    } catch (e) {}

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
        syncFileManagerTheme();
    });
    </script>
</body>
</html>
