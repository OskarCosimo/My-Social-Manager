<?php
// plugins/bluesky/index.php

session_start();

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Models/UsageManager.php';
require_once __DIR__ . '/../../app/Models/SocialAccount.php';
require_once __DIR__ . '/BlueskyApi.php';

$action = $_GET['action'] ?? '';

if ($action === 'connect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized");
    }

    $userId = (int)$_SESSION['user_id'];
    $handle = trim($_POST['handle'] ?? '');
    $appPassword = trim($_POST['app_password'] ?? '');

    if (!UsageManager::canAddAccount($userId)) {
        $_SESSION['error'] = "Max account limit reached. Please upgrade your plan.";
        header('Location: /dashboard/accounts');
        exit;
    }

    try {
        $api = new BlueskyApi();
        $sessionData = $api->createSession($handle, $appPassword);

        SocialAccount::save([
            'user_id' => $userId,
            'platform' => 'bluesky',
            'account_id' => $sessionData['did'],
            'account_name' => $sessionData['handle'],
            'access_token' => $appPassword
        ]);

        $_SESSION['success'] = "Bluesky connected successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: /dashboard/accounts');
    exit;
}