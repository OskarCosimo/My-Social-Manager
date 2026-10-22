<?php
// app/Controllers/ComposeController.php

require_once __DIR__ . '/../Models/SocialAccount.php';
require_once __DIR__ . '/../Core/PluginManager.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Models/Group.php';
require_once __DIR__ . '/../Models/MailingList.php';
require_once __DIR__ . '/../Core/Mailer.php';

class ComposeController {

    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance(); 
        
        // 1. Fetch Plan Limits (Monthly Usage)
        $limits = UsageManager::getUserLimits($userId);

        // 2. Fetch Post Status Statistics
        $statsStmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM posts 
            WHERE user_id = ?
        ");
        $statsStmt->execute([$userId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 
            'published' => 0, 
            'scheduled' => 0, 
            'failed' => 0
        ];

        // Fetch connected accounts
        $connectedAccounts = SocialAccount::getUserAccounts($userId);

        // Fetch User Groups
        $groupModel = new \App\Models\Group($db);
        $groups = $groupModel->getUserGroups($userId);

        // Fetch Mailing Lists
        $mailingLists = MailingList::getUserLists($userId);

        require_once __DIR__ . '/../Views/compose/index.php';
    }

    public function publish(): void {
        if (!isset($_SESSION['user_id'])) exit;

        $userId = (int)$_SESSION['user_id'];
        $content = $_POST['content'] ?? '';
        $targetAccounts = $_POST['target_accounts'] ?? [];
        $targetMailingLists = $_POST['target_mailing_lists'] ?? [];
        $scheduledInput = $_POST['scheduled_time'] ?? ($_POST['scheduled_at'] ?? '');
        
        // Catch the new source link input
        $sourceLink = !empty($_POST['source_link']) ? trim($_POST['source_link']) : null;
        
        // Catch the attached media array
        $mediaUrls = $_POST['media_urls'] ?? [];

        if (empty($content) && empty($mediaUrls) && empty($sourceLink)) {
            $_SESSION['error'] = "Post content, media, and link cannot all be empty.";
        } elseif (empty($targetAccounts) && empty($targetMailingLists)) {
            $_SESSION['error'] = "Please select at least one social account or mailing list.";
        } else {
            // --- USAGE ENFORCEMENT ---
            if (!UsageManager::canPublishPost($userId)) {
                $_SESSION['error'] = "Monthly post limit reached! Upgrade your plan to publish more.";
                header('Location: /compose');
                exit;
            }

            // Handle timezone conversion to UTC
            $scheduledForUTC = null;
            if (!empty($scheduledInput)) {
                try {
                    $userTz = new DateTimeZone($_SESSION['user_timezone'] ?? 'UTC');
                    $date = new DateTime($scheduledInput, $userTz);
                    $date->setTimezone(new DateTimeZone('UTC'));
                    $scheduledForUTC = $date->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $scheduledForUTC = null;
                }
            }

            // FALLBACK IMMEDIATO: Se la data è vuota, usa la data/ora attuale UTC
            if (empty($scheduledForUTC)) {
                $scheduledForUTC = gmdate('Y-m-d H:i:s');
            }

            // Encode the media array to JSON for database storage
            $mediaUrlJson = !empty($mediaUrls) ? json_encode($mediaUrls) : null;

            // 1. Pubblicazione sugli account Social
            if (!empty($targetAccounts)) {
                $db = Database::getInstance();
                
                // Query fedele alla tua struttura originale (user_id, social_account_id, content, media_urls, source_link, status, scheduled_for, scheduled_at)
                $stmt = $db->prepare("
                    INSERT INTO posts (user_id, social_account_id, content, media_urls, source_link, status, scheduled_for, scheduled_at) 
                    VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?)
                ");

                foreach ($targetAccounts as $accountId) {
                    $stmt->execute([
                        $userId, 
                        (int)$accountId, 
                        $content, 
                        $mediaUrlJson, 
                        $sourceLink,
                        $scheduledForUTC,
                        $scheduledForUTC
                    ]);
                }
            }

            // 2. Send to selected Mailinglist
            if (!empty($targetMailingLists)) {
                $totalRecipients = 0;
                $listsData = [];

                foreach ($targetMailingLists as $listId) {
                    $listId = (int)$listId;
                    $subscribers = MailingList::getActiveSubscribers($listId);
                    $listInfo = MailingList::getById($listId, $userId);
                    
                    if (!empty($subscribers)) {
                        $listsData[] = [
                            'info'        => $listInfo,
                            'subscribers' => $subscribers
                        ];
                        $totalRecipients += count($subscribers);
                    }
                }

                if ($totalRecipients > 0) {
                    if (!UsageManager::canSendEmails($userId, $totalRecipients)) {
                        $_SESSION['error'] = "Monthly email limit exceeded! You cannot send emails to " . $totalRecipients . " recipients with your current plan.";
                        header('Location: /compose');
                        exit;
                    }

                    $emailsSentCount = 0;
                    foreach ($listsData as $item) {
                        $subject = $item['info'] ? $item['info']['name'] : "Newsletter Update";

                        $htmlBody = "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
                        $htmlBody .= $content;

                        if (!empty($sourceLink)) {
                            $htmlBody .= "<p><a href='" . htmlspecialchars($sourceLink) . "' target='_blank'>" . htmlspecialchars($sourceLink) . "</a></p>";
                        }

                        if (!empty($mediaUrls)) {
                            $htmlBody .= "<div style='margin-top: 20px;'>";
                            foreach ($mediaUrls as $mediaUrl) {
                                $htmlBody .= "<p><a href='" . htmlspecialchars($mediaUrl) . "' target='_blank'><img src='" . htmlspecialchars($mediaUrl) . "' style='max-width: 100%; height: auto; border-radius: 8px;' alt='Attached Media' /></a></p>";
                            }
                            $htmlBody .= "</div>";
                        }
                        $htmlBody .= "</div>";

                        foreach ($item['subscribers'] as $subscriberEmail) {
                            if (Mailer::send($subscriberEmail, $subject, $htmlBody)) {
                                $emailsSentCount++;
                            }
                        }
                    }

                    if ($emailsSentCount > 0) {
                        UsageManager::incrementEmailUsage($userId, $emailsSentCount);
                    }
                }
            }

            // --- INCREMENT QUOTA ---
            UsageManager::incrementPostUsage($userId);
            
            $_SESSION['success'] = "Post / Newsletter dispatched successfully!";
        }

        header('Location: /compose');
        exit;
    }
}