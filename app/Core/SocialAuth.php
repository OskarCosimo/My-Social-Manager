<?php
// app/Core/SocialAuth.php

class SocialAuth {

    /**
     * Perform HTTP GET request using cURL
     *
     * @param string $url
     * @param array $headers
     * @return array|null
     */
    private static function httpGet(string $url, array $headers = []): ?array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MySocialManager/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("cURL GET Error: " . curl_error($ch));
        }
        curl_close($ch);

        if ($response) {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
            parse_str($response, $parsed);
            if (is_array($parsed) && !empty($parsed)) {
                return $parsed;
            }
        }
        return null;
    }

    /**
     * Perform HTTP POST request using cURL
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array|null
     */
    private static function httpPost(string $url, array $data, array $headers = []): ?array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MySocialManager/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("cURL POST Error: " . curl_error($ch));
        }
        curl_close($ch);

        if ($response) {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
            parse_str($response, $parsed);
            if (is_array($parsed) && !empty($parsed)) {
                return $parsed;
            }
        }
        return null;
    }

    /**
     * Exchange authorization code for Google User Info
     *
     * @param string $code
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     * @return array|null
     */
    public static function getGoogleUser(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array {
        $tokenData = self::httpPost('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ]);

        if (empty($tokenData['access_token'])) {
            return null;
        }

        $userInfo = self::httpGet('https://www.googleapis.com/oauth2/v2/userinfo', [
            'Authorization: Bearer ' . $tokenData['access_token']
        ]);

        if (empty($userInfo['email']) || empty($userInfo['id'])) {
            return null;
        }

        return [
            'id' => (string)$userInfo['id'], 
            'email' => strtolower($userInfo['email'])
        ];
    }

    /**
     * Exchange authorization code for Facebook User Info
     *
     * @param string $code
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     * @return array|null
     */
    public static function getFacebookUser(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array {
        $tokenData = self::httpGet('https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        ]));

        if (empty($tokenData['access_token'])) {
            return null;
        }

        $userInfo = self::httpGet('https://graph.facebook.com/me?fields=id,email&access_token=' . $tokenData['access_token']);

        if (empty($userInfo['email']) || empty($userInfo['id'])) {
            return null;
        }

        return [
            'id' => (string)$userInfo['id'], 
            'email' => strtolower($userInfo['email'])
        ];
    }

    /**
     * Exchange authorization code for Microsoft User Info
     *
     * @param string $code
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     * @return array|null
     */
    public static function getMicrosoftUser(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array {
        $tokenData = self::httpPost('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ], ['Content-Type: application/x-www-form-urlencoded']);

        if (empty($tokenData['access_token'])) {
            return null;
        }

        $userInfo = self::httpGet('https://graph.microsoft.com/v1.0/me', [
            'Authorization: Bearer ' . $tokenData['access_token']
        ]);

        if (empty($userInfo['id'])) {
            return null;
        }

        $email = $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? null;
        if (empty($email)) {
            return null;
        }

        return [
            'id' => (string)$userInfo['id'], 
            'email' => strtolower($email)
        ];
    }

    /**
     * Exchange authorization code for MYETV User Info (DEBUG MODE)
     */
    public static function getMyetvUser(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array {
        // 1. Exchange authorization code for Access Token
        $tokenResponse = self::httpPost('https://developers.myetv.tv/api/oauth/token.php', [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
            'scope'         => 'email'
        ]);

        if (empty($tokenResponse)) {
            die("<h3 style='color:red;'>DEBUG ERROR: Token Exchange Failed</h3><p>The MYETV API returned an empty response during token exchange.</p>");
        }

        // Support both access_token and token keys
        $accessToken = $tokenResponse['access_token'] ?? $tokenResponse['token'] ?? null;

        if (empty($accessToken)) {
            die("<h3 style='color:red;'>DEBUG ERROR: Missing Access Token</h3><p>MYETV responded, but the access token is missing. Here is what MYETV said:</p><pre style='background:#f4f4f4; padding:15px; border:1px solid #ccc;'>" . print_r($tokenResponse, true) . "</pre>");
        }

        // 2. Fetch User Profile Info
        $profileUrl = 'https://developers.myetv.tv/api/v1/profile.php?access_token=' . urlencode($accessToken);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ];

        $userInfo = self::httpGet($profileUrl, $headers);

        if (empty($userInfo)) {
            die("<h3 style='color:red;'>DEBUG ERROR: Profile Fetch Failed</h3><p>Token was retrieved successfully (Token: {$accessToken}), but the profile endpoint returned an empty response.</p>");
        }

        // 3. Extract User ID and Email
        $userId = $userInfo['id'] ?? $userInfo['user_id'] ?? $userInfo['uid'] ?? ($userInfo['data']['id'] ?? null);
        $email  = $userInfo['email'] ?? $userInfo['mail'] ?? $userInfo['user_email'] ?? ($userInfo['data']['email'] ?? null);

        if (empty($userId) || empty($email)) {
            die("<h3 style='color:red;'>DEBUG ERROR: Missing ID or Email in Profile</h3><p>The profile endpoint responded, but we couldn't find the 'id' or 'email' fields. Here is the raw profile data:</p><pre style='background:#f4f4f4; padding:15px; border:1px solid #ccc;'>" . print_r($userInfo, true) . "</pre>");
        }

        return [
            'id'    => (string)$userId,
            'email' => strtolower((string)$email)
        ];
    }
}