<?php
// app/Core/Crypto.php

require_once __DIR__ . '/Config.php';

class Crypto {

    private const CIPHER = 'aes-256-gcm';

    /**
     * Encrypt a string using AES-256-GCM
     *
     * @param string $plaintext
     * @return string Base64 encoded payload containing IV, authentication tag and ciphertext
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }

        $key = hash('sha256', Config::ENCRYPTION_KEY, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        // Pack: IV (12 bytes) + Tag (16 bytes) + Ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt an AES-256-GCM payload
     *
     * @param string $payload Base64 encoded payload
     * @return string|null Decrypted string or null on failure / plaintext fallback
     */
    public static function decrypt(string $payload): ?string {
        if ($payload === '') {
            return '';
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return $payload; // Fallback to raw value if not base64
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16;

        if (strlen($decoded) < ($ivLength + $tagLength)) {
            return $payload; // Fallback if string is too short to be an encrypted payload
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $ciphertext = substr($decoded, $ivLength + $tagLength);

        $key = hash('sha256', Config::ENCRYPTION_KEY, true);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? $decrypted : $payload;
    }

    /**
     * Create a deterministic HMAC blind index for search lookups
     *
     * @param string $email
     * @return string 64-character hex string
     */
    public static function hashEmail(string $email): string {
        $normalized = strtolower(trim($email));
        return hash_hmac('sha256', $normalized, Config::HMAC_KEY);
    }
}