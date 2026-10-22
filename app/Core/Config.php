<?php
// app/Core/Config.php

class Config {
    // Application version and GitHub repository
    public const APP_VERSION = '1.0.0';
    public const GITHUB_REPO = 'OskarCosimo/My-Social-Manager';

    // Database connection details
    public const DB_HOST = '127.0.0.1';
    public const DB_PORT = 3306;
    public const DB_NAME = 'mysocialmanager';
    public const DB_USER = '';
    public const DB_PASS = '';

    // Application settings
    public const APP_URL = 'http://localhost';
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