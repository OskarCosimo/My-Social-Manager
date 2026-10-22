<?php
// app/Core/SocialPluginInterface.php

interface SocialPluginInterface {
    
    /**
     * Unique identifier for the plugin (e.g., 'facebook', 'x', 'myetv')
     */
    public function getId(): string;

    /**
     * Readable name (e.g., 'Facebook')
     */
    public function getName(): string;

    /**
     * Returns an array of required settings fields (e.g., App ID, App Secret)
     * This allows the Settings page to auto-generate the form
     */
    public function getSettingsFields(): array;

    /**
     * Returns the OAuth connection URL
     */
    public function getAuthUrl(): string;

    /**
     * Handles the OAuth callback and returns access tokens
     */
    public function handleCallback(array $requestData): array;

    /**
     * Publishes a post to the platform
     */
    public function publishPost(string $content, array $mediaUrls, string $accessToken): array;
}