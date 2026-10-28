<?php
// public/index.php

// Installer lock verification guard
$lockFile = dirname(__DIR__) . '/install/installed.lock';
if (!file_exists($lockFile)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    if (strpos($requestUri, '/install') === false) {
        header('Location: /install/index.php');
        exit;
    }
}

// Start global session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load core system components
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Version.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Router.php';
// languages and others helpers
require_once __DIR__ . '/../app/Core/Helpers.php';
// Internal Pseudo-Cron execution (runs seamlessly if enabled in settings)
require_once __DIR__ . '/../app/Core/CronRunner.php';
CronRunner::runIfDue();

$router = new Router();

// Define Auth Routes
$router->get('/login', ['AuthController', 'showLogin']);
$router->post('/login', ['AuthController', 'login']);
$router->get('/register', ['AuthController', 'showRegister']);
$router->post('/register', ['AuthController', 'register']);
$router->get('/logout', ['AuthController', 'logout']);

// Password Reset Routes
$router->get('/reset-password', ['AuthController', 'showResetPassword']);
$router->post('/reset-password', ['AuthController', 'resetPassword']);

// --- Social Login Routes ---
$router->get('/auth/social', ['AuthController', 'redirectSocial']);
$router->get('/auth/social/callback', ['AuthController', 'handleSocialCallback']);
// --- Social OAuth Routes (Clean Paths) ---
$router->post('/auth/social', ['AuthController', 'redirectSocial']);
$router->get('/auth/callback/google', ['AuthController', 'handleGoogleCallback']);
$router->get('/auth/callback/facebook', ['AuthController', 'handleFacebookCallback']);
$router->get('/auth/callback/microsoft', ['AuthController', 'handleMicrosoftCallback']);
$router->get('/auth/callback/myetv', ['AuthController', 'handleMyetvCallback']);

// --- 2FA Routes ---
$router->get('/auth/2fa', ['AuthController', 'show2fa']);
$router->post('/auth/2fa', ['AuthController', 'verify2fa']);
$router->post('/auth/2fa/toggle', ['AuthController', 'toggle2FA']);

// Bridge approval route for external database integration
$router->get('/auth/bridge-approval', ['AuthController', 'showBridgeApproval']);
$router->post('/auth/bridge-approval', ['AuthController', 'approveBridge']);

// Protected Dashboard Route (Placeholder)
$router->get('/', ['HomeController', 'index']);
$router->get('/dashboard', ['DashboardController', 'index']);

// Social Accounts Management
$router->get('/accounts', ['AccountController', 'index']);
$router->post('/accounts/disconnect', ['AccountController', 'disconnect']);
$router->post('/accounts/verify-token', ['AccountController', 'verifyToken']);

// --- AI Studio Routes ---
$router->get('/ai-studio', ['AiStudioController', 'index']);
$router->post('/ai-studio/store', ['AiStudioController', 'store']);
$router->post('/ai-studio/update', ['AiStudioController', 'update']);
$router->post('/ai-studio/delete', ['AiStudioController', 'delete']);
$router->get('/ai-studio/campaign-posts/{id}', ['AiStudioController', 'getCampaignPosts']);
$router->post('/ai-studio/posts/delete', ['AiStudioController', 'deleteCampaignPosts']);
$router->post('/ai-studio/posts/delete-all', ['AiStudioController', 'deleteAllCampaignPosts']);

// --- AI Credits Top-Up Routes ---
$router->post('/billing/buy-credits/stripe', ['BillingController', 'buyCreditsStripe']);
$router->get('/billing/stripe-credits-success', ['BillingController', 'stripeCreditsSuccess']);
$router->post('/billing/buy-credits/paypal', ['BillingController', 'buyCreditsPayPal']);
$router->get('/billing/paypal-credits-return', ['BillingController', 'paypalCreditsReturn']);

// Admin AI Credit Packs Settings Route
$router->post('/admin/billing/ai-packs', ['AdminBillingController', 'saveAiCreditPacks']);

// Plugin Auth Routes
$router->get('/plugin/auth', ['PluginAuthController', 'redirect']);
$router->get('/plugin/callback', ['PluginAuthController', 'callback']);
$router->post('/plugin/callback', ['PluginAuthController', 'callback']);
// Manual Setup Route for non-OAuth Plugins
$router->get('/accounts/manual-setup', ['AccountController', 'manualSetup']);

// --- Admin Area Routes ---
$router->get('/admin', ['AdminController', 'index']);
$router->post('/admin/user/update', ['AdminController', 'updateUser']);
$router->post('/admin/user/send-reset', ['AdminController', 'sendPasswordReset']);
$router->post('/admin/user/toggle', ['AdminController', 'toggleUserStatus']);
$router->post('/admin/plugin/save', ['AdminController', 'savePluginSettings']);
$router->post('/admin/settings/scripts', ['AdminController', 'saveScriptsSettings']);
$router->post('/admin/settings/auth', ['AdminController', 'saveAuthSettings']);
$router->post('/admin/user/plan', ['AdminController', 'changeUserPlan']);
$router->post('/admin/settings/payments', ['AdminController', 'savePaymentSettings']);
$router->post('/admin/settings/branding', ['AdminController', 'saveBrandingSettings']);
$router->post('/admin/settings/ai', ['AdminController', 'saveAiSettings']);
$router->post('/admin/settings/languages', ['AdminController', 'saveEnabledLanguages']);

