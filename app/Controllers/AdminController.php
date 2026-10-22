<?php
// app/Controllers/AdminController.php

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/PluginSetting.php';
require_once __DIR__ . '/../Core/PluginManager.php';
require_once __DIR__ . '/../Models/Plan.php';
require_once __DIR__ . '/../Models/SystemSetting.php';
require_once __DIR__ . '/../Core/CronRunner.php';
require_once __DIR__ . '/../Core/Language.php';

class AdminController {

    /**
     * Constructor enforcing admin privileges for all endpoints
     */
    public function __construct() {
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            die("403 - Forbidden: Administration access only.");
        }
    }

    /**
     * Render the main Admin Dashboard
     */
    public function index(): void {
        // 1. Load Users and Plans
        $users = User::getAll();
        $plans = Plan::getAll();

        // 2. Load Plugins and saved settings
        $pluginManager = new PluginManager();
        $plugins = $pluginManager->getPlugins();
        
        $pluginSavedSettings = [];
        foreach ($plugins as $plugin) {
            $pluginSavedSettings[$plugin->getId()] = PluginSetting::getForPlugin($plugin->getId());
        }

        // 3. Load Available and Enabled Interface Languages
        $availableLanguages = Language::getInstalledLanguages();
        $languages = $availableLanguages;
        $enabledLanguages = Language::getEnabledLanguages();

        // 4. Load System Settings
        $sysSettings = SystemSetting::getAll();

        // 5. Load Cron Information (Queue, Failed Jobs, and Internal Status)
        $cronTasks = CronRunner::getTasksStatus();
        $queuedJobs = CronRunner::getQueuedPosts(100);
        $failedJobs = CronRunner::getFailedPosts(100);

        require_once __DIR__ . '/../Views/admin/index.php';
    }

    /**
     * Save Branding Settings (Logo, Favicon, and Sidebar Visibility)
     * Enforces strict file validation, 2MB size limit, and uploads folder hardening.
     */
    public function saveBrandingSettings(): void {
        $uploadDir = dirname(__DIR__, 2) . '/public/assets/images';
        
        // Ensure upload directory exists and is secured
        $this->ensureSecureUploadDirectory($uploadDir);

        // Maximum file size in bytes (2MB)
        $maxSizeBytes = 2 * 1024 * 1024;

        // 1. Process Logo
        if (isset($_POST['reset_logo']) && $_POST['reset_logo'] === '1') {
            SystemSetting::save('app_logo', '');
        } elseif (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedPath = $this->handleSecureUpload($_FILES['logo_file'], $uploadDir, 'logo', $maxSizeBytes);
            if ($uploadedPath !== null) {
                SystemSetting::save('app_logo', $uploadedPath);
            } else {
                header('Location: /admin#branding');
                exit;
            }
        } elseif (isset($_POST['logo_url'])) {
            SystemSetting::save('app_logo', trim((string)$_POST['logo_url']));
        }

        // 2. Process Favicon
        if (isset($_POST['reset_favicon']) && $_POST['reset_favicon'] === '1') {
            SystemSetting::save('app_favicon', '');
        } elseif (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedPath = $this->handleSecureUpload($_FILES['favicon_file'], $uploadDir, 'favicon', $maxSizeBytes);
            if ($uploadedPath !== null) {
                SystemSetting::save('app_favicon', $uploadedPath);
            } else {
                header('Location: /admin#branding');
                exit;
            }
        } elseif (isset($_POST['favicon_url'])) {
            SystemSetting::save('app_favicon', trim((string)$_POST['favicon_url']));
        }

        // 3. Process Hide Sidebar Logo Option
        $hideSidebarLogo = isset($_POST['hide_sidebar_logo']) ? '1' : '0';
        SystemSetting::save('hide_sidebar_logo', $hideSidebarLogo);

        $_SESSION['success'] = "Branding settings saved successfully.";
        header('Location: /admin#branding');
        exit;
    }

    /**
     * Validate and securely store an uploaded image file
     */
    private function handleSecureUpload(array $file, string $destinationDir, string $prefix, int $maxSizeBytes): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
                ? "The uploaded file exceeds the 2MB size limit."
                : "An error occurred during file upload (Code: {$file['error']}).";
            return null;
        }

        if ($file['size'] > $maxSizeBytes) {
            $_SESSION['error'] = "The uploaded file is too large. Maximum allowed size is 2MB.";
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'ico', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            $_SESSION['error'] = "Invalid file extension. Allowed extensions: " . implode(', ', $allowedExts);
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/png',
            'image/jpeg',
            'image/svg+xml',
            'image/x-icon',
            'image/vnd.microsoft.icon',
            'image/webp'
        ];

        if (!in_array($mime, $allowedMimes, true)) {
            $_SESSION['error'] = "Invalid file MIME type detected ({$mime}).";
            return null;
        }

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $_SESSION['error'] = "File corruption detected: not a valid image.";
                return null;
            }
        }

        if ($ext === 'svg' || $mime === 'image/svg+xml') {
            $svgContent = file_get_contents($file['tmp_name']);
            $blacklistedPatterns = [
                '/<script\b[^>]*>/i',
                '/<\/script>/i',
                '/javascript\s*:/i',
                '/onload\s*=/i',
                '/onerror\s*=/i',
                '/onclick\s*=/i',
                '/onmouseover\s*=/i',
                '/<iframe\b[^>]*>/i',
                '/<object\b[^>]*>/i',
                '/<embed\b[^>]*>/i',
                '/<\?php/i'
            ];

            foreach ($blacklistedPatterns as $pattern) {
                if (preg_match($pattern, $svgContent)) {
                    $_SESSION['error'] = "Security alert: SVG file contains forbidden active scripts or tags.";
                    return null;
                }
            }
        }

        $safeFilename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $destination = $destinationDir . '/' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $_SESSION['error'] = "Failed to move uploaded file to target directory.";
            return null;
        }

        return '/public/assets/images/' . $safeFilename;
    }

    /**
     * Create directory and place .htaccess + index.html to prevent execution and directory listing
     */
    private function ensureSecureUploadDirectory(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $indexFile = $dir . '/index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory access is forbidden.</h1></body></html>');
        }

        $htaccessFile = $dir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $htaccessRules = "# Disable script execution in uploads directory\n"
                           . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phar|pl|py|cgi|sh|bash)$\">\n"
                           . "    Order Allow,Deny\n"
                           . "    Deny from all\n"
                           . "</FilesMatch>\n"
                           . "Options -Indexes -ExecCGI\n";
            file_put_contents($htaccessFile, $htaccessRules);
        }
    }

    /**
     * Toggle or save internal PHP cron settings
     */
    public function saveCronSettings(): void {
        $enableInternalCron = isset($_POST['enable_internal_cron']) ? '1' : '0';
        SystemSetting::save('enable_internal_cron', $enableInternalCron);

        $_SESSION['success'] = "Cron system configuration updated successfully.";
        header('Location: /admin#cron');
        exit;
    }

    /**
     * Manually trigger an internal cron worker
     */
    public function runCronManual(): void {
        $task = $_POST['task_key'] ?? '';
        if (empty($task)) {
            $_SESSION['error'] = "No task specified.";
            header('Location: /admin#cron');
            exit;
        }

        $output = CronRunner::executeTask($task);
        $_SESSION['success'] = "Task executed. Output: " . htmlspecialchars($output);
        header('Location: /admin#cron');
        exit;
    }

    /**
     * Retry a failed post
     */
    public function retryFailedPost(): void {
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId <= 0) {
            $_SESSION['error'] = "Invalid post ID.";
            header('Location: /admin#cron');
            exit;
        }

        if (CronRunner::retryPost($postId)) {
            $_SESSION['success'] = "Post #{$postId} queued for retry.";
        } else {
            $_SESSION['error'] = "Failed to requeue post #{$postId}.";
        }

        header('Location: /admin#cron');
        exit;
    }

    /**
     * Admin functionality to manually change user status (active/banned)
     */
    public function toggleUserStatus(): void {
        if (empty($_POST['user_id']) || empty($_POST['status'])) {
            $_SESSION['error'] = "Invalid data provided.";
            header('Location: /admin#users');
            exit;
        }

        $userId = (int)$_POST['user_id'];
        $status = $_POST['status'];

        if (User::updateStatus($userId, $status)) {
            $_SESSION['success'] = "User status updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update user status.";
        }

        header('Location: /admin#users');
        exit;
    }

    /**
     * Change a user's subscription plan manually
     */
    public function changeUserPlan(): void {
        if (empty($_POST['user_id']) || empty($_POST['plan_id'])) {
            $_SESSION['error'] = "Invalid data provided.";
            header('Location: /admin#users');
            exit;
        }

        $userId = (int)$_POST['user_id'];
        $planId = (int)$_POST['plan_id'];

        if (User::updatePlan($userId, $planId)) {
            $_SESSION['success'] = "User plan updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update user plan.";
        }

        header('Location: /admin#users');
        exit;
    }

    /**
     * Save settings for a specific plugin
     */
    public function savePluginSettings(): void {
        $pluginId = $_POST['plugin_id'] ?? '';
        if (empty($pluginId)) {
            $_SESSION['error'] = "Plugin ID is missing.";
            header('Location: /admin#plugins');
            exit;
        }

        $postData = $_POST;
        unset($postData['plugin_id']);

        $isActive = isset($postData['is_active']) ? '1' : '0';
        PluginSetting::save($pluginId, 'is_active', $isActive);
        unset($postData['is_active']);

        foreach ($postData as $key => $value) {
            PluginSetting::save($pluginId, $key, trim((string)$value));
        }

        $_SESSION['success'] = "Plugin settings saved successfully.";
        header('Location: /admin#plugins');
        exit;
    }

    /**
     * Save Social Login / OAuth Configuration
     */
    public function saveAuthSettings(): void {
        $fields = [
            'enable_social_google', 'google_client_id', 'google_client_secret',
            'enable_social_facebook', 'facebook_client_id', 'facebook_client_secret',
            'enable_social_microsoft', 'microsoft_client_id', 'microsoft_client_secret',
            'enable_social_myetv', 'myetv_client_id', 'myetv_client_secret',
            'enable_turnstile', 'turnstile_site_key', 'turnstile_secret_key'
        ];

        foreach ($fields as $field) {
            $val = $_POST[$field] ?? '0';
            SystemSetting::save($field, trim((string)$val));
        }

        $_SESSION['success'] = "Authentication & OAuth settings saved successfully.";
        header('Location: /admin#auth');
        exit;
    }

    /**
     * Save Mailer / SMTP Settings
     */
    public function saveMailSettings(): void {
        $fields = [
            'mail_method', 'smtp_sender', 'smtp_host', 'smtp_port',
            'smtp_user', 'smtp_encryption', 'require_email_verification'
        ];

        foreach ($fields as $field) {
            $val = $_POST[$field] ?? '';
            SystemSetting::save($field, trim((string)$val));
        }

        if (!empty($_POST['smtp_pass'])) {
            SystemSetting::save('smtp_pass', trim((string)$_POST['smtp_pass']));
        }

        $_SESSION['success'] = "Mail & SMTP settings updated successfully.";
        header('Location: /admin#email');
        exit;
    }

    /**
     * Save Custom Scripts & Ads Injection
     */
    public function saveScriptsSettings(): void {
        $headerScripts = $_POST['custom_header_script'] ?? '';
        $footerScripts = $_POST['custom_footer_script'] ?? '';
        $adsScript     = $_POST['custom_ads_script'] ?? '';
        $adsFreeOnly   = isset($_POST['ads_free_only']) ? '1' : '0';

        SystemSetting::save('custom_header_script', $headerScripts);
        SystemSetting::save('custom_footer_script', $footerScripts);
        SystemSetting::save('custom_ads_script', $adsScript);
        SystemSetting::save('ads_free_only', $adsFreeOnly);

        $_SESSION['success'] = "Header, Footer scripts and Ads settings updated successfully.";
        header('Location: /admin#scripts');
        exit;
    }

    /**
     * Save AI and LibreTranslate settings by overwriting Config.php constants.
     */
    public function saveAiSettings(): void {
        $aiProvider = trim((string)($_POST['ai_provider'] ?? 'openai-chat'));
        $allowedProviders = ['openai-chat', 'ollama', 'gemini'];
        if (!in_array($aiProvider, $allowedProviders, true)) {
            $aiProvider = 'openai-chat';
        }

        $getField = function(string $fieldName): string {
            $b64Key = $fieldName . '_b64';
            if (isset($_POST[$b64Key]) && $_POST[$b64Key] !== '') {
                $decoded = base64_decode((string)$_POST[$b64Key], true);
                if ($decoded !== false) {
                    return trim($decoded);
                }
            }
            return trim((string)($_POST[$fieldName] ?? ''));
        };

        $constantsToUpdate = [
            'AI_PROVIDER'            => $aiProvider,
            'OPENAI_ENDPOINT'        => $getField('openai_endpoint'),
            'OPENAI_MODEL'           => trim((string)($_POST['openai_model'] ?? '')),
            'OPENAI_API_KEY'         => trim((string)($_POST['openai_api_key'] ?? '')),
            'OLLAMA_ENDPOINT'        => $getField('ollama_endpoint'),
            'OLLAMA_MODEL'           => trim((string)($_POST['ollama_model'] ?? '')),
            'GEMINI_API_KEY'         => trim((string)($_POST['gemini_api_key'] ?? '')),
            'LIBRETRANSLATE_URL'     => $getField('libretranslate_url'),
            'LIBRETRANSLATE_API_KEY' => trim((string)($_POST['libretranslate_api_key'] ?? '')),
        ];

        if ($this->updateConfigConstants($constantsToUpdate)) {
            $_SESSION['success'] = "AI & Translation settings updated in Config.php successfully.";
        } else {
            $_SESSION['error'] = "Failed to update Config.php. Please ensure the file has write permissions (chmod 664 or chown www-data).";
        }

        header('Location: /admin#ai');
        exit;
    }

    /**
     * Helper to safely update constant declarations in app/Core/Config.php
     */
    private function updateConfigConstants(array $constants): bool {
        $configFile = __DIR__ . '/../Core/Config.php';

        if (!is_file($configFile) || !is_writable($configFile)) {
            return false;
        }

        $content = file_get_contents($configFile);
        if ($content === false) {
            return false;
        }

        foreach ($constants as $key => $value) {
            $escapedValue = addcslashes($value, "'\\");
            $pattern = "/(public\s+const\s+" . preg_quote($key, '/') . "\s*=\s*)(['\"]).*?\\2(\s*;)/";
            $content = preg_replace_callback($pattern, function($matches) use ($escapedValue) {
                return $matches[1] . "'" . $escapedValue . "'" . $matches[3];
            }, $content);
        }

        return file_put_contents($configFile, $content) !== false;
    }

    /**
     * Save Enabled / Visible Languages for front-end selectors
     */
    public function saveEnabledLanguages(): void {
        $selected = $_POST['enabled_languages'] ?? [];
        if (!is_array($selected) || empty($selected)) {
            $_SESSION['error'] = __('at_least_one_language_required') ?? "At least one language must remain enabled.";
            header('Location: /admin#translations');
            exit;
        }

        $cleanLangs = [];
        foreach ($selected as $lang) {
            $code = strtolower(preg_replace('/[^a-z]/', '', (string)$lang));
            if (!empty($code)) {
                $cleanLangs[] = $code;
            }
        }

        if (!in_array(Config::DEFAULT_LANG, $cleanLangs, true)) {
            $cleanLangs[] = Config::DEFAULT_LANG;
        }

        SystemSetting::save('enabled_languages', json_encode(array_values(array_unique($cleanLangs))));
        $_SESSION['success'] = __('save_enabled_languages') ?? "Visible languages updated successfully.";
        header('Location: /admin#translations');
        exit;
    }

    /**
     * Auto-translate language file using LibreTranslate API with batching, placeholder shielding, and rate-limit handling
     */
    public function autoTranslate(): void {
        @set_time_limit(180);

        $input = json_decode(file_get_contents('php://input'), true);
        $targetLang = preg_replace('/[^a-z]/', '', $input['target_lang'] ?? '');

        if (empty($targetLang)) {
            echo json_encode(['success' => false, 'error' => 'Invalid language code.']);
            exit;
        }

        $englishFile = __DIR__ . '/../../lang/en.json';
        if (!file_exists($englishFile)) {
            echo json_encode(['success' => false, 'error' => 'English base file (en.json) not found.']);
            exit;
        }

        $englishContent = json_decode(file_get_contents($englishFile), true);
        if (!$englishContent) {
            echo json_encode(['success' => false, 'error' => 'English file is empty or corrupted.']);
            exit;
        }

        $translatedContent = [];
        $batchSize = 25; // 25 texts per HTTP call to easily bypass the 120 req/min limit
        $batches = array_chunk($englishContent, $batchSize, true);

        foreach ($batches as $batch) {
            $keys = array_keys($batch);
            $texts = array_values($batch);
            $protectedTexts = [];
            $batchTokenMaps = [];

            // Protect placeholders {name} AND DataTables variables _START_, _TOTAL_, _MENU_, etc. with numeric tokens
            foreach ($texts as $idx => $text) {
                preg_match_all('/(\{[a-zA-Z0-9_]+\}|_[A-Z0-9]+_)/', $text, $matches);
                $placeholders = $matches[0] ?? [];
                $protected = $text;
                $tokenMap = [];

                foreach ($placeholders as $pIdx => $placeholder) {
                    $token = "999" . str_pad((string)$pIdx, 2, "0", STR_PAD_LEFT);
                    $tokenMap[$token] = $placeholder;
                    $protected = str_replace($placeholder, $token, $protected);
                }

                $protectedTexts[] = $protected;
                $batchTokenMaps[$idx] = $tokenMap;
            }

            // Execute batch call to LibreTranslate
            $call = $this->callLibreTranslateBatch($protectedTexts, 'en', $targetLang);

            if (!$call['success']) {
                echo json_encode([
                    'success' => false, 
                    'error' => "LibreTranslate failed: {$call['error']}. Check server status and rate limits."
                ]);
                exit;
            }

            $results = $call['texts'];

            // Map results back to original keys and restore all protected tokens exactly
            foreach ($keys as $idx => $key) {
                $translated = $results[$idx] ?? $texts[$idx];
                $tokenMap = $batchTokenMaps[$idx] ?? [];

                foreach ($tokenMap as $token => $placeholder) {
                    $translated = str_replace($token, $placeholder, $translated);
                }

                // Clean duplicate stuttered single words
                $words = preg_split('/\s+/', trim($translated));
                if (count($words) === 2 && mb_strtolower($words[0]) === mb_strtolower($words[1])) {
                    $translated = $words[0];
                }

                $translatedContent[$key] = $translated;
            }

            // Polite delay between batches
            usleep(200000); // 0.2 seconds
        }

        $targetFile = __DIR__ . '/../../lang/' . $targetLang . '.json';
        if (file_put_contents($targetFile, json_encode($translatedContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => "Language file ({$targetLang}.json) successfully translated and saved."]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to write translated file. Check directory write permissions.']);
        }
        exit;
    }

    /**
     * Internal helper to execute batch cURL POST to LibreTranslate endpoint with automatic rate-limit retry
     */
    private function callLibreTranslateBatch(array $texts, string $source, string $target, int $retryCount = 0): array {
        $url = rtrim((string)Config::LIBRETRANSLATE_URL, '/');
        if (empty($url)) {
            return ['success' => false, 'texts' => [], 'error' => 'LibreTranslate URL is not configured.'];
        }

        if (!str_ends_with($url, '/translate')) {
            $url .= '/translate';
        }
        
        $data = [
            'q' => $texts,
            'source' => $source,
            'target' => $target,
            'format' => 'text'
        ];

        if (!empty(Config::LIBRETRANSLATE_API_KEY)) {
            $data['api_key'] = Config::LIBRETRANSLATE_API_KEY;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'texts' => [], 'error' => "cURL failed: {$curlError}"];
        }

        // Automatic 4-second wait and retry if hitting rate limiter (HTTP 429)
        if ($httpCode === 429 || str_contains((string)$response, 'Slowdown')) {
            if ($retryCount < 2) {
                sleep(4);
                return $this->callLibreTranslateBatch($texts, $source, $target, $retryCount + 1);
            }
            return ['success' => false, 'texts' => [], 'error' => 'Rate limit exceeded (Slowdown: 120/min).'];
        }

        if ($httpCode !== 200 || empty($response)) {
            $errResult = json_decode((string)$response, true);
            $msg = $errResult['error'] ?? "HTTP error {$httpCode}";
            return ['success' => false, 'texts' => [], 'error' => $msg];
        }

        $result = json_decode($response, true);
        $translated = $result['translatedText'] ?? null;

        if (is_array($translated)) {
            return ['success' => true, 'texts' => $translated, 'error' => ''];
        } elseif (is_string($translated) && count($texts) === 1) {
            return ['success' => true, 'texts' => [$translated], 'error' => ''];
        }

        return ['success' => false, 'texts' => [], 'error' => 'Invalid batch structure returned by LibreTranslate.'];
    }

    /**
     * Save Global Payment Gateway Settings & Platform Commission
     */
    public function savePaymentSettings(): void {
        $fields = [
            'stripe_secret_key', 'stripe_public_key', 'stripe_webhook_secret',
            'paypal_client_id', 'paypal_secret', 'paypal_mode',
            'platform_commission_percent'
        ];

        foreach ($fields as $field) {
            $val = $_POST[$field] ?? '';
            SystemSetting::save($field, trim((string)$val));
        }

        $_SESSION['success'] = "Payment gateways and platform commission settings saved.";
        header('Location: /admin#payments');
        exit;
    }
}