<?php
// app/Core/TwoFactor.php

class TwoFactor {

    /**
     * Generate a secret key for 2FA (Standard RFC 4648 Base32 format)
     */
    public static function generateSecret(int $length = 16): string {
        $b32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $b32[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verify the 6-digit TOTP code against secret
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool {
        $currentTime = (int)floor(time() / 30);
        $code = trim($code);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (self::getCode($secret, $currentTime + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate 6-digit code for a specific time slice
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        $modulo = $value % 1000000;

        return str_pad((string)$modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Internal helper to decode standard Base32 string to binary
     */
    private static function base32Decode(string $secret): string {
        $b32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $ch = $secret[$i];
            $val = strpos($b32, $ch);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $binary;
    }
}