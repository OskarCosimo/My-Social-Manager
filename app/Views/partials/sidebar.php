<?php
// app/Views/partials/sidebar.php
$activeMenu = $activeMenu ?? '';
$userRole = $_SESSION['user_role'] ?? 'user';

// Ensure SystemSetting is loaded
if (!class_exists('SystemSetting')) {
    require_once __DIR__ . '/../../Models/SystemSetting.php';
}

// Fetch configured logo or fallback to default /public/assets/images/mysocialmanager_logo.png
$customLogo = SystemSetting::get('app_logo');
$appLogoUrl = !empty($customLogo) ? $customLogo : '/public/assets/images/mysocialmanager_logo.png';

// Check if admin chose to hide the sidebar logo
$hideSidebarLogo = (SystemSetting::get('hide_sidebar_logo') ?? '0') === '1';
?>
<style>
    /* Base Sidebar Styles */
    #main-sidebar { 
        width: 260px; 
        min-height: 100vh; 
        background-color: #2c3e50; 
        transition: width 0.3s ease; 
        flex-shrink: 0; 
        overflow-x: hidden; 
        display: flex;
        flex-direction: column;
    }
    
    /* Collapsed State */
    #main-sidebar.collapsed { 
        width: 80px; 
    }
    
    /* Links and Layout */
    #main-sidebar a { 
        color: #ced4da; 
        text-decoration: none; 
        display: flex; 
        align-items: center; 
        padding: 10px 14px; 
        border-radius: 6px; 
        margin-bottom: 4px; 
        white-space: normal; /* Allow text wrapping */
        word-break: break-word;
        line-height: 1.3;
        transition: background-color 0.2s, color 0.2s, padding 0.3s;
    }
    #main-sidebar a:hover, #main-sidebar a.active { 
        background-color: #34495e; 
        color: #fff; 
    }
    
    /* Perfect Vertical Alignment for Menu Icons */
    #main-sidebar .menu-icon { 
        font-size: 1.25rem; 
        width: 28px; 
        height: 28px;
        flex-shrink: 0; /* Never shrink or distort icon when text wraps */
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px; 
        transition: margin 0.3s;
    }
    #main-sidebar .menu-text {
        opacity: 1;
        font-size: 0.95rem;
        flex-grow: 1;
        transition: opacity 0.2s ease;
    }

    /* Responsive App Title */
    .app-title {
        font-size: 1.05rem; 
        white-space: normal;
        word-break: break-word;
        line-height: 1.3;
        transition: opacity 0.2s ease;
    }

    /* Elements to hide/adjust when collapsed */
    #main-sidebar.collapsed .menu-text,
    #main-sidebar.collapsed .sidebar-heading,
    #main-sidebar.collapsed .app-title { 
        display: none; 
    }
    #main-sidebar.collapsed a { 
        padding: 12px 0; 
        justify-content: center; 
    }
    #main-sidebar.collapsed .menu-icon { 
        margin-right: 0; 
    }
    #main-sidebar.collapsed .toggle-header {
        justify-content: center !important;
    }

    .sidebar-heading { 
        font-size: 0.72rem; 
        letter-spacing: 0.08em; 
        color: #8e9bb0; 
        padding: 0 14px; 
        white-space: nowrap; 
    }
</style>

