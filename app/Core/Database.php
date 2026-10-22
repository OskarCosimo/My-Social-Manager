<?php
// app/Core/Database.php

require_once 'Config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = Config::DB_HOST;
        $port = defined('Config::DB_PORT') ? Config::DB_PORT : 3306;
        $db   = Config::DB_NAME;
        $user = Config::DB_USER;
        $pass = Config::DB_PASS;
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}