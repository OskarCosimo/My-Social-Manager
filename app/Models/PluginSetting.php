<?php
// app/Models/PluginSetting.php

require_once __DIR__ . '/../Core/Database.php';

class PluginSetting {

    /**
     * Get all saved settings for a specific plugin
     */
    public static function getForPlugin(string $pluginId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM plugin_settings WHERE plugin_id = ?");
        $stmt->execute([$pluginId]);
        
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Save or update a specific setting for a plugin
     */
    public static function save(string $pluginId, string $key, string $value): bool {
        $db = Database::getInstance();
        $sql = "INSERT INTO plugin_settings (plugin_id, setting_key, setting_value) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$pluginId, $key, $value]);
    }
}