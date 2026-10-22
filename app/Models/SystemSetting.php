<?php
// app/Models/SystemSetting.php

require_once __DIR__ . '/../Core/Database.php';

class SystemSetting {
    
    /**
     * Retrieve all system settings as an associative array.
     * Uses a universally compatible fetch method.
     *
     * @return array Associative array of all settings
     */
    public static function getAll(): array {
        $db = Database::getInstance();
        
        // Execute the query to fetch all settings
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        
        // Fetch all rows as an associative array
        $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        if ($rawResults) {
            foreach ($rawResults as $row) {
                // Manually map the key-value pairs for easy access
                $results[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        return $results;
    }

    /**
     * Retrieve a specific setting by its key.
     * Returns a fallback default value if the key is not found in the database.
     *
     * @param string $key     The setting key to look for
     * @param string $default The default value to return if not found
     * @return string
     */
    public static function get(string $key, string $default = ''): string {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? (string)$result : $default;
    }

    /**
     * Legacy method to update a specific setting.
     * Kept for backward compatibility with older parts of the app.
     *
     * @param string $key   The setting key
     * @param string|null $value The setting value
     */
    public static function set(string $key, ?string $value): void {
        self::save($key, (string)$value);
    }

    /**
     * Insert or update a system setting in the database.
     * Used heavily by the AdminController to save configurations.
     *
     * @param string $key   The setting key
     * @param string $value The setting value
     * @return void
     */
    public static function save(string $key, string $value): void {
        $db = Database::getInstance();
        
        // Use ON DUPLICATE KEY UPDATE to insert if it doesn't exist, or update if it does
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        $stmt->execute([$key, $value, $value]);
    }
}