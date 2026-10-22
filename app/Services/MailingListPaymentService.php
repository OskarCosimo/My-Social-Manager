<?php
// app/Services/MailingListPaymentService.php

require_once __DIR__ . '/../Core/StripeClient.php';
require_once __DIR__ . '/../Core/PayPalClient.php';
require_once __DIR__ . '/../Models/SystemSetting.php';

class MailingListPaymentService {

    /**
     * Create Checkout URL for Stripe using Connected Account or Creator Keys
     */
    public static function createStripeSession(array $list, ?array $owner, string $subscriberEmail, string $baseUrl): string {
        $settings = SystemSetting::getAll();
        
        // System platform commission percentage (e.g. 5.0)
        $platformFeePercent = (float)($settings['platform_commission_percent'] ?? 0);

        // Fetch creator payout credentials directly from user profile or list settings
        $creatorStripeAccount = $owner['creator_stripe_account_id'] ?? '';
        
        // Ensure creator Stripe Account ID is present and valid
        if (empty($creatorStripeAccount) || !preg_match('/^acct_[a-zA-Z0-9]+$/', $creatorStripeAccount)) {
            throw new Exception("Invalid or missing Stripe Connected Account ID for this mailing list.");
        }

        // Master Stripe secret key strictly used for Connect API calls
        $masterSecretKey = $settings['stripe_secret_key'] ?? '';
        if (empty($masterSecretKey)) {
            throw new Exception("System Stripe integration is not configured for processing payments.");
        }

        $client = new StripeClient($masterSecretKey);

        $amountInCents = (int)round($list['price'] * 100);
        $feeInCents = (int)round(($amountInCents * $platformFeePercent) / 100);

        $payload = [
            'success_url' => $baseUrl . '/subscribe/success/' . $list['public_hash'] . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/subscribe/' . $list['public_hash'],
            'customer_email' => $subscriberEmail,
            'client_reference_id' => $list['id'] . '|' . $subscriberEmail,
            'line_items[0][price_data][currency]' => strtolower($list['currency']),
            'line_items[0][price_data][unit_amount]' => $amountInCents,
            'line_items[0][price_data][product_data][name]' => 'Subscription: ' . $list['name'],
            'line_items[0][quantity]' => 1
        ];

        if ($list['payment_type'] === 'subscription') {
            $payload['mode'] = 'subscription';
            $payload['line_items[0][price_data][recurring][interval]'] = ($list['billing_period'] === 'yearly') ? 'year' : 'month';
        } else {
            $payload['mode'] = 'payment';
        }

        // Direct payment transfer to Creator's Stripe Connected Account with platform commission deduction
        $payload['payment_intent_data[transfer_data][destination]'] = $creatorStripeAccount;
        if ($feeInCents > 0) {
            $payload['payment_intent_data[application_fee_amount]'] = $feeInCents;
        }

        $response = $client->post('checkout/sessions', $payload);

        if (!isset($response['url'])) {
            throw new Exception("Failed to generate Stripe checkout session.");
        }

        return $response['url'];
    }

    /**
     * Create Checkout URL for PayPal using Creator's Payout Email
     */
    public static function createPayPalOrder(array $list, ?array $owner, string $subscriberEmail, string $baseUrl): string {
        $settings = SystemSetting::getAll();
        $clientId = $settings['paypal_client_id'] ?? '';
        $clientSecret = $settings['paypal_secret'] ?? '';
        $mode = $settings['paypal_mode'] ?? 'sandbox';

        $creatorPaypalEmail = $owner['creator_paypal_email'] ?? '';

        if (empty($creatorPaypalEmail) || !filter_var($creatorPaypalEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid or missing PayPal payout email for this mailing list.");
        }

        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception("PayPal credentials are not configured in system settings.");
        }

        $client = new PayPalClient($clientId, $clientSecret, $mode);

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($list['currency']),
                    'value' => number_format($list['price'], 2, '.', '')
                ],
                'payee' => [
                    'email_address' => $creatorPaypalEmail
                ],
                'description' => 'Mailing List: ' . $list['name'],
                'custom_id' => $list['id'] . '|' . $subscriberEmail
            ]],
            'application_context' => [
                'return_url' => $baseUrl . '/subscribe/success/' . $list['public_hash'],
                'cancel_url' => $baseUrl . '/subscribe/' . $list['public_hash']
            ]
        ];

        $response = $client->post('v2/checkout/orders', $orderData);

        if (isset($response['links'])) {
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return $link['href'];
                }
            }
        }

        throw new Exception("Failed to generate PayPal approval link.");
    }
}