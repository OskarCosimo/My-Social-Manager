<?php
// app/Controllers/DashboardController.php

require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Models/Plan.php';
require_once __DIR__ . '/../Models/MailingList.php';

class DashboardController {
    
    /**
     * Render the main dashboard with usage stats and pricing table
     */
    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        
        // Fetch the user's current limits and usage data
        $limits = UsageManager::getUserLimits($userId);
        
        // Fetch current mailing list count for this user
        $userLists = MailingList::getUserLists($userId);
        $limits['current_mailing_lists'] = count($userLists);

        // Fetch all available plans to build the pricing UI
        $plans = Plan::getAll();

        // Handle feedback messages from Stripe redirection
        $paymentStatus = $_GET['payment'] ?? '';
        if ($paymentStatus === 'success') {
            $_SESSION['success'] = "Payment successful! Your plan has been upgraded.";
            // Redirect to clean the URL
            header('Location: /dashboard');
            exit;
        } elseif ($paymentStatus === 'cancelled') {
            $_SESSION['error'] = "Payment was cancelled. No charges were made.";
            header('Location: /dashboard');
            exit;
        }

        // Fetch translations if we are implementing the multi-language system now
        // For now, we will pass some basic variables to the view
        $userEmail = $_SESSION['user_email'] ?? 'User';

        require_once __DIR__ . '/../Views/dashboard/index.php';
    }
}