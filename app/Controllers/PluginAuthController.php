<?php
// app/Controllers/PluginAuthController.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/PluginManager.php';
require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Models/PluginSetting.php';

class PluginAuthController {

    private PluginManager $pluginManager;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        $this->pluginManager = new PluginManager();
    }

    /**
     * Redirect user to the specific Social Network OAuth page or Force BYOK
     */
    public function redirect(): void {
        $pluginId = $_GET['id'] ?? '';
        $plugin = $this->pluginManager->getPlugin($pluginId);

        if (!$plugin) {
            $_SESSION['error'] = "Plugin not found or disabled.";
            header('Location: /accounts');
            exit;
        }

        // 1. Check if Plugin is actually enabled by the Admin
        $settings = PluginSetting::getForPlugin($pluginId);
        if (($settings['is_active'] ?? '0') !== '1') {
            $_SESSION['error'] = "This platform is currently disabled by the Administrator.";
            header('Location: /accounts');
            exit;
        }

        // 2. Determine if Global API Keys exist (if the plugin requires them)
        $fields = $plugin->getSettingsFields();
        $hasGlobalKeys = true;
        foreach ($fields as $key => $field) {
            if (empty(trim($settings[$key] ?? ''))) {
                $hasGlobalKeys = false;
                break; // Missing at least one key
            }
        }

        // 3. If keys are missing, FORCE manual BYOK setup!
        if (!$hasGlobalKeys && !empty($fields)) {
            // Bypass the plugin's getAuthUrl() entirely
            header("Location: /accounts/manual-setup?platform=" . urlencode($pluginId) . "&forced_byok=1");
            exit;
        }

        // 4. Otherwise, proceed as normal with the Plugin's native auth URL
        $authUrl = $plugin->getAuthUrl();
        header("Location: " . $authUrl);
        exit;
    }

    /**
     * Handle the callback from the Social Network
     */
    public function callback(): void {
        // Retrieve the plugin ID from GET, POST, or state parameter
        $pluginId = $_GET['id'] ?? $_GET['state'] ?? $_POST['state'] ?? '';
        
        try {
            $plugin = $this->pluginManager->getPlugin($pluginId);
            if (!$plugin) {
                throw new Exception("Invalid plugin configuration.");
            }

            // Gather all request data
            $requestData = array_merge($_GET, $_POST);
            
            // Let the plugin handle the data
            $accountData = $plugin->handleCallback($requestData);
                
            if (!empty($accountData['access_token'])) {
                require_once __DIR__ . '/../Models/UsageManager.php';
                if (!UsageManager::canAddAccount((int)$_SESSION['user_id'])) {
                    $_SESSION['error'] = "You have reached the maximum number of social accounts for your plan.";
                    header('Location: /accounts');
                    exit;
                }

                // Extract optional refresh token and expiration details
                $refreshToken = $accountData['refresh_token'] ?? null;
                $tokenExpiresAt = $accountData['token_expires_at'] ?? null;

                // If expires_in in seconds is provided, compute DATETIME
                if (empty($tokenExpiresAt) && !empty($accountData['expires_in'])) {
                    $tokenExpiresAt = date('Y-m-d H:i:s', time() + (int)$accountData['expires_in']);
                }

                // Save the token securely in the database
                SocialAccount::save(
                    (int)$_SESSION['user_id'],
                    $pluginId,
                    $accountData['platform_user_id'] ?? 'unknown',
                    $accountData['platform_name'] ?? $plugin->getName() . ' Account',
                    $accountData['access_token'],
                    $refreshToken,
                    $tokenExpiresAt
                );
                    
                $_SESSION['success'] = "Account connected successfully!";
            } else {
                $_SESSION['error'] = "Failed to retrieve access token.";
            }
        } catch (Exception $e) {
            error_log("Plugin Callback Error [{$pluginId}]: " . $e->getMessage());
            $_SESSION['error'] = "Authentication failed: " . $e->getMessage();
        }

        header('Location: /accounts');
        exit;
    }
}