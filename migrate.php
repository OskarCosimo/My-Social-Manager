<?php
// migrate.php
// Safe incremental database migration script executed during updates

$rootDir = __DIR__;

// Load configuration and database connection
require_once $rootDir . '/app/Core/Config.php';
require_once $rootDir . '/app/Core/Crypto.php';

try {
    $host = Config::DB_HOST;
    $port = defined('Config::DB_PORT') ? Config::DB_PORT : 3306;
    $db   = Config::DB_NAME;
    $user = Config::DB_USER;
    $pass = Config::DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\Throwable $e) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Migration Error: Unable to connect to database: " . $e->getMessage() . "\n");
        exit(1);
    }
    return;
}

/**
 * Helper: Check if a column exists in a given table
 */
function migration_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.columns 
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Helper: Check if an index exists in a given table
 */
function migration_index_exists(PDO $pdo, string $table, string $indexName): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.statistics 
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
        ");
        $stmt->execute([$table, $indexName]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Helper: Check if a table exists in the database
 */
function migration_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

try {
    // =========================================================================
    // INCREMENTAL MIGRATIONS LOGIC
    // Append version checks and idempotent schema changes below
    // =========================================================================

    // Migration: Encrypt mailing list subscriber emails at rest and add deterministic HMAC blind index
    if (migration_table_exists($pdo, 'mailing_list_subscribers')) {
        // 1. Add email_hash column and expand email column to TEXT if not present
        if (!migration_column_exists($pdo, 'mailing_list_subscribers', 'email_hash')) {
            $pdo->exec("
                ALTER TABLE `mailing_list_subscribers` 
                MODIFY `email` TEXT COLLATE utf8mb4_bin NOT NULL,
                ADD COLUMN `email_hash` VARCHAR(64) COLLATE utf8mb4_bin DEFAULT NULL AFTER `email`
            ");
        }

        // 2. Encrypt existing plain emails and calculate email_hash for unmigrated rows
        $stmt = $pdo->query("SELECT `id`, `email` FROM `mailing_list_subscribers` WHERE `email_hash` IS NULL OR `email_hash` = ''");
        $unhashedSubscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($unhashedSubscribers)) {
            $updateStmt = $pdo->prepare("UPDATE `mailing_list_subscribers` SET `email` = ?, `email_hash` = ? WHERE `id` = ?");
            foreach ($unhashedSubscribers as $sub) {
                // If already encrypted, Crypto::decrypt retrieves the plain text; otherwise falls back to raw value
                $plainEmail = strtolower(trim(Crypto::decrypt($sub['email']) ?? $sub['email']));
                $encryptedEmail = Crypto::encrypt($plainEmail);
                $emailHash = Crypto::hashEmail($plainEmail);

                $updateStmt->execute([$encryptedEmail, $emailHash, $sub['id']]);
            }
        }

        // 3. Drop legacy unique key on (list_id, email) if still present
        $stmtIdx = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
              AND table_name = 'mailing_list_subscribers' 
              AND index_name = 'unique_subscription' 
              AND column_name = 'email'
        ");
        $stmtIdx->execute();
        $isOldIndex = (bool)$stmtIdx->fetchColumn();

        if ($isOldIndex) {
            $pdo->exec("ALTER TABLE `mailing_list_subscribers` DROP INDEX `unique_subscription`");
        }

        // 4. Create unique key on (list_id, email_hash)
        if (!migration_index_exists($pdo, 'mailing_list_subscribers', 'unique_subscription')) {
            $pdo->exec("ALTER TABLE `mailing_list_subscribers` ADD UNIQUE KEY `unique_subscription` (`list_id`, `email_hash`)");
        }
    }

    if (php_sapi_name() === 'cli') {
        echo "Database migrations executed successfully.\n";
    }
} catch (\Throwable $e) {
    error_log("Database Migration Failed: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}
