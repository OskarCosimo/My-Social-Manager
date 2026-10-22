<?php
// app/Core/PayPalClient.php

class PayPalClient {

    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct(string $clientId, string $clientSecret, string $mode = 'sandbox') {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = ($mode === 'live') ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
    }

    /**
     * Obtain OAuth2 access token from PayPal
     */
    private function authenticate(): string {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $ch = curl_init($this->baseUrl . 'v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->clientId}:{$this->clientSecret}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode >= 400 || !isset($response['access_token'])) {
            throw new Exception("PayPal OAuth Error: " . ($response['error_description'] ?? 'Authentication failed'));
        }

        $this->accessToken = $response['access_token'];
        return $this->accessToken;
    }

    /**
     * Make a POST request to PayPal REST API
     */
    public function post(string $endpoint, array $data): array {
        $token = $this->authenticate();
        $url = $this->baseUrl . ltrim($endpoint, '/');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode >= 400) {
            throw new Exception("PayPal API Error: " . ($response['message'] ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Make a GET request to PayPal REST API
     */
    public function get(string $endpoint): array {
        $token = $this->authenticate();
        $url = $this->baseUrl . ltrim($endpoint, '/');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if ($httpCode >= 400) {
            throw new Exception("PayPal API Error: " . ($response['message'] ?? 'Unknown error'));
        }

        return $response;
    }
}