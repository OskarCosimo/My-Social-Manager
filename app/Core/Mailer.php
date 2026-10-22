<?php
// app/Core/Mailer.php

require_once __DIR__ . '/../Models/SystemSetting.php';

// Manually require the PHPMailer core classes since we are bypassing Composer
// Navigating from app/Core/ up to the root, then down into public/assets/
require_once __DIR__ . '/../../public/assets/phpmailer6/src/Exception.php';
require_once __DIR__ . '/../../public/assets/phpmailer6/src/PHPMailer.php';
require_once __DIR__ . '/../../public/assets/phpmailer6/src/SMTP.php';

// Import the namespaces into the current context
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {

    /**
     * Centralized method to send emails
     * * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody Email content in HTML
     * @return bool True on success, False on failure
     */
    public static function send(string $to, string $subject, string $htmlBody): bool {
        // Retrieve global mail configurations
        $settings = SystemSetting::getAll();
        
        $method = $settings['mail_method'] ?? 'local';
        $sender = $settings['smtp_sender'] ?? 'noreply@mysocialmanager.com';
        
        if ($method === 'smtp') {
            return self::sendViaSmtp($to, $subject, $htmlBody, $settings, $sender);
        }

        return self::sendViaLocal($to, $subject, $htmlBody, $sender);
    }

    /**
     * Send email using PHPMailer with SMTP configurations
     */
    private static function sendViaSmtp(string $to, string $subject, string $htmlBody, array $settings, string $sender): bool {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'] ?? '';
            $mail->SMTPAuth   = !empty($settings['smtp_user']);
            $mail->Username   = $settings['smtp_user'] ?? '';
            $mail->Password   = $settings['smtp_pass'] ?? '';
            $mail->Port       = (int)($settings['smtp_port'] ?? 587);

            // Handle encryption protocol
            $encryption = $settings['smtp_encryption'] ?? 'none';
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = '';
            }

            // Recipients
            $mail->setFrom($sender, 'My Social Manager');
            $mail->addAddress($to);

            // Content formatting
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // In a production environment, you should log $mail->ErrorInfo
            error_log("SMTP Mailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send email using native PHP mail() function
     */
    private static function sendViaLocal(string $to, string $subject, string $htmlBody, string $sender): bool {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: My Social Manager <{$sender}>\r\n";
        $headers .= "Reply-To: {$sender}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return mail($to, $subject, $htmlBody, $headers);
    }
}