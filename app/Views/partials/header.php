<?php
// app/Views/partials/header.php

require_once __DIR__ . '/../../Models/SystemSetting.php';

// Fetch custom header scripts (e.g., Google Analytics, AdSense)
$customHeaderScripts = SystemSetting::get('custom_header_script') ?? '';

// Fetch dynamic favicon or fallback to default /public/assets/images/favicon.png
$appFavicon = SystemSetting::get('app_favicon');
if (empty($appFavicon)) {
    $appFavicon = '/public/assets/images/favicon.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Social Manager</title>

    <!-- Favicon & Icons -->
    <link rel="icon" href="<?= htmlspecialchars($appFavicon) ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= htmlspecialchars($appFavicon) ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($appFavicon) ?>">

    <!-- PWA Web App Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Social Manager">

    <!-- Early Theme Detection Script to Prevent FOUC -->
    <script>
        (function() {
            // Detect OS theme preference
            function getSystemTheme() {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            // Retrieve saved user preference from localStorage
            const savedTheme = localStorage.getItem('app_theme');

            // Determine active theme (User preference -> System fallback)
            let activeTheme = savedTheme;
            if (!savedTheme || savedTheme === 'auto') {
                activeTheme = getSystemTheme();
            }

            // Apply theme to the root <html> element
            document.documentElement.setAttribute('data-bs-theme', activeTheme);
        })();
    </script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js', { scope: '/' })
                    .then(function(registration) {
                        // Service worker registered successfully with root scope
                    })
                    .catch(function(error) {
                        // Registration failed gracefully without interrupting user flow
                        console.debug('Service Worker registration skipped or failed:', error);
                    });
            });
        }
    </script>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Custom Injected Header Scripts -->
    <?= $customHeaderScripts ?>
</head>
<body>