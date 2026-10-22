<?php
// app/Views/filemanager/standalone.php
if (!isset($_SESSION['user_id'])) exit;

require_once __DIR__ . '/../../Core/Config.php';

// Resolve the active language priority: Cookie -> Session -> Default config
$currentLang = $_COOKIE['lang'] ?? $_SESSION['app_language'] ?? (class_exists('Config') ? Config::DEFAULT_LANG : 'en');
$validToken  = "rh4h45yh4hyà4y]hà45yhà45yh4r5thyò4r@5thyàòe4r5thye4r5thygòe4r5ty#hge5r";
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('file_manager') ?? 'File Manager' ?></title>
    <link rel="stylesheet" href="https://www.myetv.tv/CDN/myetv-file-manager/css/myfilemanager.min.css?v=14" />
</head>
<body style="margin: 0; padding: 0;">

    <div id="fm-container" name="fm-container" style="text-align:left;" align="left"></div>

    <script src="https://www.myetv.tv/CDN/myetv-file-manager/js/myfilemanager.min.js?v=14"></script>
    <script src="https://www.myetv.tv/CDN/myetv-file-manager/js/i18n.min.js" integrity="sha512-nIO9Dwx78lHCDg5KuucL4NSa9nGogW0D1yKHuqE6GSoirca2X/aCxufNPGQcZgFwhdz1XthOa77SJX/LDOPcWg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
    // Helper function to send the selected file to the Post Composer
    function sendToComposer(fileName) {
        const baseUrl = 'https://www.mysocialmanager.myetv.tv/public/assets/My-File-Manager/files/';
        const fileUrl = baseUrl + fileName;
        
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

    var fm = new MyFileManager('#fm-container', {
        // Backend settings
        url: '/public/assets/My-File-Manager/src/connector.php',
        debug: false,
        autoRefresh: 8000,
        homeLabel: '<?= addslashes(strtoupper(__('home') ?? 'HOME')) ?>',
        
        // UI settings with localized user language
        lang: appLanguage,
        theme: 'light',
        viewMode: 'list',

        // Hide sensitive and runtime file extensions
        hideExtensions: ['.tmp', '.log', '.cache', '.bak', '.sh'],

        // Branding configuration
        brandLogo: 'https://www.myetv.tv/CDN/assets/images/LogoMYETV_trademark_120px.png',
        brandLink: 'https://www.myetv.tv',
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
            console.log('File manager initialized in locale: ' + appLanguage);
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
    </script>
</body>
</html>