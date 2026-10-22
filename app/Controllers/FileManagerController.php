<?php
// app/Controllers/FileManagerController.php

class FileManagerController {

    public function index(): void {
        if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
        require_once __DIR__ . '/../Views/filemanager/index.php';
    }

    // New method for the iframe content
    public function standalone(): void {
        if (!isset($_SESSION['user_id'])) { exit; }
        require_once __DIR__ . '/../Views/filemanager/standalone.php';
    }
}