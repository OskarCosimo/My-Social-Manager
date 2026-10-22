<?php
// app/Controllers/AuthController.php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Mailer.php';
require_once __DIR__ . '/../Models/SystemSetting.php';
require_once __DIR__ . '/../Core/TwoFactor.php';
require_once __DIR__ . '/../Core/SocialAuth.php';

class AuthController {

    /**
     * Render login view or perform cookie auto-login
     */
    public function showLogin(): void {
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me_token'])) {
            $user = User::findByRememberToken($_COOKIE['remember_me_token']);
            if ($user && ($user['status'] ?? 'active') === 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'] ?? 'user';
                header('Location: /dashboard');
                exit;
            }
        }

        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard');
            exit;
        }

        $settings = SystemSetting::getAll();
        require_once __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Process standard login request
     */
    public function login(): void {
        // Verify Turnstile anti-bot challenge before proceeding
        if (!$this->validateTurnstile()) {
            $_SESSION['error'] = "Anti-bot verification failed. Please try again.";
            // Redirect back to the previous page (login or register)
            $referer = $_SERVER['HTTP_REFERER'] ?? '/login';
            header('Location: ' . $referer);
            exit;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['error'] = "Please fill in all fields.";
            header('Location: /login');
            exit;
        }

        $user = User::findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            if (isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
                $_SESSION['error'] = "Please verify your email address before logging in.";
                header('Location: /login');
                exit;
            }

            if (($user['status'] ?? 'active') === 'banned') {
                $_SESSION['error'] = "Your account has been suspended.";
                header('Location: /login');
                exit;
            }

            $this->finalizeLoginProcess($user);
            return;
        }

        $_SESSION['error'] = "Invalid credentials.";
        header('Location: /login');
        exit;
    }

    /**
     * Finalize session state, set user role, handle 2FA and persistent login cookies
     */
    private function finalizeLoginProcess(array $user, bool $skip2fa = false): void {
        // Evaluate remember me from POST, previous 2FA session, or persistent OAuth session
        $shouldRemember = !empty($_POST['remember_me']) 
            || (isset($_SESSION['pending_2fa_remember']) && $_SESSION['pending_2fa_remember'] === '1')
            || (isset($_SESSION['social_remember_me']) && $_SESSION['social_remember_me'] === '1');

        if (!$skip2fa && !empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            $_SESSION['pending_2fa_remember'] = $shouldRemember ? '1' : '0';
            unset($_SESSION['social_remember_me']);
            header('Location: /auth/2fa');
            exit;
        }

        if ($shouldRemember) {
            $token = bin2hex(random_bytes(32));
            User::updateRememberToken((int)$user['id'], $token);
            setcookie('remember_me_token', $token, time() + (86400 * 180), '/', '', isset($_SERVER["HTTPS"]), true);
            unset($_SESSION['pending_2fa_remember']);
            unset($_SESSION['social_remember_me']);
        }

        if (!isset($user['role'])) {
            $freshUser = User::findById((int)$user['id']);
            if ($freshUser && isset($freshUser['role'])) {
                $user['role'] = $freshUser['role'];
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = !empty($user['role']) ? $user['role'] : 'user';

        unset($_SESSION['pending_2fa_user_id']);

        header('Location: /dashboard');
        exit;
    }

    /**
     * Render two-factor authentication prompt
     */
    public function show2fa(): void {
        if (!isset($_SESSION['pending_2fa_user_id'])) {
            header('Location: /login');
            exit;
        }
        require_once __DIR__ . '/../Views/auth/2fa-verify.php';
    }

    /**
     * Verify two-factor 6-digit code during login
     */
    public function verify2fa(): void {
        if (!isset($_SESSION['pending_2fa_user_id'])) {
            header('Location: /login');
            exit;
        }

        $code = trim($_POST['code'] ?? '');
        $userId = (int)$_SESSION['pending_2fa_user_id'];
        $user = User::findById($userId);

        if ($user && TwoFactor::verifyCode($user['two_factor_secret'], $code)) {
            $this->finalizeLoginProcess($user, true);
            return;
        }

        $_SESSION['error'] = "Invalid 2FA verification code.";
        header('Location: /auth/2fa');
        exit;
    }

    /**
     * Redirects the user to the selected OAuth Provider authentication URL.
     * Validates Turnstile anti-bot token prior to initiating OAuth flow.
     */
    public function redirectSocial(): void {
        // Use $_REQUEST to support both POST (from Turnstile form submit) and GET
        $provider = $_REQUEST['provider'] ?? '';

        // Validate Turnstile anti-bot challenge
        if (!$this->validateTurnstile()) {
            $_SESSION['error'] = "Anti-bot verification failed. Please try again.";
            header('Location: /login');
            exit;
        }

        // Store remember me choice in session across the external OAuth redirect
        if (!empty($_REQUEST['remember_me'])) {
            $_SESSION['social_remember_me'] = '1';
        } else {
            unset($_SESSION['social_remember_me']);
        }

        $settings = SystemSetting::getAll();

        switch ($provider) {
            case 'google':
                if (($settings['enable_social_google'] ?? '0') !== '1') {
                    $_SESSION['error'] = "Google login is currently disabled.";
                    header('Location: /login');
                    exit;
                }
                $clientId = $settings['google_client_id'] ?? '';
                $redirectUri = Config::APP_URL . '/auth/callback/google';
                $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'email profile',
                    'access_type' => 'online'
                ]);
                header('Location: ' . $url);
                exit;

            case 'facebook':
                if (($settings['enable_social_facebook'] ?? '0') !== '1') {
                    $_SESSION['error'] = "Facebook login is currently disabled.";
                    header('Location: /login');
                    exit;
                }
                $clientId = $settings['facebook_client_id'] ?? '';
                $redirectUri = Config::APP_URL . '/auth/callback/facebook';
                $url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => 'email'
                ]);
                header('Location: ' . $url);
                exit;

            case 'microsoft':
                if (($settings['enable_social_microsoft'] ?? '0') !== '1') {
                    $_SESSION['error'] = "Microsoft login is currently disabled.";
                    header('Location: /login');
                    exit;
                }
                $clientId = $settings['microsoft_client_id'] ?? '';
                $redirectUri = Config::APP_URL . '/auth/callback/microsoft';
                $url = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?" . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'openid email profile User.Read'
                ]);
                header('Location: ' . $url);
                exit;

            case 'myetv':
                if (($settings['enable_social_myetv'] ?? '0') !== '1') {
                    $_SESSION['error'] = "MYETV login is currently disabled.";
                    header('Location: /login');
                    exit;
                }
                $clientId = $settings['myetv_client_id'] ?? '';
                $redirectUri = Config::APP_URL . '/auth/callback/myetv';
                
                // Construct the authorization URL specifically requesting the email scope
                $url = "https://developers.myetv.tv/api/oauth/authorize.php?" . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'email profile' // <--- REQUIRED: Explicitly request email permission
                ]);
                header('Location: ' . $url);
                exit;

            default:
                $_SESSION['error'] = "Social login provider disabled or unsupported.";
                header('Location: /login');
                exit;
        }
    }

    public function handleGoogleCallback(): void {
        $this->processSocialCallback('google');
    }

    public function handleFacebookCallback(): void {
        $this->processSocialCallback('facebook');
    }

    public function handleMicrosoftCallback(): void {
        $this->processSocialCallback('microsoft');
    }

    public function handleMyetvCallback(): void {
        $this->processSocialCallback('myetv');
    }

    /**
     * Centralized social authorization processing
     */
    private function processSocialCallback(string $provider): void {
        $code = $_GET['code'] ?? '';
        $settings = SystemSetting::getAll();

        if (empty($code)) {
            $_SESSION['error'] = "Social login authorization failed.";
            header('Location: /login');
            exit;
        }

        $appUrl = rtrim(Config::APP_URL, '/');
        $redirectUri = $appUrl . "/auth/callback/" . $provider;
        $socialUserData = null;

        if ($provider === 'google' && ($settings['enable_social_google'] ?? '0') === '1') {
            $socialUserData = SocialAuth::getGoogleUser($code, $settings['google_client_id'] ?? '', $settings['google_client_secret'] ?? '', $redirectUri);
        } elseif ($provider === 'facebook' && ($settings['enable_social_facebook'] ?? '0') === '1') {
            $socialUserData = SocialAuth::getFacebookUser($code, $settings['facebook_client_id'] ?? '', $settings['facebook_client_secret'] ?? '', $redirectUri);
        } elseif ($provider === 'microsoft' && ($settings['enable_social_microsoft'] ?? '0') === '1') {
            $socialUserData = SocialAuth::getMicrosoftUser($code, $settings['microsoft_client_id'] ?? '', $settings['microsoft_client_secret'] ?? '', $redirectUri);
        } elseif ($provider === 'myetv' && ($settings['enable_social_myetv'] ?? '0') === '1') {
            $socialUserData = SocialAuth::getMyetvUser($code, $settings['myetv_client_id'] ?? '', $settings['myetv_client_secret'] ?? '', $redirectUri);
        }

        if (!$socialUserData) {
            $_SESSION['error'] = "Failed to retrieve user profile from " . ucfirst($provider) . ".";
            header('Location: /login');
            exit;
        }

        $socialId = $socialUserData['id'];
        $email = $socialUserData['email'];

        $user = User::findBySocialId($provider, $socialId);

        if (!$user) {
            $user = User::findByEmail($email);

            if (!$user) {
                $localId = User::createFromSocial($email, $provider, $socialId);
                $user = User::findById($localId);
            } else {
                User::linkSocialAccount((int)$user['id'], $provider, $socialId);
                $user = User::findById((int)$user['id']);
            }
        }

        $this->finalizeLoginProcess($user);
    }

    /**
     * Legacy query parameter callback fallback
     */
    public function handleSocialCallback(): void {
        $provider = $_GET['provider'] ?? '';
        if (empty($provider)) {
            $_SESSION['error'] = "Invalid social provider.";
            header('Location: /login');
            exit;
        }
        $this->processSocialCallback($provider);
    }

    /**
     * Enable or disable two-factor authentication from user settings
     */
    public function toggle2FA(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $action = $_POST['action'] ?? '';

        if ($action === 'enable') {
            $code = trim($_POST['code'] ?? '');
            $secret = $_SESSION['temp_2fa_secret'] ?? '';

            if ($secret && TwoFactor::verifyCode($secret, $code)) {
                User::update2FA($userId, $secret, true);
                unset($_SESSION['temp_2fa_secret']);
                $_SESSION['success'] = "Two-Factor Authentication activated successfully!";
            } else {
                $_SESSION['error'] = "Invalid code. 2FA activation failed.";
            }
        } elseif ($action === 'disable') {
            User::update2FA($userId, null, false);
            $_SESSION['success'] = "Two-Factor Authentication disabled.";
        }

        header('Location: /settings');
        exit;
    }

    /**
     * Render user registration view
     */
    public function showRegister(): void {
        require_once __DIR__ . '/../Views/auth/register.php';
    }

    /**
     * Process new user registration
     */
    public function register(): void {
        // Verify Turnstile anti-bot challenge before proceeding
        if (!$this->validateTurnstile()) {
            $_SESSION['error'] = "Anti-bot verification failed. Please try again.";
            // Redirect back to the previous page (login or register)
            $referer = $_SERVER['HTTP_REFERER'] ?? '/login';
            header('Location: ' . $referer);
            exit;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['error'] = "All fields are required.";
            header('Location: /register');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email format.";
            header('Location: /register');
            exit;
        }

        if (User::findByEmail($email)) {
            $_SESSION['error'] = "Email already registered.";
            header('Location: /register');
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $settings = SystemSetting::getAll();
        $requireVerification = ($settings['require_email_verification'] ?? '0') === '1';

        $emailVerified = $requireVerification ? 0 : 1;
        $token = $requireVerification ? bin2hex(random_bytes(32)) : null;

        $newUserId = User::createWithVerification($email, $passwordHash, $emailVerified, $token);

        if ($newUserId) {
            if ($requireVerification) {
                $activationLink = Config::APP_URL . "/verify?token=" . $token;
                $subject = "Verify your account - My Social Manager";
                $html = "
                    <h2>Welcome!</h2>
                    <p>Please click the button below to verify your email address:</p>
                    <a href='{$activationLink}' style='padding:10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Verify Email</a>
                ";
                Mailer::send($email, $subject, $html);
                $_SESSION['success'] = "Registration successful! Check your email to activate your account.";
            } else {
                $_SESSION['success'] = "Registration successful. Please log in.";
            }
            header('Location: /login');
        } else {
            $_SESSION['error'] = "System error during registration.";
            header('Location: /register');
        }
        exit;
    }

    /**
     * Verify email activation token from link
     */
    public function verifyEmail(): void {
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            $_SESSION['error'] = "Invalid or missing token.";
            header('Location: /login');
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE verification_token = ? AND email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $db->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?")->execute([$user['id']]);
            $_SESSION['success'] = "Email verified! You can now log in.";
        } else {
            $_SESSION['error'] = "Invalid or expired link.";
        }

        header('Location: /login');
        exit;
    }

    /**
     * Logout user, clear session data and remember token cookie
     */
    public function logout(): void {
        if (isset($_COOKIE['remember_me_token'])) {
            if (isset($_SESSION['user_id'])) {
                User::updateRememberToken((int)$_SESSION['user_id'], null);
            }
            setcookie('remember_me_token', '', time() - 3600, '/');
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: /login');
        exit;
    }

    /**
     * Verifies the Cloudflare Turnstile token with the Cloudflare API.
     *
     * @return bool True if verification passes or Turnstile is disabled, false otherwise.
     */
    private function validateTurnstile(): bool {
        // Retrieve system settings
        $settings = \SystemSetting::getAll(); 
        $enabled = ($settings['enable_turnstile'] ?? '0') === '1';
        
        // If Turnstile is disabled from the admin panel, automatically pass validation
        if (!$enabled) {
            return true;
        }

        // Retrieve the token generated by the Turnstile widget
        $token = $_POST['cf-turnstile-response'] ?? '';
        if (empty($token)) {
            return false;
        }

        $secretKey = $settings['turnstile_secret_key'] ?? '';
        $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        
        // Prepare the payload for Cloudflare API
        $data = [
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        // Set up the POST request
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context  = stream_context_create($options);
        $result = @file_get_contents($verifyUrl, false, $context);
        
        if ($result === false) {
            return false;
        }

        // Decode the JSON response
        $responseData = json_decode($result, true);
        return $responseData['success'] ?? false;
    }

     /**
     * Render the reset password form from email token
     */
    public function showResetPassword(): void {
        $token = trim($_GET['token'] ?? '');
        if (empty($token)) {
            $_SESSION['error'] = "Invalid or missing password reset token.";
            header('Location: /login');
            exit;
        }

        $user = User::findByResetToken($token);
        if (!$user) {
            $_SESSION['error'] = "This reset link is invalid or has expired.";
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../Views/auth/reset-password.php';
    }

    /**
     * Process password reset submission
     */
    public function resetPassword(): void {
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirmation'] ?? '';

        if (empty($token) || empty($password)) {
            $_SESSION['error'] = "Please fill in all required fields.";
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        if ($password !== $confirmPassword) {
            $_SESSION['error'] = "Passwords do not match.";
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        if (strlen($password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters long.";
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        $user = User::findByResetToken($token);
        if (!$user) {
            $_SESSION['error'] = "This reset link is invalid or has expired.";
            header('Location: /login');
            exit;
        }

        $newPasswordHash = password_hash($password, PASSWORD_ARGON2ID);
        if (User::updatePassword((int)$user['id'], $newPasswordHash)) {
            $_SESSION['success'] = "Password updated successfully. You can now log in.";
            header('Location: /login');
        } else {
            $_SESSION['error'] = "Failed to update password. Please try again.";
            header('Location: /reset-password?token=' . urlencode($token));
        }
        exit;
    }

}
