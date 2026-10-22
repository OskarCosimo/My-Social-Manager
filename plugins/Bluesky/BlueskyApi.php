<?php
// plugins/bluesky/BlueskyApi.php

class BlueskyApi {
    private string $baseUrl = 'https://bsky.social/xrpc';

    public function createSession(string $handle, string $appPassword): array {
        $endpoint = $this->baseUrl . '/com.atproto.server.createSession';
        $payload = json_encode(['identifier' => $handle, 'password' => $appPassword]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true) ?? [];

        if (isset($response['error'])) {
            throw new Exception("Bluesky Auth Error: " . ($response['message'] ?? 'Invalid credentials'));
        }

        return $response;
    }

    public function createTextPost(string $text, string $did, string $appPassword): array {
        $session = $this->createSession($did, $appPassword);
        
        $endpoint = $this->baseUrl . '/com.atproto.repo.createRecord';
        $payload = json_encode([
            'repo' => $did,
            'collection' => 'app.bsky.feed.post',
            'record' => [
                '$type' => 'app.bsky.feed.post',
                'text' => $text,
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $session['accessJwt']
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true) ?? [];
    }
}