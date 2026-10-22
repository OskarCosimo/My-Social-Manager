<?php
// app/Controllers/NewsletterController.php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Mailer.php';
require_once __DIR__ . '/../Core/Crypto.php';

class NewsletterController {

    /**
     * Display the Newsletter compose form
     */
    public function index(): void {
        // Strict security check: only admins allowed
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            $_SESSION['error'] = "Unauthorized access.";
            header('Location: /dashboard');
            exit;
        }

        require_once __DIR__ . '/../Views/newsletter/index.php';
    }

    /**
     * Process the newsletter broadcast
     */
    public function send(): void {
        // Strict security check
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /dashboard');
            exit;
        }

        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';

        if (empty($subject) || empty($body)) {
            $_SESSION['error'] = "Subject and Body are required.";
            header('Location: /newsletter');
            exit;
        }

        $db = Database::getInstance();

        // Fetch ALL valid users from the local My Social Manager database
        $stmt = $db->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            $_SESSION['error'] = "No users found in the local database.";
            header('Location: /newsletter');
            exit;
        }

        $successCount = 0;
        $errorCount = 0;

        // Loop through all users, decrypt email address and send the newsletter
        foreach ($users as $user) {
            $to = Crypto::decrypt($user['email']);

            if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $errorCount++;
                continue;
            }
            
            // Send email using our centralized Mailer class
            if (Mailer::send($to, $subject, $body)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        // Prepare the feedback message
        if ($errorCount === 0) {
            $_SESSION['success'] = "Newsletter sent successfully to {$successCount} users!";
        } else {
            $_SESSION['success'] = "Broadcast completed: {$successCount} sent, {$errorCount} failed.";
        }

        header('Location: /newsletter');
        exit;
    }
}
