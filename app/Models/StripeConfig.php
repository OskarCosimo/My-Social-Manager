<?php
// app/Models/StripeConfig.php

class StripeConfig {
    
    /**
     * Fetch Stripe API keys from the database configuration table
     */
    public static function getKeys(): array {
        // Query your database settings table here
        return [
            'public_key' => 'pk_test_...', 
            'secret_key' => 'sk_test_...', 
            'webhook_secret' => 'whsec_...' // Critical to verify incoming Stripe events securely
        ];
    }
}