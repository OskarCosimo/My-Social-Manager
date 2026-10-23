<?php
// install/index.php

$rootDir    = dirname(__DIR__);
$installDir = __DIR__;
$appCoreDir = $rootDir . '/app/Core';
$lockFile   = $installDir . '/installed.lock';

if (file_exists($lockFile)) {
    die("My Social Manager is already installed. Delete install/installed.lock if you want to reinstall.");
}

$errors = [];
$step = (int)($_GET['step'] ?? 1);

// Directory and environment requirements check
$requirements = [
    'PHP Version >= 8.2'         => version_compare(PHP_VERSION, '8.2.0', '>='),
    'PDO MySQL Extension'        => extension_loaded('pdo_mysql'),
    'cURL Extension'             => extension_loaded('curl'),
    'OpenSSL Extension'          => extension_loaded('openssl'),
    'ZipArchive Extension'       => extension_loaded('zip'),
    'Root Dir Writable (Update)' => is_writable($rootDir),
    'App Core Writable (Config)' => is_writable($appCoreDir),
    'Install Dir Writable'       => is_writable($installDir)
];

$allRequirementsPassed = !in_array(false, $requirements, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost    = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort    = (int)($_POST['db_port'] ?? 3306);
    $dbName    = trim($_POST['db_name'] ?? '');
    $dbUser    = trim($_POST['db_user'] ?? '');
    $dbPass    = $_POST['db_pass'] ?? '';
    $appUrl    = rtrim(trim($_POST['app_url'] ?? ''), '/');
    
    $adminEmail    = strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPassword = $_POST['admin_password'] ?? '';

    try {
        // 1. Verify database connection
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // 2. Import database schema
        $schemaFile = $rootDir . '/database/database.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Database schema file not found at: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);

        // 3. Automatically generate high-entropy 256-bit cryptographic keys
        $encryptionKey = bin2hex(random_bytes(32));
        $hmacKey       = bin2hex(random_bytes(32));

        // 4. Encrypt administrator email and compute deterministic blind index
        $rawKey = hash('sha256', $encryptionKey, true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $adminEmail,
            'aes-256-gcm',
            $rawKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        $encryptedAdminEmail = base64_encode($iv . $tag . $ciphertext);
        $adminEmailHash = hash_hmac('sha256', $adminEmail, $hmacKey);

        // 5. Create administrator account
        $passwordHash = password_hash($adminPassword, PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, email_hash, password, role, email_verified, accountsocialmanager, plan_id, status)
            VALUES (?, ?, ?, 'admin', 1, 1, 1, 'active')
            ON DUPLICATE KEY UPDATE 
                email = VALUES(email),
                email_hash = VALUES(email_hash),
                password = VALUES(password),
                role = 'admin',
                status = 'active'
        ");
        $stmt->execute([$encryptedAdminEmail, $adminEmailHash, $passwordHash]);

        // 6. Generate and write app/Core/Config.php
        $escapedDbPass = addcslashes($dbPass, "'\\");
        $escapedDbUser = addcslashes($dbUser, "'\\");
        $escapedDbName = addcslashes($dbName, "'\\");
        $escapedDbHost = addcslashes($dbHost, "'\\");
        $escapedAppUrl = addcslashes($appUrl, "'\\");

$configTemplate = <<<PHP
<?php
// app/Core/Config.php

class Config {
    // Application version and GitHub repository
    public const APP_VERSION = '1.0.0';
    public const GITHUB_REPO = 'OskarCosimo/My-Social-Manager';

    // Database connection details
    public const DB_HOST = '{$escapedDbHost}';
    public const DB_PORT = {$dbPort};
    public const DB_NAME = '{$escapedDbName}';
    public const DB_USER = '{$escapedDbUser}';
    public const DB_PASS = '{$escapedDbPass}';

    // Encryption Keys (Generated automatically during installation)
    public const ENCRYPTION_KEY = '{$encryptionKey}';
    public const HMAC_KEY       = '{$hmacKey}';

    // Application settings
    public const APP_URL = '{$escapedAppUrl}';
    public const DEFAULT_LANG = 'en';
    
    // AI Configuration ('openai-chat', 'ollama', or 'gemini')
    public const AI_PROVIDER = 'openai-chat';
    
    // AI SETUP
    public const OPENAI_ENDPOINT = 'http://localhost:11435/v1/chat/completions';
    public const OPENAI_MODEL = '';
    public const OPENAI_API_KEY = '';

    // Ollama Native API Configuration
    public const OLLAMA_ENDPOINT = 'http://localhost:11434/api/generate';
    public const OLLAMA_MODEL = '';

    // Google Gemini API Configuration
    public const GEMINI_API_KEY = '';

    // Translation Config
    public const LIBRETRANSLATE_URL = 'http://localhost:5000/translate';
    public const LIBRETRANSLATE_API_KEY = '';
}
PHP;

        $configFile = $appCoreDir . '/Config.php';
        if (file_put_contents($configFile, $configTemplate) === false) {
            throw new Exception("Unable to write configuration file at: {$configFile}");
        }

        // 7. Create installation lock file
        if (file_put_contents($lockFile, date('Y-m-d H:i:s')) === false) {
            throw new Exception("Unable to create installation lock file at: {$lockFile}");
        }

        $step = 3;
    } catch (\Throwable $e) {
        $errors[] = "Installation Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Social Manager - Installation Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-share me-2"></i>My Social Manager - Setup Wizard</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>
                            <?= implode('<br>', $errors) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                        <h5 class="fw-bold mb-3">Pre-Installation Checklist</h5>
                        <p class="text-muted small">Please verify that server dependencies and file write permissions are satisfied.</p>
                        
                        <ul class="list-group mb-4 shadow-sm">
                            <?php foreach ($requirements as $name => $ok): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($name) ?></span>
                                    <span class="badge bg-<?= $ok ? 'success' : 'danger' ?> px-3 py-2">
                                        <?= $ok ? 'PASSED' : 'FAILED' ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($allRequirementsPassed): ?>
                            <a href="?step=2" class="btn btn-primary w-100 py-2 fw-bold">Proceed to Configuration &rarr;</a>
                        <?php else: ?>
                            <div class="alert alert-warning small">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Some requirements failed. Adjust file permissions in your server terminal:
                                <pre class="mt-2 bg-dark text-white p-2 rounded"><code>sudo chown -R www-data:www-data <?= htmlspecialchars($rootDir) ?><br>sudo chmod -R 755 <?= htmlspecialchars($rootDir) ?></code></pre>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($step === 2): ?>
                        <form method="POST">
                            <h5 class="fw-bold mb-3">1. Database Configuration</h5>
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Host</label>
                                    <input type="text" name="db_host" value="127.0.0.1" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Port</label>
                                    <input type="number" name="db_port" value="3306" class="form-control" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Database Name</label>
                                    <input type="text" name="db_name" value="mysocialmanager" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Username</label>
                                    <input type="text" name="db_user" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Password</label>
                                    <input type="password" name="db_pass" class="form-control">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3 mt-4">2. Application URL</h5>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">App URL (without trailing slash)</label>
                                <input type="url" name="app_url" value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') ?>" class="form-control" required>
                            </div>

                            <h5 class="fw-bold mb-3 mt-4">3. Administrator Account</h5>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Admin Email</label>
                                <input type="email" name="admin_email" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Admin Password (min. 8 characters)</label>
                                <input type="password" name="admin_password" class="form-control" required minlength="8">
                            </div>

                            <div class="alert alert-info small d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-shield-lock-fill fs-4 flex-shrink-0"></i>
                                <div>
                                    <strong>Automatic Security at Rest:</strong> Cryptographic keys (AES-256-GCM & HMAC-SHA256) will be generated automatically and saved to your configuration file.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Install My Social Manager</button>
                        </form>

                    <?php elseif ($step === 3): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            <h3 class="text-success fw-bold mt-2">Installation Successful!</h3>
                            <p class="text-muted">Configuration files, encryption keys, and database schema have been created successfully.</p>
                            <a href="/login" class="btn btn-primary px-4 py-2 mt-2 fw-bold">Go to Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
