<?php
// app/Controllers/MailingListController.php

require_once __DIR__ . '/../Models/MailingList.php';
require_once __DIR__ . '/../Models/SystemSetting.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Plan.php';
require_once __DIR__ . '/../Models/UsageManager.php';
require_once __DIR__ . '/../Core/Mailer.php';
require_once __DIR__ . '/../Services/MailingListPaymentService.php';

class MailingListController {

    public function index(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $lists = MailingList::getUserLists($userId);

        // Fetch user limits and usage data using UsageManager (same as DashboardController)
        $limits = UsageManager::getUserLimits($userId);
        
        // Add current mailing lists count
        $limits['current_mailing_lists'] = count($lists);

        require_once __DIR__ . '/../Views/mailing_lists/index.php';
    }

    public function subscribers(int $listId): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $list = MailingList::getById($listId, $userId);

        if (!$list) {
            $_SESSION['error'] = "Mailing list not found.";
            header('Location: /mailinglists');
            exit;
        }

        $subscribers = MailingList::getSubscribersDetailed($listId, $userId);

        require_once __DIR__ . '/../Views/mailing_lists/subscribers.php';
    }

    public function toggleExclusion(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mailinglists');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $subscriberId = (int)($_POST['subscriber_id'] ?? 0);
        $listId = (int)($_POST['list_id'] ?? 0);

        if ($subscriberId > 0 && $listId > 0) {
            MailingList::toggleExclusion($subscriberId, $listId, $userId);
            $_SESSION['success'] = "Subscriber status updated.";
        }

        header('Location: /mailinglists/' . $listId . '/subscribers');
        exit;
    }

    public function deleteSubscriber(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mailinglists');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $subscriberId = (int)($_POST['subscriber_id'] ?? 0);
        $listId = (int)($_POST['list_id'] ?? 0);

        if ($subscriberId > 0 && $listId > 0) {
            MailingList::deleteSubscriber($subscriberId, $listId, $userId);
            $_SESSION['success'] = "Subscriber removed.";
        }

        header('Location: /mailinglists/' . $listId . '/subscribers');
        exit;
    }

    public function store(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mailinglists');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $name = trim($_POST['name'] ?? '');
        $requireConfirmation = isset($_POST['require_confirmation']) && $_POST['require_confirmation'] === '1';

        $isPaid = isset($_POST['is_paid']) && $_POST['is_paid'] === '1';
        $paymentGateway = $_POST['payment_gateway'] ?? 'stripe';
        $paymentType = $_POST['payment_type'] ?? 'one_time';
        $billingPeriod = $_POST['billing_period'] ?? 'monthly';
        $price = (float)($_POST['price'] ?? 0.00);
        $currency = trim($_POST['currency'] ?? 'EUR');

        $creatorStripeId = trim($_POST['creator_stripe_account_id'] ?? '');
        $creatorPaypalEmail = trim($_POST['creator_paypal_email'] ?? '');

        if (empty($name)) {
            $_SESSION['error'] = "List name cannot be empty.";
            header('Location: /mailinglists');
            exit;
        }

        if ($isPaid) {
            if ($price <= 0) {
                $_SESSION['error'] = "Price must be greater than zero for paid mailing lists.";
                header('Location: /mailinglists');
                exit;
            }

            if ($paymentGateway === 'stripe') {
                if (empty($creatorStripeId) || !preg_match('/^acct_[a-zA-Z0-9]+$/', $creatorStripeId)) {
                    $_SESSION['error'] = "Invalid Stripe Connected Account ID. It must start with 'acct_'.";
                    header('Location: /mailinglists');
                    exit;
                }
            }

            if ($paymentGateway === 'paypal' && (empty($creatorPaypalEmail) || !filter_var($creatorPaypalEmail, FILTER_VALIDATE_EMAIL))) {
                $_SESSION['error'] = "A valid PayPal Email address is required for paid mailing lists using PayPal.";
                header('Location: /mailinglists');
                exit;
            }

            $db = Database::getInstance();
            if ($paymentGateway === 'stripe') {
                $stmt = $db->prepare("UPDATE users SET creator_stripe_account_id = ? WHERE id = ?");
                $stmt->execute([$creatorStripeId, $userId]);
            } elseif ($paymentGateway === 'paypal') {
                $stmt = $db->prepare("UPDATE users SET creator_paypal_email = ? WHERE id = ?");
                $stmt->execute([$creatorPaypalEmail, $userId]);
            }
        }

        $limits = UsageManager::getUserLimits($userId);
        $maxLists = (int)($limits['max_mailing_lists'] ?? 0);
        $currentListsCount = count(MailingList::getUserLists($userId));

        if ($maxLists > 0 && $currentListsCount >= $maxLists) {
            $_SESSION['error'] = "You have reached the maximum allowed mailing lists (" . $maxLists . ") for your plan.";
            header('Location: /mailinglists');
            exit;
        }

        MailingList::create($userId, $name, $requireConfirmation, $isPaid, $paymentGateway, $paymentType, $billingPeriod, $price, $currency);
        $_SESSION['success'] = "Mailing list created successfully!";

        header('Location: /mailinglists');
        exit;
    }

    public function update(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mailinglists');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $listId = (int)($_POST['list_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $requireConfirmation = isset($_POST['require_confirmation']) && $_POST['require_confirmation'] === '1';

        $isPaid = isset($_POST['is_paid']) && $_POST['is_paid'] === '1';
        $paymentGateway = $_POST['payment_gateway'] ?? 'stripe';
        $paymentType = $_POST['payment_type'] ?? 'one_time';
        $billingPeriod = $_POST['billing_period'] ?? 'monthly';
        $price = (float)($_POST['price'] ?? 0.00);
        $currency = trim($_POST['currency'] ?? 'EUR');

        $creatorStripeId = trim($_POST['creator_stripe_account_id'] ?? '');
        $creatorPaypalEmail = trim($_POST['creator_paypal_email'] ?? '');

        if ($listId <= 0 || empty($name)) {
            $_SESSION['error'] = "Invalid mailing list data or list name is empty.";
            header('Location: /mailinglists');
            exit;
        }

        $existingList = MailingList::getById($listId, $userId);
        if (!$existingList) {
            $_SESSION['error'] = "Mailing list not found or unauthorized.";
            header('Location: /mailinglists');
            exit;
        }

        if ($isPaid) {
            if ($price <= 0) {
                $_SESSION['error'] = "Price must be greater than zero for paid mailing lists.";
                header('Location: /mailinglists');
                exit;
            }

            if ($paymentGateway === 'stripe') {
                if (empty($creatorStripeId) || !preg_match('/^acct_[a-zA-Z0-9]+$/', $creatorStripeId)) {
                    $_SESSION['error'] = "Invalid Stripe Connected Account ID. It must start with 'acct_'.";
                    header('Location: /mailinglists');
                    exit;
                }
            }

            if ($paymentGateway === 'paypal' && (empty($creatorPaypalEmail) || !filter_var($creatorPaypalEmail, FILTER_VALIDATE_EMAIL))) {
                $_SESSION['error'] = "A valid PayPal Email address is required for paid mailing lists using PayPal.";
                header('Location: /mailinglists');
                exit;
            }

            $db = Database::getInstance();
            if ($paymentGateway === 'stripe') {
                $stmt = $db->prepare("UPDATE users SET creator_stripe_account_id = ? WHERE id = ?");
                $stmt->execute([$creatorStripeId, $userId]);
            } elseif ($paymentGateway === 'paypal') {
                $stmt = $db->prepare("UPDATE users SET creator_paypal_email = ? WHERE id = ?");
                $stmt->execute([$creatorPaypalEmail, $userId]);
            }
        }

        MailingList::update($listId, $userId, $name, $requireConfirmation, $isPaid, $paymentGateway, $paymentType, $billingPeriod, $price, $currency);
        $_SESSION['success'] = "Mailing list updated successfully!";

        header('Location: /mailinglists');
        exit;
    }

    public function delete(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mailinglists');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $listId = (int)($_POST['list_id'] ?? 0);

        if ($listId > 0) {
            MailingList::delete($listId, $userId);
            $_SESSION['success'] = "Mailing list deleted.";
        }

        header('Location: /mailinglists');
        exit;
    }

    public function publicSubscribe(string $hash): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM mailing_lists WHERE public_hash = ?");
        $stmt->execute([$hash]);
        $list = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$list) {
            http_response_code(404);
            die("Mailing list not found.");
        }

        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = \SystemSetting::getAll();
            $turnstileEnabled = ($settings['enable_turnstile'] ?? '0') === '1';
            $turnstileSecretKey = $settings['turnstile_secret_key'] ?? '';

            if ($turnstileEnabled && !empty($turnstileSecretKey)) {
                $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
                $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
                $verifyData = http_build_query([
                    'secret'   => $turnstileSecretKey,
                    'response' => $turnstileResponse,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $verifyUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);

                $responseData = json_decode($result, true);

                if (!($responseData['success'] ?? false)) {
                    $error = "CAPTCHA verification failed. Please try again.";
                }
            }

            if (empty($error)) {
                $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

                if ($email) {
                    $ownerId = (int)$list['user_id'];
                    $userPlan = Plan::getByUserId($ownerId);
                    $maxSubscribers = (int)($userPlan['max_subscribers_per_list'] ?? 0);

                    $countStmt = $db->prepare("SELECT COUNT(*) FROM mailing_list_subscribers WHERE list_id = ?");
                    $countStmt->execute([$list['id']]);
                    $currentSubscribers = (int)$countStmt->fetchColumn();

                    if ($maxSubscribers > 0 && $currentSubscribers >= $maxSubscribers) {
                        $error = "This mailing list has reached its maximum subscriber limit.";
                    } else {
                        if (!empty($list['is_paid'])) {
                            $listOwner = User::findById($ownerId);
                            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                            $baseUrl = $scheme . "://" . $_SERVER['HTTP_HOST'];

                            try {
                                if ($list['payment_gateway'] === 'stripe') {
                                    $checkoutUrl = MailingListPaymentService::createStripeSession($list, $listOwner, $email, $baseUrl);
                                    header('Location: ' . $checkoutUrl);
                                    exit;
                                } elseif ($list['payment_gateway'] === 'paypal') {
                                    $checkoutUrl = MailingListPaymentService::createPayPalOrder($list, $listOwner, $email, $baseUrl);
                                    header('Location: ' . $checkoutUrl);
                                    exit;
                                }
                            } catch (\Exception $e) {
                                $error = "Payment Error: " . $e->getMessage();
                            }
                        } else {
                            $requireConfirmation = (bool)($list['require_confirmation'] ?? false);
                            $result = MailingList::addSubscriber((int)$list['id'], $email, $requireConfirmation);

                            if (is_string($result)) {
                                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                                $confirmUrl = $scheme . "://" . $_SERVER['HTTP_HOST'] . "/subscribe/confirm/" . $result;
                                $subject = "Confirm your subscription to " . $list['name'];
                                
                                $htmlBody = "
                                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                                        <h2>Confirm your subscription</h2>
                                        <p>Hello,</p>
                                        <p>Thank you for subscribing to <strong>" . htmlspecialchars($list['name']) . "</strong>.</p>
                                        <p>Please click the button below to confirm your subscription:</p>
                                        <p style='margin: 25px 0;'>
                                            <a href='" . $confirmUrl . "' style='background-color: #0d6efd; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Confirm Subscription</a>
                                        </p>
                                    </div>
                                ";
                                Mailer::send($email, $subject, $htmlBody);
                                $message = "A confirmation link has been sent to " . htmlspecialchars($email) . ". Please check your inbox.";
                            } else {
                                $message = "Thank you! You have been successfully subscribed to " . htmlspecialchars($list['name']);
                            }
                        }
                    }
                } else {
                    $error = "Please enter a valid email address.";
                }
            }
        }

        require_once __DIR__ . '/../Views/mailing_lists/subscribe.php';
    }

    public function paymentSuccess(string $hash): void {
        $message = "Payment completed successfully! You are now subscribed to the mailing list.";
        require_once __DIR__ . '/../Views/mailing_lists/confirm.php';
    }

    public function stripeWebhook(): void {
        $payload = file_get_contents('php://input');
        $event = json_decode($payload, true);

        if (!$event) {
            http_response_code(400);
            exit;
        }

        if ($event['type'] === 'checkout.session.completed') {
            $session = $event['data']['object'];
            $ref = explode('|', $session['client_reference_id'] ?? '');
            
            if (count($ref) === 2) {
                $listId = (int)$ref[0];
                $email = $ref[1];
                $subId = $session['subscription'] ?? $session['payment_intent'] ?? 'PAYMENT_OK';

                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
                MailingList::addSubscriber($listId, $email, false, 'paid', $subId, $expiresAt);
            }
        } elseif ($event['type'] === 'customer.subscription.deleted') {
            $subscription = $event['data']['object'];
            MailingList::setSubscriberPaymentStatus($subscription['id'], 'expired');
        }

        http_response_code(200);
    }

    public function confirm(string $token): void {
        $confirmed = MailingList::confirmSubscriber($token);
        $message = $confirmed ? "Your email address has been successfully confirmed!" : "Invalid or expired confirmation token.";
        
        require_once __DIR__ . '/../Views/mailing_lists/confirm.php';
    }
}