// Admin Billing & Subscription Plans Routes
$router->get('/admin/billing', ['AdminBillingController', 'index']);
$router->post('/admin/billing/plans', ['AdminBillingController', 'updatePlans']);
$router->post('/admin/billing/plans/create', ['AdminBillingController', 'createPlan']);
$router->post('/admin/billing/plans/update', ['AdminBillingController', 'updatePlan']);
$router->post('/admin/plans/delete', ['AdminBillingController', 'deletePlan']);
$router->post('/admin/billing/stripe', ['AdminBillingController', 'saveStripeSettings']);

// Admin Mail Settings Route
$router->post('/admin/settings/mail', ['AdminController', 'saveMailSettings']);

// Admin Cron Routes
$router->post('/admin/cron/settings', ['AdminController', 'saveCronSettings']);
$router->post('/admin/cron/run', ['AdminController', 'runCronManual']);
$router->post('/admin/cron/retry-post', ['AdminController', 'retryFailedPost']);

// Admin System Updater Routes
$router->get('/admin/updater', ['UpdaterController', 'index']);
$router->post('/admin/updater/apply', ['UpdaterController', 'apply']);

// User Billing Routes (Checkout & Plan Changes)
$router->post('/billing/checkout', ['BillingController', 'checkout']);
$router->post('/billing/change-plan', ['BillingController', 'changePlan']);
$router->post('/billing/cancel-downgrade', ['BillingController', 'cancelDowngrade']);

// Email Verification Route
$router->get('/verify', ['AuthController', 'verifyEmail']);

// Stripe Webhook Endpoint (Must be POST and publicly accessible)
// Important: If you have a global CSRF middleware, you MUST exclude this route from CSRF checks!
$router->post('/webhook/stripe', ['WebhookController', 'handleStripe']);

// Compose Area Routes
$router->get('/compose', ['ComposeController', 'index']);
$router->post('/compose/publish', ['ComposeController', 'publish']);

// --- Mailing List Routes ---
$router->get('/mailinglists', ['MailingListController', 'index']);
$router->post('/mailinglists/store', ['MailingListController', 'store']);
$router->post('/mailinglist/create', ['MailingListController', 'create']);
$router->post('/mailinglist/update', ['MailingListController', 'update']);
$router->post('/mailinglists/update', ['MailingListController', 'update']);
$router->post('/mailinglist/delete', ['MailingListController', 'delete']);
$router->get('/mailinglists/{id}/subscribers', ['MailingListController', 'subscribers']);
$router->post('/mailinglists/subscribers/toggle-exclusion', ['MailingListController', 'toggleExclusion']);
$router->post('/mailinglists/subscribers/delete', ['MailingListController', 'deleteSubscriber']);
$router->get('/subscribe/confirm/{token}', ['MailingListController', 'confirm']);

// Public Mailing List Subscription Routes
$router->get('/subscribe/{hash}', ['MailingListController', 'publicSubscribe']);
$router->post('/subscribe/{hash}', ['MailingListController', 'publicSubscribe']);

// --- Payment Webhooks and Redirects for Mailing Lists ---
$router->get('/subscribe/success/{hash}', ['MailingListController', 'paymentSuccess']);
$router->post('/webhook/mailinglist/stripe', ['MailingListController', 'stripeWebhook']);
$router->post('/webhook/mailinglist/paypal', ['MailingListController', 'paypalWebhook']);

// --- Groups Routes ---
$router->get('/groups', ['GroupController', 'index']);
$router->post('/groups/save', ['GroupController', 'save']);
$router->post('/groups/delete', ['GroupController', 'delete']);

// --- Calendar & Scheduling Routes ---
$router->get('/calendar', ['CalendarController', 'index']);
$router->post('/calendar/delete', ['CalendarController', 'delete']);
$router->post('/calendar/update', ['CalendarController', 'update']);

// RSS Feeds Routes
$router->get('/rss', ['RssController', 'index']);
$router->post('/rss/add', ['RssController', 'add']);
$router->post('/rss/delete', ['RssController', 'delete']);
$router->post('/rss/update', ['RssController', 'update']);

// Channel & Social Automations Routes
$router->get('/channel-automations', ['ChannelAutomationController', 'index']);
$router->post('/channel-automations/add', ['ChannelAutomationController', 'add']);
$router->post('/channel-automations/update', ['ChannelAutomationController', 'update']);
$router->post('/channel-automations/delete', ['ChannelAutomationController', 'delete']);

// Settings & System Routes
$router->get('/settings', ['SettingsController', 'index']);
$router->post('/settings/save', ['SettingsController', 'save']);

// File Manager Route
$router->get('/filemanager', ['FileManagerController', 'index']);
$router->get('/filemanager/standalone', ['FileManagerController', 'standalone']);

// Newsletter Routes
$router->get('/newsletter', ['NewsletterController', 'index']);
$router->post('/newsletter/send', ['NewsletterController', 'send']);

// Post Management Routes
$router->post('/post/save', ['PostController', 'save']);

// AI Routes
$router->post('/api/admin/translate', ['AdminController', 'autoTranslate']);
$router->post('/api/ai/generate', ['AiController', 'generate']);

// Dispatch the current request
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
