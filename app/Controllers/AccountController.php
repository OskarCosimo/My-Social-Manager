<?php
// app/Controllers/AccountController.php

require_once __DIR__ . '/../Core/PluginManager.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/MediaFallbackService.php';

class AccountController {
    
    /**
     * Render the user social accounts management page
     */
    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];

        $limits = UsageManager::getUserLimits($userId);
        $connectedAccounts = SocialAccount::getUserAccounts($userId);

        $pluginManager = new PluginManager();
        $availablePlugins = $pluginManager->getActivePlugins();

        $now = new DateTime();
        foreach ($connectedAccounts as &$account) {
            $account['status_badge'] = 'unknown';
            $account['status_label'] = 'Connected';
            $account['days_left'] = null;

            if (!empty($account['token_expires_at'])) {
                $expiresAt = new DateTime($account['token_expires_at']);
                $diff = $now->diff($expiresAt);
                $isExpired = ($now >= $expiresAt);

                if ($isExpired) {
                    $account['status_badge'] = 'expired';
                    $account['status_label'] = 'Expired';
                    $account['days_left'] = 0;
                } else {
                    $totalHours = ($diff->days * 24) + $diff->h;
                    $daysRemaining = (int)$diff->format('%r%a');

                    if ($totalHours < 24) {
                        $account['status_badge'] = 'valid';
                        $account['days_left'] = 1;
                        $account['status_label'] = ($totalHours > 0) ? "Valid ({$totalHours}h left)" : "Valid ({$diff->i}m left)";
                    } elseif ($daysRemaining <= 7) {
                        $account['status_badge'] = 'expiring';
                        $account['days_left'] = $daysRemaining;
                        $account['status_label'] = "Expiring in {$daysRemaining}d";
                    } else {
                        $account['status_badge'] = 'valid';
                        $account['days_left'] = $daysRemaining;
                        $account['status_label'] = "Valid ({$daysRemaining}d left)";
                    }
                }
            }

            // Plugin capability flags and global fallback references
            $plugin = $pluginManager->getPlugin($account['plugin_id']);
            $account['supports_live_check'] = ($plugin && method_exists($plugin, 'checkTokenStatus'));
            $account['is_video_network'] = MediaFallbackService::isVideoRequired($account['plugin_id']);
            $account['is_image_network'] = MediaFallbackService::isImageRequired($account['plugin_id']);
            $account['supports_media_fallback'] = ($account['is_video_network'] || $account['is_image_network']);

            // Get global admin default as reference
            $account['global_admin_default'] = $account['is_video_network'] 
                ? MediaFallbackService::getDefaultVideo($account['plugin_id']) 
                : MediaFallbackService::getDefaultImage($account['plugin_id']);
        }
        unset($account);

        require_once __DIR__ . '/../Views/accounts/index.php';
    }

    /**
     * Save account-specific custom default media (video or image)
     */
    public function saveDefaultMedia(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $accountId = (int)($_POST['account_id'] ?? 0);
        $defaultMedia = trim($_POST['default_media'] ?? '');

        if ($accountId <= 0) {
            $_SESSION['error'] = "Invalid account ID.";
            header('Location: /accounts');
            exit;
        }

        if (SocialAccount::updateDefaultMedia($accountId, $userId, $defaultMedia)) {
            $_SESSION['success'] = "Custom fallback media updated for this account.";
        } else {
            $_SESSION['error'] = "Failed to update custom fallback media.";
        }

        header('Location: /accounts');
        exit;
    }

    /**
     * Verify token status live via the plugin (JSON response)
     */
    public function verifyToken(): void {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        header('Content-Type: application/json');

        $userId = (int)$_SESSION['user_id'];
        $accountId = (int)($_POST['account_id'] ?? 0);

        $account = SocialAccount::getAccountById($accountId, $userId);
        if (!$account) {
            echo json_encode(['success' => false, 'message' => 'Account not found.']);
            exit;
        }

        $pluginManager = new PluginManager();
        $plugin = $pluginManager->getPlugin($account['plugin_id']);

        if (!$plugin || !method_exists($plugin, 'checkTokenStatus')) {
            echo json_encode(['success' => false, 'message' => 'This platform does not support live token checking.']);
            exit;
        }

        try {
            $status = $plugin->checkTokenStatus($account['access_token']);

            if (isset($status['expires_at'])) {
                SocialAccount::updateTokenExpiry($accountId, $userId, $status['expires_at']);
            }

            echo json_encode([
                'success' => true,
                'valid' => (bool)($status['valid'] ?? false),
                'message' => $status['message'] ?? ($status['valid'] ? 'Token is valid' : 'Token expired or invalid')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Render the manual setup form dynamically by loading the requested plugin
     */
    public function manualSetup(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
    
        if (!UsageManager::canAddAccount($userId)) {
            $_SESSION['error'] = "You have reached the maximum number of accounts for your plan. Please upgrade.";
            header('Location: /accounts');
            exit;
        }

        $pluginId = $_GET['platform'] ?? '';
        $pluginManager = new PluginManager();
        $plugin = $pluginManager->getPlugin($pluginId);

        if (!$plugin) {
            $_SESSION['error'] = "Invalid platform selected.";
            header('Location: /accounts');
            exit;
        }

        if (!method_exists($plugin, 'getManualSetupHtml')) {
            $_SESSION['error'] = "This platform does not support manual configuration.";
            header('Location: /accounts');
            exit;
        }

        $pluginFormHtml = $plugin->getManualSetupHtml();
        $platform = $plugin->getId();
        
        require_once __DIR__ . '/../Views/accounts/manual_setup.php';
    }
    
    /**
     * Disconnect a social account from the user profile
     */
    public function disconnect(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        $accountId = (int)($_POST['account_id'] ?? 0);
        $userId = (int)$_SESSION['user_id'];
        
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM social_accounts WHERE id = ? AND user_id = ?");
        
        if ($stmt->execute([$accountId, $userId])) {
            $_SESSION['success'] = "Account disconnected successfully.";
        } else {
            $_SESSION['error'] = "Failed to disconnect account.";
        }
        
        header('Location: /accounts');
        exit;
    }
}
