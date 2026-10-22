<?php
// app/Core/Helpers.php

require_once 'Language.php';

// Create a static instance holder to avoid loading the JSON file multiple times per request
class TranslatorInstance {
    public static ?Language $instance = null;

    public static function get(): Language {
        if (self::$instance === null) {
            // Prioritize cookie language first, then session, then fallback to default config
            $userLang = $_COOKIE['lang'] ?? $_SESSION['app_language'] ?? Config::DEFAULT_LANG;
            self::$instance = new Language($userLang);
        }
        return self::$instance;
    }
}

/**
 * Global helper function for translations.
 */
function __(string $key, array $replacements = []): string {
    return TranslatorInstance::get()->get($key, $replacements);
}