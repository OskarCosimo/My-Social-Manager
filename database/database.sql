-- database/database.sql

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `post_targets`;
DROP TABLE IF EXISTS `group_account`;
DROP TABLE IF EXISTS `social_accounts`;
DROP TABLE IF EXISTS `rss_feeds`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `ai_campaigns`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `plugin_settings`;
DROP TABLE IF EXISTS `plans`;
DROP TABLE IF EXISTS `mailing_lists`;
DROP TABLE IF EXISTS `mailing_list_subscribers`;
DROP TABLE IF EXISTS `channel_automations`;

SET FOREIGN_KEY_CHECKS = 1;

-- Table: plans
CREATE TABLE `plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `plan_code` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
  `stripe_price_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_cycle` enum('monthly','yearly') COLLATE utf8mb4_bin DEFAULT 'monthly',
  `max_accounts` int NOT NULL DEFAULT '1',
  `max_posts` int NOT NULL DEFAULT '10',
  `post_period` enum('daily','weekly','monthly') COLLATE utf8mb4_bin DEFAULT 'monthly',
  `ai_credits_month` int NOT NULL DEFAULT '5',
  `is_default` tinyint(1) DEFAULT '0',
  `ai_max_campaign_posts` int NOT NULL DEFAULT '3',
  `ai_max_daily_posts` int NOT NULL DEFAULT '1',
  `max_storage_size` varchar(50) COLLATE utf8mb4_bin DEFAULT '1GB',
  `max_upload_size` varchar(50) COLLATE utf8mb4_bin DEFAULT '50MB',
  `max_rss_feeds` int NOT NULL DEFAULT '5',
  `max_rss_posts` int NOT NULL DEFAULT '50',
  `max_channel_automations` int NOT NULL DEFAULT '5',
  `max_channel_posts` int NOT NULL DEFAULT '50',
  `max_emails_per_month` int DEFAULT '0',
  `max_subscribers_per_list` int DEFAULT '0',
  `max_mailing_lists` int DEFAULT '0',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Default seed: Plan 1 (Default / Free plan)
INSERT INTO `plans` (`id`, `name`, `plan_code`, `stripe_price_id`, `price`, `billing_cycle`, `max_accounts`, `max_posts`, `post_period`, `ai_credits_month`, `is_default`, `ai_max_campaign_posts`, `ai_max_daily_posts`, `max_storage_size`, `max_upload_size`, `max_rss_feeds`, `max_rss_posts`, `max_channel_automations`, `max_channel_posts`, `max_emails_per_month`, `max_subscribers_per_list`, `max_mailing_lists`, `is_hidden`)
VALUES (1, 'Free Plan', 'free', NULL, 0.00, 'monthly', 3, 50, 'monthly', 10, 1, 3, 2, '500MB', '25MB', 3, 30, 2, 20, 100, 50, 1, 0);

-- Table: users
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `role` enum('user','admin') COLLATE utf8mb4_bin DEFAULT 'user',
  `status` enum('active','banned') COLLATE utf8mb4_bin DEFAULT 'active',
  `is_premium` tinyint(1) DEFAULT '0',
  `premium_expires_at` datetime DEFAULT NULL,
  `accountsocialmanager` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `language` varchar(10) COLLATE utf8mb4_bin DEFAULT 'en',
  `timezone` varchar(50) COLLATE utf8mb4_bin DEFAULT 'UTC',
  `plan_id` int DEFAULT '1',
  `stripe_customer_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `stripe_subscription_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `posts_used` int DEFAULT '0',
  `rss_posts_used` int NOT NULL DEFAULT '0',
  `emails_sent_month` int NOT NULL DEFAULT '0',
  `ai_credits_used` int DEFAULT '0',
  `current_period_end` datetime DEFAULT NULL,
  `remember_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `two_factor_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `social_provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `social_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `creator_stripe_account_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `creator_paypal_email` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `creator_paypal_client_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `creator_paypal_secret` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `channel_posts_used` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: channel_automations
CREATE TABLE `channel_automations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `source_type` enum('youtube_url','connected_account') NOT NULL DEFAULT 'connected_account',
  `source_account_id` int DEFAULT NULL,
  `source_identifier` varchar(500) DEFAULT NULL,
  `source_title` varchar(255) NOT NULL,
  `target_accounts` text NOT NULL,
  `post_interval_hours` int NOT NULL DEFAULT '1',
  `only_new_items` tinyint(1) NOT NULL DEFAULT '0',
  `random_repost_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `random_repost_limit` int NOT NULL DEFAULT '10',
  `last_checked` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_checked` (`last_checked`),
  CONSTRAINT `channel_automations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: mailing_lists
CREATE TABLE `mailing_lists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `public_hash` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `require_confirmation` tinyint(1) NOT NULL DEFAULT '0',
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `payment_gateway` enum('stripe','paypal') COLLATE utf8mb4_bin DEFAULT 'stripe',
  `payment_type` enum('one_time','subscription') COLLATE utf8mb4_bin DEFAULT 'one_time',
  `billing_period` enum('monthly','yearly') COLLATE utf8mb4_bin DEFAULT 'monthly',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_bin NOT NULL DEFAULT 'EUR',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_hash` (`public_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `mailing_lists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: mailing_list_subscribers
CREATE TABLE `mailing_list_subscribers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `list_id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `subscribed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT '1',
  `confirmation_token` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL,
  `is_excluded` tinyint(1) NOT NULL DEFAULT '0',
  `payment_status` enum('free','pending','paid','expired','cancelled') COLLATE utf8mb4_bin NOT NULL DEFAULT 'free',
  `gateway_subscription_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subscription` (`list_id`,`email`),
  CONSTRAINT `mailing_list_subscribers_ibfk_1` FOREIGN KEY (`list_id`) REFERENCES `mailing_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: plugin_settings
CREATE TABLE `plugin_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `setting_value` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plugin_setting` (`plugin_id`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: system_settings
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `setting_value` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: ai_campaigns
CREATE TABLE `ai_campaigns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `prompt` text COLLATE utf8mb4_bin NOT NULL,
  `selected_accounts` json NOT NULL,
  `schedule_settings` json NOT NULL,
  `status` enum('active','completed','paused','failed') COLLATE utf8mb4_bin DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ai_campaigns_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: groups
