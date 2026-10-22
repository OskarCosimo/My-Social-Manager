<?php
// app/Core/Language.php

require_once 'Config.php';
require_once __DIR__ . '/../Models/SystemSetting.php';

class Language {
    private array $translations = [];
    private array $fallbackTranslations = [];
    private string $currentLang;

    public function __construct(?string $lang = null) {
        $this->currentLang = $lang ?? Config::DEFAULT_LANG;
        $this->loadTranslations();
    }

    /**
     * Load the JSON file for the selected language and fallback language (English)
     */
    private function loadTranslations(): void {
        $langDir = dirname(__DIR__, 2) . '/lang';
        
        // Always load English as primary fallback if active language is different
        if ($this->currentLang !== 'en') {
            $fallbackPath = $langDir . '/en.json';
            if (file_exists($fallbackPath)) {
                $fallbackJson = file_get_contents($fallbackPath);
                $this->fallbackTranslations = json_decode($fallbackJson, true) ?? [];
            }
        }

        // Load active language file
        $filePath = $langDir . '/' . $this->currentLang . '.json';
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            $this->translations = json_decode($json, true) ?? [];
        } else {
            error_log("Language file not found for: " . $this->currentLang);
            // Fallback directly to English translations if file is missing
            $this->translations = $this->fallbackTranslations;
        }
    }

    /**
     * Translate a specific key with fallback support and placeholder replacements
     */
    public function get(string $key, array $replacements = []): string {
        // Look up key in active language, fallback to English if missing, or return key
        $text = $this->translations[$key] 
            ?? $this->fallbackTranslations[$key] 
            ?? $key;

        // Replace placeholders like {name} with values
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace('{' . $placeholder . '}', (string)$value, $text);
        }

        return $text;
    }

    /**
     * Get list of all installed language codes based on existing .json files in /lang/
     */
    public static function getInstalledLanguages(): array {
        $langDir = dirname(__DIR__, 2) . '/lang';
        $languages = [];
        if (is_dir($langDir)) {
            $files = scandir($langDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $languages[] = basename($file, '.json');
                }
            }
        }
        return $languages;
    }

    /**
     * Get list of allowed/enabled language codes for dropdown selectors
     */
    public static function getEnabledLanguages(): array {
        $installed = self::getInstalledLanguages();
        $saved = SystemSetting::get('enabled_languages');

        if (!empty($saved)) {
            $decoded = json_decode($saved, true);
            if (is_array($decoded) && !empty($decoded)) {
                $filtered = array_values(array_intersect($decoded, $installed));
                if (!empty($filtered)) {
                    return $filtered;
                }
            }
        }

        return !empty($installed) ? $installed : ['en'];
    }
}