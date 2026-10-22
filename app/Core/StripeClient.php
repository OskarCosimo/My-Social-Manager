<?php
// app/Core/StripeClient.php

class StripeClient {
    
    private const API_BASE = 'https://api.stripe.com/v1/';
    private string $secretKey;

    public function __construct(string $secretKey) {
        $this->secretKey = $secretKey;
    }

    /**
     * Make a POST request to the Stripe API
     */
    public function post(string $endpoint, array $data): array {
        $url = self::API_BASE . ltrim($endpoint, '/');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->secretKey}",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode >= 400) {
            throw new Exception("Stripe API Error: " . ($response['error']['message'] ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Make a GET request to the Stripe API
     */
    public function get(string $endpoint): array {
        $url = self::API_BASE . ltrim($endpoint, '/');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->secretKey}"
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode >= 400) {
            throw new Exception("Stripe API Error: " . ($response['error']['message'] ?? 'Unknown error'));
        }

        return $response;
    }
}