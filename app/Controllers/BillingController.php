<?php
// app/Controllers/BillingController.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/StripeClient.php';
require_once __DIR__ . '/../Core/PayPalClient.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Plan.php';
require_once __DIR__ . '/../Models/SystemSetting.php';
require_once __DIR__ . '/../Models/UsageManager.php';

class BillingController {
    
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

        $stmt = $db->prepare("
            SELECT u.plan_id, u.current_period_end, p.price as current_price, p.name as current_plan_name 
            FROM users u 
            LEFT JOIN plans p ON u.plan_id = p.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $userCurrent = $stmt->fetch(PDO::FETCH_ASSOC);

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

        // UPGRADE INSTANT
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

    public function checkout(): void {
        $priceId = $_POST['price_id'] ?? ''; 
        $planId = (int)($_POST['plan_id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $this->checkoutWithParams($priceId, $planId, $userId);
    }

    private function checkoutWithParams(string $priceId, int $planId, int $userId): void {
        if (empty($userId)) {
            header('Location: /login');
            exit;
        }

        $secretKey = SystemSetting::get('stripe_secret_key');
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
     * Top-Up AI Credits via Stripe Checkout (One-Time Payment)
     */
    public function buyCreditsStripe(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $credits = (int)($_POST['credits'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if ($credits <= 0 || $price <= 0) {
            $_SESSION['error'] = "Invalid AI credits package selected.";
            header('Location: /ai-studio');
            exit;
        }

        $secretKey = SystemSetting::get('stripe_secret_key');
        if (empty($secretKey)) {
            $_SESSION['error'] = "Stripe is not configured. Please contact the administrator.";
            header('Location: /ai-studio');
            exit;
        }

        $stripe = new StripeClient($secretKey);

        $sessionData = [
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'client_reference_id'  => "ai_credits|{$userId}|{$credits}",
            'success_url'          => Config::APP_URL . '/billing/stripe-credits-success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => Config::APP_URL . '/ai-studio?cancelled=1',
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int)round($price * 100),
                    'product_data' => [
                        'name'        => "{$credits} AI Credits Pack",
                        'description' => "One-time top-up of {$credits} AI Studio generation credits."
                    ]
                ],
                'quantity'   => 1
            ]],
            'metadata' => [
                'type'    => 'ai_credits',
                'user_id' => $userId,
                'credits' => $credits
            ]
        ];

        try {
            $response = $stripe->post('checkout/sessions', $sessionData);
            header("Location: " . $response['url']);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to initiate Stripe payment: " . $e->getMessage();
            header('Location: /ai-studio');
            exit;
        }
    }

    /**
     * Instant verification on return from Stripe Checkout
     */
    public function stripeCreditsSuccess(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $sessionId = $_GET['session_id'] ?? '';

        if (!empty($sessionId)) {
            $secretKey = SystemSetting::get('stripe_secret_key');
            if (!empty($secretKey)) {
                try {
                    $stripe = new StripeClient($secretKey);
                    $session = $stripe->get("checkout/sessions/{$sessionId}");

                    if (($session['payment_status'] ?? '') === 'paid') {
                        $credits = (int)($session['metadata']['credits'] ?? 0);
                        if ($credits > 0) {
                            UsageManager::addPurchasedAiCredits($userId, $credits);
                            $_SESSION['success'] = "Payment successful! {$credits} AI credits have been added to your account.";
                            header('Location: /ai-studio');
                            exit;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Stripe credits success verification error: " . $e->getMessage());
                }
            }
        }

        $_SESSION['success'] = "Payment processed successfully!";
        header('Location: /ai-studio');
        exit;
    }

    /**
     * Top-Up AI Credits via PayPal Orders API
     */
    public function buyCreditsPayPal(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $credits = (int)($_POST['credits'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if ($credits <= 0 || $price <= 0) {
            $_SESSION['error'] = "Invalid AI credits package selected.";
            header('Location: /ai-studio');
            exit;
        }

        $clientId = SystemSetting::get('paypal_client_id');
        $clientSecret = SystemSetting::get('paypal_client_secret');
        $mode = SystemSetting::get('paypal_mode', 'sandbox');

        if (empty($clientId) || empty($clientSecret)) {
            $_SESSION['error'] = "PayPal is not configured. Please contact the administrator.";
            header('Location: /ai-studio');
            exit;
        }

        try {
            $paypal = new PayPalClient($clientId, $clientSecret, $mode);
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'custom_id'   => "ai_credits|{$userId}|{$credits}",
                    'description' => "{$credits} AI Credits Pack",
                    'amount'      => [
                        'currency_code' => 'EUR',
                        'value'         => number_format($price, 2, '.', '')
                    ]
                ]],
                'application_context' => [
                    'brand_name'          => 'My Social Manager',
                    'landing_page'        => 'NO_PREFERENCE',
                    'user_action'         => 'PAY_NOW',
                    'return_url'          => Config::APP_URL . "/billing/paypal-credits-return?credits={$credits}",
                    'cancel_url'          => Config::APP_URL . '/ai-studio?cancelled=1'
                ]
            ];

            $order = $paypal->post('v2/checkout/orders', $orderData);
            $approveLink = null;

            foreach ($order['links'] ?? [] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveLink = $link['href'];
                    break;
                }
            }

            if ($approveLink) {
                header("Location: {$approveLink}");
                exit;
            }

            throw new Exception("Approval link not returned by PayPal.");

        } catch (Exception $e) {
            $_SESSION['error'] = "PayPal order creation failed: " . $e->getMessage();
            header('Location: /ai-studio');
            exit;
        }
    }

    /**
     * Capture PayPal order on return
     */
    public function paypalCreditsReturn(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $orderId = $_GET['token'] ?? '';
        $credits = (int)($_GET['credits'] ?? 0);

        if (empty($orderId) || $credits <= 0) {
            $_SESSION['error'] = "Invalid PayPal return parameters.";
            header('Location: /ai-studio');
            exit;
        }

        $clientId = SystemSetting::get('paypal_client_id');
        $clientSecret = SystemSetting::get('paypal_client_secret');
        $mode = SystemSetting::get('paypal_mode', 'sandbox');

        try {
            $paypal = new PayPalClient($clientId, $clientSecret, $mode);
            $captureResult = $paypal->post("v2/checkout/orders/{$orderId}/capture", []);

            if (($captureResult['status'] ?? '') === 'COMPLETED') {
                UsageManager::addPurchasedAiCredits($userId, $credits);
                $_SESSION['success'] = "Payment successful! {$credits} AI credits have been added to your balance.";
            } else {
                $_SESSION['error'] = "PayPal payment was not completed.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "PayPal capture error: " . $e->getMessage();
        }

        header('Location: /ai-studio');
        exit;
    }

    public function cancelDowngrade(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        $settingKey = 'pending_downgrade_user_' . $userId;
        $stmt = $db->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$settingKey]);

        $_SESSION['success'] = "Scheduled downgrade cancelled! Your active plan subscription will renew normally.";
        header('Location: /dashboard');
        exit;
    }
}
