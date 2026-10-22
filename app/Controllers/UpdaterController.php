<?php
// app/Controllers/UpdaterController.php

require_once __DIR__ . '/../Services/UpdaterService.php';

class UpdaterController {
    private UpdaterService $updater;

    public function __construct() {
        // Enforce admin authorization guard
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: /login');
            exit;
        }

        $this->updater = new UpdaterService();
    }

    public function index(): void {
        $currentVersion = $this->updater->getCurrentVersion();
        $updateInfo     = $this->updater->checkForUpdates();
        $permCheck      = $this->updater->checkWritePermissions();

        require __DIR__ . '/../Views/admin/updater.php';
    }

    public function apply(): void {
        $zipUrl = $_POST['zip_url'] ?? '';

        if (empty($zipUrl)) {
            header('Location: /admin/updater?error=invalid_url');
            exit;
        }

        try {
            $this->updater->applyUpdate($zipUrl);
            header('Location: /admin/updater?updated=1');
            exit;
        } catch (\Throwable $e) {
            header('Location: /admin/updater?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}