CREATE TABLE `groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: posts
CREATE TABLE `posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `campaign_id` int unsigned DEFAULT NULL,
  `social_account_id` int DEFAULT NULL,
  `content` text COLLATE utf8mb4_bin NOT NULL,
  `media_url` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `media_urls` json DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `status` enum('draft','scheduled','published','failed') COLLATE utf8mb4_bin DEFAULT 'draft',
  `scheduled_for` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `error_log` text COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `source_link` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `is_rss` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_posts_campaign` (`campaign_id`),
  CONSTRAINT `fk_posts_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: rss_feeds
CREATE TABLE `rss_feeds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `feed_url` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `target_accounts` json NOT NULL,
  `post_interval_hours` int NOT NULL DEFAULT '1',
  `only_new_items` tinyint(1) NOT NULL DEFAULT '0',
  `random_repost_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `random_repost_limit` int NOT NULL DEFAULT '10',
  `last_checked` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rss_feeds_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: social_accounts
CREATE TABLE `social_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `plugin_id` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `platform_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `access_token` text COLLATE utf8mb4_bin NOT NULL,
  `refresh_token` text COLLATE utf8mb4_bin,
  `token_expires_at` datetime DEFAULT NULL,
  `platform_user_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `social_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: group_account
CREATE TABLE `group_account` (
  `group_id` int NOT NULL,
  `account_id` int NOT NULL,
  PRIMARY KEY (`group_id`,`account_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `group_account_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_account_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Table: post_targets
CREATE TABLE `post_targets` (
  `post_id` int NOT NULL,
  `social_account_id` int NOT NULL,
  `publish_status` enum('pending','success','error') COLLATE utf8mb4_bin DEFAULT 'pending',
  `api_response` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`post_id`,`social_account_id`),
  KEY `social_account_id` (`social_account_id`),
  CONSTRAINT `post_targets_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_targets_ibfk_2` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;