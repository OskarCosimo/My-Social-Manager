<?php
// app/Controllers/SettingsController.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/User.php';

class SettingsController {

    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // Fetch current logged in user from database
        $userId = (int)$_SESSION['user_id'];
        $user = User::findById($userId);

        // Get available languages from the lang directory
        $langDir = __DIR__ . '/../../lang/';
        $availableLanguages = [];
        if (is_dir($langDir)) {
            $files = scandir($langDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $availableLanguages[] = basename($file, '.json');
                }
            }
        }

        // Fetch current preferences from session or fallback to user model / defaults
        $currentLang = $_SESSION['app_language'] ?? ($user['language'] ?? Config::DEFAULT_LANG);
        $userTz = $_SESSION['user_timezone'] ?? ($user['timezone'] ?? 'UTC');

        require_once __DIR__ . '/../Views/settings/index.php';
    }

    /**
     * Save user preferences including language and timezone
     */
    public function save(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $newLang = $_POST['language'] ?? 'en';
        $timezone = $_POST['timezone'] ?? 'UTC';

        // Sanitize language input to prevent directory traversal or injection
        $newLang = preg_replace('/[^a-z]/', '', $newLang);

        // Validate timezone against native PHP timezone identifiers
        if (!in_array($timezone, timezone_identifiers_list())) {
            $timezone = 'UTC';
        }

        // 1. Set global cookie valid for the entire platform (365 days) across all paths
        setcookie('lang', $newLang, time() + (86400 * 365), '/');

        // 2. Persist the new settings to the database
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET language = ?, timezone = ? WHERE id = ?");
        
        if ($stmt->execute([$newLang, $timezone, $userId])) {
            // Update session variables so the UI reflects changes immediately
            $_SESSION['app_language'] = $newLang;
            $_SESSION['user_timezone'] = $timezone;
            $_SESSION['success'] = "Settings updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update settings.";
        }

        // Redirect back to settings page
        header('Location: /settings');
        exit;
    }
}