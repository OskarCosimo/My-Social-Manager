<?php
// app/Controllers/HomeController.php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/SystemSetting.php';

class HomeController {
    /**
     * Render the public landing page
     */
    public function index(): void {
        // Pass session status to the view
        $isLoggedIn = isset($_SESSION['user_id']);
        
        // Fetch plans from the database to display on the homepage
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM plans ORDER BY price ASC");
        $plans = $stmt->fetchAll();
        
        require_once __DIR__ . '/../Views/home/index.php';
    }

    /**
     * Render or redirect to the Privacy Policy page
     */
    public function privacyPolicy(): void {
        $this->renderLegalPage('privacy');
    }

    /**
     * Render or redirect to the Terms and Conditions page
     */
    public function terms(): void {
        $this->renderLegalPage('terms');
    }

    /**
     * Handle internal rendering or external redirect for legal pages
     */
    private function renderLegalPage(string $type): void {
        $mode = SystemSetting::get($type . '_mode', 'disabled');

        // Redirect directly if configured as an external URL
        if ($mode === 'external') {
            $externalUrl = trim(SystemSetting::get($type . '_url', ''));
            $scheme = strtolower((string)parse_url($externalUrl, PHP_URL_SCHEME));

            if (!empty($externalUrl) && filter_var($externalUrl, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true)) {
                header('Location: ' . $externalUrl);
                exit;
            }
        }

        $isLoggedIn = isset($_SESSION['user_id']);
        $isNotAvailable = ($mode !== 'internal');

        if ($isNotAvailable) {
            http_response_code(404);
        }

        if ($type === 'privacy') {
            $pageTitle = __('privacy_policy') ?: 'Privacy Policy';
            $pageIcon  = 'bi-shield-lock';
        } else {
            $pageTitle = __('terms_and_conditions') ?: 'Terms and Conditions';
            $pageIcon  = 'bi-file-check';
        }

        $legalContent = $isNotAvailable ? '' : SystemSetting::get($type . '_content', '');

        require_once __DIR__ . '/../Views/home/legal.php';
    }
}
