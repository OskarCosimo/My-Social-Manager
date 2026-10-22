<?php
// app/Controllers/AdminBillingController.php

require_once __DIR__ . '/../Models/SystemSetting.php';
require_once __DIR__ . '/../Models/Plan.php';

class AdminBillingController {
    
    public function index(): void {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /dashboard');
            exit;
        }

        $stripePublicKey = SystemSetting::get('stripe_public_key');
        $stripeSecretKey = SystemSetting::get('stripe_secret_key');
        $stripeWebhookSecret = SystemSetting::get('stripe_webhook_secret');
        
        $plans = Plan::getAll();

        require_once __DIR__ . '/../Views/admin/billing.php';
    }

    public function saveStripeSettings(): void {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Unauthorized");
        }

        SystemSetting::set('stripe_public_key', trim($_POST['stripe_public_key'] ?? ''));
        SystemSetting::set('stripe_secret_key', trim($_POST['stripe_secret_key'] ?? ''));
        SystemSetting::set('stripe_webhook_secret', trim($_POST['stripe_webhook_secret'] ?? ''));

        $_SESSION['success'] = "Stripe settings saved successfully.";
        header('Location: /admin/billing');
        exit;
    }

    /**
     * Create a new plan from the admin modal
     */
    public function createPlan(): void {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Unauthorized");
        }

        $stripePriceId = trim($_POST['stripe_price_id'] ?? ($_POST['new_stripe_price_id'] ?? ''));
        $planCode = strtolower(trim($_POST['plan_code'] ?? ''));

        Plan::create([
            'name'                     => trim($_POST['name'] ?? ($_POST['new_name'] ?? 'New Plan')),
            'plan_code'                => !empty($planCode) ? $planCode : null,
            'stripe_price_id'          => !empty($stripePriceId) ? $stripePriceId : null,
            'price'                    => (float)($_POST['price'] ?? ($_POST['new_price'] ?? 0)),
            'billing_cycle'            => trim($_POST['billing_cycle'] ?? ($_POST['new_billing_cycle'] ?? 'monthly')),
            'max_accounts'             => (int)($_POST['max_accounts'] ?? ($_POST['new_max_accounts'] ?? 1)),
            'max_posts'                => (int)($_POST['max_posts'] ?? ($_POST['new_max_posts'] ?? 10)),
            'post_period'              => trim($_POST['post_period'] ?? ($_POST['new_post_period'] ?? 'monthly')),
            'max_rss_feeds'            => (int)($_POST['max_rss_feeds'] ?? ($_POST['new_max_rss_feeds'] ?? 5)),
            'max_rss_posts'            => (int)($_POST['max_rss_posts'] ?? ($_POST['new_max_rss_posts'] ?? 50)),
            'max_channel_automations'  => (int)($_POST['max_channel_automations'] ?? ($_POST['new_max_channel_automations'] ?? 5)),
            'max_channel_posts'        => (int)($_POST['max_channel_posts'] ?? ($_POST['new_max_channel_posts'] ?? 50)),
            'ai_credits_month'         => (int)($_POST['ai_credits_month'] ?? ($_POST['new_ai_credits_month'] ?? 0)),
            'ai_max_campaign_posts'    => (int)($_POST['ai_max_campaign_posts'] ?? ($_POST['new_ai_max_campaign_posts'] ?? 10)),
            'ai_max_daily_posts'       => (int)($_POST['ai_max_daily_posts'] ?? ($_POST['new_ai_max_daily_posts'] ?? 2)),
            'max_storage_size'         => trim($_POST['max_storage_size'] ?? ($_POST['new_max_storage_size'] ?? '1GB')),
            'max_upload_size'          => trim($_POST['max_upload_size'] ?? ($_POST['new_max_upload_size'] ?? '50MB')),
            'max_mailing_lists'        => (int)($_POST['max_mailing_lists'] ?? ($_POST['new_max_mailing_lists'] ?? 0)),
            'max_subscribers_per_list' => (int)($_POST['max_subscribers_per_list'] ?? ($_POST['new_max_subscribers_per_list'] ?? 0)),
            'max_emails_per_month'     => (int)($_POST['max_emails_per_month'] ?? ($_POST['new_max_emails_per_month'] ?? 0)),
            'is_default'              => isset($_POST['is_default']) ? 1 : 0,
            'is_hidden'               => isset($_POST['is_hidden']) ? 1 : 0
        ]);

        $_SESSION['success'] = "Plan created successfully.";
        header('Location: /admin/billing');
        exit;
    }

    /**
     * Update an existing plan from the admin edit modal
     */
    public function updatePlan(): void {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Unauthorized");
        }

        $planId = (int)($_POST['id'] ?? ($_POST['plan_id'] ?? 0));

        if (!$planId) {
            $_SESSION['error'] = "Invalid plan ID.";
            header('Location: /admin/billing');
            exit;
        }

        $stripePriceId = trim($_POST['stripe_price_id'] ?? '');
        $planCode = strtolower(trim($_POST['plan_code'] ?? ''));

        Plan::update($planId, [
            'name'                     => trim($_POST['name'] ?? ''),
            'plan_code'                => !empty($planCode) ? $planCode : null,
            'stripe_price_id'          => !empty($stripePriceId) ? $stripePriceId : null,
            'price'                    => (float)($_POST['price'] ?? 0),
            'billing_cycle'            => trim($_POST['billing_cycle'] ?? 'monthly'),
            'max_accounts'             => (int)($_POST['max_accounts'] ?? 1),
            'max_posts'                => (int)($_POST['max_posts'] ?? 10),
            'post_period'              => trim($_POST['post_period'] ?? 'monthly'),
            'max_rss_feeds'            => (int)($_POST['max_rss_feeds'] ?? 5),
            'max_rss_posts'            => (int)($_POST['max_rss_posts'] ?? 50),
            'max_channel_automations'  => (int)($_POST['max_channel_automations'] ?? 5),
            'max_channel_posts'        => (int)($_POST['max_channel_posts'] ?? 50),
            'ai_credits_month'         => (int)($_POST['ai_credits_month'] ?? 0),
            'ai_max_campaign_posts'    => (int)($_POST['ai_max_campaign_posts'] ?? 10),
            'ai_max_daily_posts'       => (int)($_POST['ai_max_daily_posts'] ?? 2),
            'max_storage_size'         => trim($_POST['max_storage_size'] ?? '1GB'),
            'max_upload_size'          => trim($_POST['max_upload_size'] ?? '50MB'),
            'max_mailing_lists'        => (int)($_POST['max_mailing_lists'] ?? 0),
            'max_subscribers_per_list' => (int)($_POST['max_subscribers_per_list'] ?? 0),
            'max_emails_per_month'     => (int)($_POST['max_emails_per_month'] ?? 0),
            'is_default'              => isset($_POST['is_default']) ? 1 : 0,
            'is_hidden'               => isset($_POST['is_hidden']) ? 1 : 0
        ]);

        $_SESSION['success'] = "Plan updated successfully.";
        header('Location: /admin/billing');
        exit;
    }

    public function updatePlans(): void {
        $this->updatePlan();
    }

    public function deletePlan(): void {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Unauthorized");
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id > 0) {
            if (Plan::delete($id)) {
                $_SESSION['success'] = "Plan deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to delete plan.";
            }
        } else {
            $_SESSION['error'] = "Invalid plan ID.";
        }

        header('Location: /admin/billing');
        exit;
    }
}