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
        /* Ensure smooth background transition matching the active theme */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #ffffff;
        }
        html[data-bs-theme="dark"],
        html[data-bs-theme="dark"] body {
            background-color: #212529;
        }
    </style>
    <script>
        // Resolve the effective theme ('dark' or 'light') from Bootstrap 5 / localStorage / OS preference
        function getResolvedTheme() {
            // 1. Check if parent window (when inside iframe) already has data-bs-theme set
            try {
                if (window.parent && window.parent !== window && window.parent.document) {
                    const parentTheme = window.parent.document.documentElement.getAttribute('data-bs-theme');
                    if (parentTheme === 'dark' || parentTheme === 'light') {
                        return parentTheme;
                    }
                }
            } catch (e) {
                // Fallback to localStorage if cross-origin access is restricted
            }

            // 2. Read saved theme preference from localStorage (supports common key names)
            const storedTheme = localStorage.getItem('theme') 
                             || localStorage.getItem('bs-theme') 
                             || localStorage.getItem('app_theme') 
                             || 'auto';

            if (storedTheme === 'dark' || storedTheme === 'light') {
                return storedTheme;
            }

            // 3. Fallback to Operating System preference ('auto')
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        // Apply initial theme to <html> immediately to prevent white flash
        document.documentElement.setAttribute('data-bs-theme', getResolvedTheme());
    </script>
</head>
<body style="margin: 0; padding: 0;">

    <div id="fm-container" name="fm-container" style="text-align:left;" align="left"></div>

    <script src="/public/assets/My-File-Manager/js/myfilemanager.min.js"></script>
    <script src="/public/assets/My-File-Manager/js/i18n.min.js"></script>

    <script>
    // Helper function to send the selected file to the Post Composer
    function sendToComposer(fileName) {
        // Dynamically build base URL from Config::APP_URL and current user session
        const appBaseUrl = '<?= rtrim(Config::APP_URL, "/") ?>';
        const userId = '<?= (int)($_SESSION["user_id"] ?? 0) ?>';
        const fileUrl = appBaseUrl + '/public/assets/My-File-Manager/files/user_' + userId + '/' + fileName;
        
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
                sendToComposer(file.name);
            }
        },
        
        // Custom file opener (double-click handler)
        customFileOpener: function(file) {
            sendToComposer(file.name);
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

    // 1. Listen for localStorage changes from parent window / other tabs
    window.addEventListener('storage', function(e) {
        if (!e.key || e.key === 'theme' || e.key === 'bs-theme' || e.key === 'app_theme') {
            syncFileManagerTheme();
        }
    });

    // 2. Observe parent <html> data-bs-theme attribute changes in real time (same-window sidebar toggle)
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

    // 3. Listen for OS-level dark/light mode switches when in 'auto' mode
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
        syncFileManagerTheme();
    });
    </script>
</body>
</html>
