<?php
// app/Core/PluginManager.php

require_once __DIR__ . '/SocialPluginInterface.php';
require_once __DIR__ . '/../Models/PluginSetting.php';

class PluginManager {
    
    private array $plugins = [];

    public function __construct() {
        $this->loadPlugins();
    }

    /**
     * Scan the plugins directory and instantiate available plugins
     */
    private function loadPlugins(): void {
        $pluginDir = __DIR__ . '/../../plugins/';
        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
            return;
        }

        $directories = glob($pluginDir . '*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            $mainFile = $dir . '/' . $pluginName . 'Plugin.php';
            
            if (file_exists($mainFile)) {
                require_once $mainFile;
                $className = $pluginName . 'Plugin';
                
                if (class_exists($className)) {
                    $instance = new $className();
                    if ($instance instanceof SocialPluginInterface) {
                        $this->plugins[$instance->getId()] = $instance;
                    }
                }
            }
        }
    }

    /**
     * Get all loaded plugins (Used in Admin Dashboard)
     */
    public function getPlugins(): array {
        return $this->plugins;
    }

    /**
     * Get a specific plugin by ID
     */
    public function getPlugin(string $id): ?SocialPluginInterface {
        return $this->plugins[$id] ?? null;
    }

    /**
     * Get ONLY active plugins (Used for User Dashboard)
     */
    public function getActivePlugins(): array {
        $activePlugins = [];
        foreach ($this->plugins as $id => $plugin) {
            $settings = PluginSetting::getForPlugin($id);
            if (isset($settings['is_active']) && $settings['is_active'] === '1') {
                $activePlugins[$id] = $plugin;
            }
        }
        return $activePlugins;
    }

    /**
     * Get dynamic brand color mapping for all registered plugins
     */
    public function getPluginColors(): array {
        $colorMap = [];
        foreach ($this->plugins as $id => $plugin) {
            $pluginKey = strtolower($id);
            if (method_exists($plugin, 'getColor')) {
                $colorMap[$pluginKey] = $plugin->getColor();
            } else {
                // Fallback to dynamic HSL hash if getColor() is missing
                $hash = md5($pluginKey);
                $hue = hexdec(substr($hash, 0, 6)) % 360;
                $colorMap[$pluginKey] = "hsl({$hue}, 70%, 45%)";
            }
        }
        return $colorMap;
    }
}