<nav id="main-sidebar" class="sidebar py-4">
    
    <!-- Inline script to apply collapsed state immediately and prevent layout flickering -->
    <script>
        (function() {
            const isMobile = window.innerWidth < 768;
            const savedState = localStorage.getItem('sidebar_collapsed');
            
            // Auto-collapse on small screens, OR if the user previously saved it as collapsed
            if (isMobile || savedState === 'true') {
                document.getElementById('main-sidebar').classList.add('collapsed');
            }
        })();
    </script>

    <div class="px-3 d-flex flex-column h-100">
        
        <!-- Header with Title and Toggle Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 toggle-header">
            <?php if (!$hideSidebarLogo): ?>
                <div class="app-title text-white fw-bold pe-2">
                    <a class="navbar-brand p-0" href="/">
                        <img style="width:200px; height:50px; object-fit: contain;" title="<?= __('app_title') ?>" src="<?= htmlspecialchars($appLogoUrl) ?>" alt="Logo" />
                    </a>
                </div>
            <?php endif; ?>
            <button id="sidebarToggleBtn" class="btn btn-sm text-white border-0 shadow-none" style="position:fixed;top:0px;left:0px;">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>
        
        <!-- User Menu -->
        <ul class="list-unstyled mb-4">
            <li>
                <a href="/dashboard" class="<?= $activeMenu === 'dashboard' ? 'active fw-bold text-white' : '' ?>" title="<?= __('dashboard') ?>">
                    <span class="menu-icon"><i class="bi bi-speedometer2"></i></span> 
                    <span class="menu-text"><?= __('dashboard') ?></span>
                </a>
            </li>
            <li>
                <a href="/accounts" class="<?= $activeMenu === 'accounts' ? 'active fw-bold text-white' : '' ?>" title="<?= __('social_accounts') ?? 'Social Accounts' ?>">
                    <span class="menu-icon"><i class="bi bi-share"></i></span> 
                    <span class="menu-text"><?= __('social_accounts') ?></span>
                </a>
            </li>
            <li>
                <a href="/mailinglists" class="<?= $activeMenu === 'mailinglists' ? 'active fw-bold text-white' : '' ?>" title="Mailing Lists">
                    <span class="menu-icon"><i class="bi bi-envelope-paper"></i></span>
                    <span class="menu-text">Mailing Lists</span>
                </a>
            </li>
            <li>
                <a href="/groups" class="<?= ($activeMenu === 'groups') ? 'active fw-bold text-white' : '' ?>" title="<?= __('groups') ?? 'Groups' ?>">
                    <span class="menu-icon"><i class="bi bi-collection"></i></span>
                    <span class="menu-text"><?= __('groups') ?? 'Groups' ?></span>
                </a>
            </li>
            <li>
                <a href="/compose" class="<?= $activeMenu === 'compose' ? 'active fw-bold text-white' : '' ?>" title="<?= __('compose') ?? 'Compose' ?>">
                    <span class="menu-icon"><i class="bi bi-pencil-square"></i></span> 
                    <span class="menu-text"><?= __('compose') ?? 'Compose' ?></span>
                </a>
            </li>
            <li>
                <a href="/calendar" class="<?= $activeMenu === 'calendar' ? 'active fw-bold text-white' : '' ?>" title="<?= __('calendar') ?? 'Calendar' ?>">
                    <span class="menu-icon"><i class="bi bi-calendar3"></i></span> 
                    <span class="menu-text"><?= __('calendar') ?? 'Calendar' ?></span>
                </a>
            </li>
            <li>
                <a href="/ai-studio" class="<?= $activeMenu === 'ai-studio' ? 'active fw-bold text-white' : '' ?>" title="<?= __('aistudio') ?? 'AI Studio' ?>">
                    <span class="menu-icon"><i class="bi bi-robot"></i></span> 
                    <span class="menu-text"><?= __('aistudio') ?? 'AI Studio' ?></span>
                </a>
            </li>
            <li>
                <a href="/rss" class="<?= $activeMenu === 'rss' ? 'active fw-bold text-white' : '' ?>" title="<?= __('rss_feeds') ?? 'RSS Feeds' ?>">
                    <span class="menu-icon"><i class="bi bi-rss"></i></span> 
                    <span class="menu-text"><?= __('rss_feeds') ?? 'RSS Feeds' ?></span>
                </a>
            </li>
            <li>
                <a href="/channel-automations" class="<?= $activeMenu === 'channel_automations' ? 'active fw-bold text-white' : '' ?>" title="<?= __('channel_automations') ?? 'Channel Automations' ?>">
                    <span class="menu-icon"><i class="bi bi-broadcast-pin"></i></span> 
                    <span class="menu-text"><?= __('channel_automations') ?? 'Channel Automations' ?></span>
                </a>
            </li>
            <li>
                <a href="/filemanager" class="<?= $activeMenu === 'filemanager' ? 'active fw-bold text-white' : '' ?>" title="<?= __('file_manager') ?? 'File Manager' ?>">
                    <span class="menu-icon"><i class="bi bi-folder2-open"></i></span> 
                    <span class="menu-text"><?= __('file_manager') ?? 'File Manager' ?></span>
                </a>
            </li>
            <li>
                <a href="/settings" class="<?= $activeMenu === 'settings' ? 'active fw-bold text-white' : '' ?>" title="<?= __('settings') ?? 'Settings' ?>">
                    <span class="menu-icon"><i class="bi bi-gear"></i></span> 
                    <span class="menu-text"><?= __('settings') ?? 'Settings' ?></span>
                </a>
            </li>
        </ul>

        <!-- Admin Menu -->
        <?php if ($userRole === 'admin'): ?>
            <?php
            $isMailConfigured = false;
            
            // Safely check for the class and its methods to prevent Fatal Errors
            if (file_exists(__DIR__ . '/../../Models/SystemSetting.php')) {
                require_once __DIR__ . '/../../Models/SystemSetting.php';
                
                if (method_exists('SystemSetting', 'getAll')) {
                    $sysSettings = SystemSetting::getAll();
                    $isMailConfigured = !empty($sysSettings['smtp_sender']);
                } elseif (method_exists('SystemSetting', 'get')) {
                    $isMailConfigured = !empty(SystemSetting::get('smtp_sender'));
                }
            }
            ?>
            <h6 class="sidebar-heading text-uppercase mt-3 mb-2">Admin Tools</h6>
            <ul class="list-unstyled mb-4">
                <li>
                    <a href="/admin" class="<?= $activeMenu === 'admin' ? 'active fw-bold text-white' : '' ?>" title="<?= __('admin_panel') ?? 'Admin Panel' ?>">
                        <span class="menu-icon"><i class="bi bi-shield-lock"></i></span> 
                        <span class="menu-text"><?= __('admin_panel') ?? 'Admin Panel' ?></span>
                    </a>
                </li>
                <li>
                    <a href="/admin/billing" class="<?= $activeMenu === 'billing' ? 'active fw-bold text-white' : '' ?>" title="<?= __('billingandplans') ?? 'Billing and Plans' ?>">
                        <span class="menu-icon"><i class="bi bi-credit-card-2-front"></i></span> 
                        <span class="menu-text"><?= __('billingandplans') ?? 'Billing and Plans' ?></span>
                    </a>
                </li>
                <?php if ($isMailConfigured): ?>
                <li>
                    <a href="/newsletter" class="<?= $activeMenu === 'newsletter' ? 'active fw-bold text-white' : '' ?>" title="<?= __('newsletter') ?? 'Newsletter' ?>">
                        <span class="menu-icon"><i class="bi bi-send"></i></span> 
                        <span class="menu-text"><?= __('newsletter') ?? 'Newsletter' ?></span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <!-- Bottom Actions -->
        <ul class="list-unstyled mt-auto border-top border-secondary pt-3">
            <li class="mb-2">
                <a href="#" id="themeToggleBtn" title="Toggle Theme">
                    <span class="menu-icon" id="themeToggleIcon"><i class="bi bi-moon-stars"></i></span> 
                    <span class="menu-text" id="themeToggleText">Theme: Dark</span>
                </a>
            </li>
            
            <li>
                <a href="/logout" class="text-danger" title="<?= __('logout') ?? 'Logout' ?>">
                    <span class="menu-icon"><i class="bi bi-box-arrow-right"></i></span> 
                    <span class="menu-text"><?= __('logout') ?? 'Logout' ?></span>
                </a>
            </li>
        </ul>

    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- SIDEBAR COLLAPSE LOGIC ---
        const sidebar = document.getElementById('main-sidebar');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        let lastWidth = window.innerWidth;

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                if (window.innerWidth >= 768) {
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebar_collapsed', isCollapsed);
                }
            });
        }

        window.addEventListener('resize', function() {
            const currentWidth = window.innerWidth;
            const wasMobile = lastWidth < 768;
            const isMobile = currentWidth < 768;

            if (isMobile && !wasMobile) {
                sidebar.classList.add('collapsed');
            } else if (!isMobile && wasMobile) {
                const savedState = localStorage.getItem('sidebar_collapsed');
                if (savedState !== 'true') {
                    sidebar.classList.remove('collapsed');
                } else {
                    sidebar.classList.add('collapsed');
                }
            }
            lastWidth = currentWidth;
        });

        // --- THEME SWITCHER LOGIC ---
        const themeBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeToggleIcon');
        const themeText = document.getElementById('themeToggleText');

        // Helper to check system preference
        function getSystemTheme() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        // Apply theme and update UI text/icons
        function applyTheme(theme) {
            let effectiveTheme = theme;

            if (theme === 'auto') {
                effectiveTheme = getSystemTheme();
            }

            // Set Bootstrap 5 attribute
            document.documentElement.setAttribute('data-bs-theme', effectiveTheme);

            // Update UI elements in the sidebar with Bootstrap Icons
            if (themeIcon && themeText) {
                if (theme === 'dark') {
                    themeIcon.innerHTML = '<i class="bi bi-moon-stars"></i>';
                    themeText.textContent = 'Theme: Dark';
                } else if (theme === 'light') {
                    themeIcon.innerHTML = '<i class="bi bi-sun"></i>';
                    themeText.textContent = 'Theme: Light';
                } else {
                    themeIcon.innerHTML = '<i class="bi bi-display"></i>';
                    themeText.textContent = 'Theme: Auto';
                }
            }
        }

        // Initialize theme UI state on load
        let currentSavedTheme = localStorage.getItem('app_theme') || 'auto';
        applyTheme(currentSavedTheme);

        // Cycle through themes on click: Auto -> Dark -> Light -> Auto
        if (themeBtn) {
            themeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                let nextTheme = 'dark';
                const activeSetting = localStorage.getItem('app_theme') || 'auto';

                if (activeSetting === 'auto') {
                    nextTheme = 'dark';
                } else if (activeSetting === 'dark') {
                    nextTheme = 'light';
                } else if (activeSetting === 'light') {
                    nextTheme = 'auto';
                }

                localStorage.setItem('app_theme', nextTheme);
                applyTheme(nextTheme);
            });
        }

        // Listen for OS system theme changes dynamically if set to 'auto'
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
            const currentSetting = localStorage.getItem('app_theme') || 'auto';
            if (currentSetting === 'auto') {
                applyTheme('auto');
            }
        });
    });
</script>