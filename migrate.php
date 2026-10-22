<?php
// migrate.php
// Safe incremental database migration script executed during updates

$rootDir = __DIR__;

// Load configuration and database connection
require_once $rootDir . '/app/Core/Config.php';

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

    // Example migration for future releases:
    // if (migration_table_exists($pdo, 'users') && !migration_column_exists($pdo, 'users', 'two_factor_confirmed_at')) {
    //     $pdo->exec("ALTER TABLE `users` ADD COLUMN `two_factor_confirmed_at` DATETIME NULL AFTER `two_factor_enabled`;");
    // }

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