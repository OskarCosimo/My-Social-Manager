<?php
// app/Controllers/BillingController.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/StripeClient.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Plan.php';

class BillingController {
    
    /**
     * Handle Plan Change (Upgrade or Downgrade / Switch to Free)
     */
    public function changePlan(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $newPlanId = (int)($_POST['plan_id'] ?? 0);
        $priceId = $_POST['price_id'] ?? '';

        if ($newPlanId <= 0) {
            $_SESSION['error'] = "Invalid plan selected.";
            header('Location: /dashboard');
            exit;
        }

        $db = Database::getInstance();

        // Fetch current user and plan info
        $stmt = $db->prepare("
            SELECT u.plan_id, u.current_period_end, p.price as current_price, p.name as current_plan_name 
            FROM users u 
            LEFT JOIN plans p ON u.plan_id = p.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $userCurrent = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch target plan details
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$newPlanId]);
        $targetPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetPlan) {
            $_SESSION['error'] = "The selected plan does not exist.";
            header('Location: /dashboard');
            exit;
        }

        $currentPrice = (float)($userCurrent['current_price'] ?? 0);
        $newPrice = (float)($targetPlan['price'] ?? 0);

        if ($newPrice < $currentPrice) {
            
            if ($newPrice > 0.00) {
                $_SESSION['error'] = "Direct downgrades between paid plans are not permitted. You can only schedule a downgrade to a Free plan at the end of your billing cycle and only then you can upgrade to another paid plan.";
                header('Location: /dashboard');
                exit;
            }

            $stmtPending = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            
            $keyName = 'pending_downgrade_user_' . $userId;
            $stmtPending->execute([$keyName, $newPlanId, $newPlanId]);

            $expiryDate = !empty($userCurrent['current_period_end']) 
                ? date('M d, Y', strtotime($userCurrent['current_period_end'])) 
                : 'the end of your billing cycle';

            $_SESSION['success'] = "Downgrade request scheduled successfully! Your account remains on " . htmlspecialchars($userCurrent['current_plan_name'] ?? 'Paid Plan') . " until " . $expiryDate . ". On that date, it will automatically switch to " . htmlspecialchars($targetPlan['name']) . ".";

            header('Location: /dashboard');
            exit;
        }

        //UPGRADE INSTANT
        if (!empty($priceId)) {
            $this->checkoutWithParams($priceId, $newPlanId, $userId);
            exit;
        }

        $updateStmt = $db->prepare("UPDATE users SET plan_id = ? WHERE id = ?");
        $updateStmt->execute([$newPlanId, $userId]);

        $_SESSION['success'] = "Plan successfully updated to " . htmlspecialchars($targetPlan['name']) . "!";
        header('Location: /dashboard');
        exit;
    }

    /**
     * Create a Stripe Checkout Session and redirect the user
     */
    public function checkout(): void {
        $priceId = $_POST['price_id'] ?? ''; 
        $planId = (int)($_POST['plan_id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $this->checkoutWithParams($priceId, $planId, $userId);
    }

    /**
     * Private helper to execute Checkout logic
     */
    private function checkoutWithParams(string $priceId, int $planId, int $userId): void {
        if (empty($userId)) {
            header('Location: /login');
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'stripe_secret_key'");
        $stmt->execute();
        $secretKey = $stmt->fetchColumn();

        if (empty($secretKey)) {
            $_SESSION['error'] = "Stripe configuration is missing. Please check the admin panel.";
            header('Location: /dashboard');
            exit;
        }

        if (empty($priceId) || empty($planId)) {
            $_SESSION['error'] = "Invalid plan or pricing selected.";
            header('Location: /dashboard');
            exit;
        }

        $stripe = new StripeClient($secretKey);

        $sessionData = [
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'client_reference_id' => $userId, 
            'success_url' => Config::APP_URL . '/dashboard?payment=success',
            'cancel_url' => Config::APP_URL . '/dashboard?payment=cancelled',
            'line_items' => [[ 'price' => $priceId, 'quantity' => 1 ]],
            'metadata' => [ 'plan_id' => $planId ]
        ];

        try {
            $response = $stripe->post('checkout/sessions', $sessionData);
            header("Location: " . $response['url']);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Checkout generation failed: " . $e->getMessage();
            header('Location: /dashboard');
            exit;
        }
    }

    /**
     * Create a new plan from the admin modal
     */
    public function createPlan(): void {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Unauthorized");
        }

        Plan::create([
            'name'                      => trim($_POST['new_name'] ?? 'New Plan'),
            'stripe_price_id'           => trim($_POST['new_stripe_price_id'] ?? ''),
            'price'                     => (float)($_POST['new_price'] ?? 0),
            'billing_cycle'             => trim($_POST['new_billing_cycle'] ?? 'monthly'),
            'max_accounts'              => (int)($_POST['new_max_accounts'] ?? 1),
            'max_posts'                 => (int)($_POST['new_max_posts'] ?? 10),
            'post_period'               => trim($_POST['new_post_period'] ?? 'monthly'),
            'ai_credits_month'          => (int)($_POST['new_ai_credits_month'] ?? 0),
            'max_mailing_lists'         => (int)($_POST['max_mailing_lists'] ?? 0),
            'max_subscribers_per_list'  => (int)($_POST['max_subscribers_per_list'] ?? 0),
            'max_emails_per_month'      => (int)($_POST['max_emails_per_month'] ?? 0)
        ]);

        $_SESSION['success'] = "New plan created successfully.";
        header('Location: /admin/billing');
        exit;
    }

    /**
     * Cancel a previously scheduled plan downgrade
     */
    public function cancelDowngrade(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        // Remove the pending downgrade setting from system_settings
        $settingKey = 'pending_downgrade_user_' . $userId;
        $stmt = $db->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$settingKey]);

        $_SESSION['success'] = "Scheduled downgrade cancelled! Your active plan subscription will renew normally.";
        header('Location: /dashboard');
        exit;
    }
}