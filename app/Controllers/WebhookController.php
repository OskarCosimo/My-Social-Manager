<?php
// app/Controllers/WebhookController.php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/SystemSetting.php';
require_once __DIR__ . '/../Models/MailingList.php';

class WebhookController {

    /**
     * Handle incoming Stripe webhook events for platform plans and mailing list subscriptions
     */
    public function handleStripe(): void {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        // Retrieve the dynamic webhook secret configured in Admin Panel
        $webhookSecret = SystemSetting::get('stripe_webhook_secret', ''); 

        // 1. Verify the Stripe Signature manually
        if (!empty($webhookSecret) && !$this->verifyStripeSignature($payload, $sigHeader, $webhookSecret)) {
            http_response_code(400);
            die('Webhook Error: Invalid signature.');
        }

        $event = json_decode($payload, true);
        if (!$event) {
            http_response_code(400);
            die('Webhook Error: Invalid payload.');
        }

        $db = Database::getInstance();

        try {
            // 2. Process specific event types
            switch ($event['type']) {
                
                case 'checkout.session.completed':
                    $session = $event['data']['object'];
                    $clientRef = $session['client_reference_id'] ?? '';

                    // ROUTE 1: Check if this checkout belongs to a Mailing List subscription ("list_id|email")
                    if (str_contains($clientRef, '|')) {
                        $parts = explode('|', $clientRef, 2);
                        $listId = (int)$parts[0];
                        $email = trim($parts[1]);

                        if ($listId > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $subId = $session['subscription'] ?? $session['payment_intent'] ?? 'PAYMENT_OK';
                            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

                            // Activate paid subscriber for the mailing list
                            MailingList::addSubscriber($listId, $email, false, 'paid', $subId, $expiresAt);
                            error_log("Mailing List Payment Success: Email {$email} added to List ID {$listId}.");
                        }
                    } 
                    // ROUTE 2: Platform Plan Subscription Upgrade (pure integer user_id)
                    else {
                        $userId = (int)$clientRef;
                        $stripeCustomerId = $session['customer'] ?? null;
                        $subscriptionId = $session['subscription'] ?? null;
                        $planId = (int)($session['metadata']['plan_id'] ?? 1); // Fallback to Free Tier

                        if ($userId > 0 && !empty($stripeCustomerId)) {
                            $stmt = $db->prepare("
                                UPDATE users 
                                SET plan_id = :plan_id, 
                                    stripe_customer_id = :customer_id, 
                                    stripe_subscription_id = :sub_id 
                                WHERE id = :user_id
                            ");
                            
                            $stmt->execute([
                                ':plan_id' => $planId,
                                ':customer_id' => $stripeCustomerId,
                                ':sub_id' => $subscriptionId,
                                ':user_id' => $userId
                            ]);
                            
                            error_log("Platform Plan Upgrade: User ID {$userId} upgraded to Plan ID {$planId}.");
                        }
                    }
                    break;

                case 'customer.subscription.deleted':
                    $subscription = $event['data']['object'];
                    $subscriptionId = $subscription['id'] ?? '';
                    $stripeCustomerId = $subscription['customer'] ?? '';
                    
                    // First attempt to cancel any mailing list subscription matching this ID
                    if (!empty($subscriptionId)) {
                        $updatedListSub = MailingList::setSubscriberPaymentStatus($subscriptionId, 'expired');
                        if ($updatedListSub) {
                            error_log("Mailing List Subscription Cancelled: Subscription ID {$subscriptionId} set to expired.");
                        }
                    }

                    // Also process platform user downgrade if it matches a platform customer
                    if (!empty($stripeCustomerId)) {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET plan_id = 1, 
                                stripe_subscription_id = NULL 
                            WHERE stripe_customer_id = :customer_id
                        ");
                        
                        $stmt->execute([
                            ':customer_id' => $stripeCustomerId
                        ]);
                        
                        error_log("Platform Plan Cancelled: Customer {$stripeCustomerId} downgraded to Free Tier.");
                    }
                    break;

                default:
                    error_log("Received unhandled Stripe event: " . $event['type']);
            }
            
        } catch (Exception $e) {
            error_log("Webhook Error: " . $e->getMessage());
            http_response_code(500);
            die('Database error processing webhook.');
        }

        http_response_code(200);
        echo 'Webhook handled successfully.';
    }

    /**
     * Manually verify the Stripe webhook signature using HMAC SHA256
     */
    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool {
        if (empty($sigHeader)) {
            return false;
        }

        $elements = explode(',', $sigHeader);
        $timestamp = '';
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if (empty($timestamp) || empty($signatures)) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }
}