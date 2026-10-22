# My Social Manager (MSM)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-777BB4.svg?logo=php)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-4479A1.svg?logo=mysql)](https://mysql.com)
[![Architecture](https://img.shields.io/badge/Architecture-SaaS%20Multi--User-orange.svg)](#features)

**My Social Manager** is an open-source, full-featured Social Media Management SaaS platform. It enables individual creators, agencies, and businesses to connect social channels, schedule posts, run AI-assisted campaigns, automate RSS feeds, mirror cross-platform channels, monetize paid mailing lists, and manage subscriptions with comprehensive administrator tools.

---

## Table of Contents
- [Features](#features)
  - [User & Creator Tools](#user--creator-tools)
  - [AI Engine & Automation](#ai-engine--automation)
  - [Monetization & Mailing Lists](#monetization--mailing-lists)
  - [Authentication & Security](#authentication--security)
  - [Administrator Dashboard](#administrator-dashboard)
- [System Requirements](#system-requirements)
- [Installation Guide](#installation-guide)
  - [1. Clone Repository](#1-clone-repository)
  - [2. File & Directory Permissions](#2-file--directory-permissions-critical)
  - [3. Web Server Configuration](#3-web-server-configuration)
  - [4. Run Guided Web Installer](#4-run-guided-web-installer)
- [Background Workers & Cron Setup](#background-workers--cron-setup)
- [Automated System Updates](#automated-system-updates)
- [License](#license)

---

## Features

### User & Creator Tools
* **Interactive Dashboard:** Real-time analytics, post status tracking, current quota usage, and subscription tier overview.
* **Social Accounts:** Connect and authenticate third-party networks (Facebook, Instagram, YouTube, X/Twitter, LinkedIn, Pinterest, Telegram, etc.) via extensible plugin drivers.
* **Groups:** Group social channels together (e.g. *"Tech Brand"*, *"Personal"*) to publish across multiple targets with a single click.
* **Post Composer:** Rich manual post editor with multi-asset uploads, live previews, custom per-network tailoring, and AI generation assistance.
* **Visual Calendar:** Calendar view (past, present, scheduled) with drag-and-drop rescheduling and status filters.
* **Channel Automations:** Automatically monitor a source channel (e.g. YouTube channel) and republish new uploads across selected destination social channels with configurable time intervals and random reposting filters.
* **RSS Feeds Parser:** Monitor external RSS/Atom feeds and automatically format and post new articles to your chosen social profiles.
* **File Manager:** Built-in standalone file storage manager with upload validation, image/video support, quota monitoring, and media library organization.
* **Theme Customization:** Instant Light / Dark mode toggle.

### AI Engine & Automation
* **Multi-Provider AI Studio:** Connect to **OpenAI** (ChatGPT), local self-hosted models via **Ollama**, or **Google Gemini**.
* **AI Campaign Studio:** Define prompt ideas, schedules, and targets; let the background worker autonomously generate, schedule, and publish posts.
* **Integrated Auto-Translator:** Automated multilingual translation powered by **LibreTranslate** APIs.

### Monetization & Mailing Lists
* **Mailing Lists:** Create public and private subscriber lists with double opt-in support.
* **Paid Newsletter Subscriptions:** Charge subscribers for access (One-time or recurring subscriptions) using integrated creator **Stripe** or **PayPal** gateways.
* **Subscriber Management:** Manual or bulk subscriber imports, blacklist/exclusion management, and payment verification.

### Authentication & Security
* **Single Sign-On (SSO):** Out-of-the-box OAuth 2.0 social login support for **MYETV**, **Google**, **Facebook**, and **Microsoft**.
* **Two-Factor Authentication (2FA):** Time-based One-Time Password (TOTP) authenticator app support.
* **Anti-Bot Protection:** Native **Cloudflare Turnstile** integration on registration and login endpoints.
* **Email encryption:** encryption at rest in mysql for subscribers email

### Administrator Dashboard
* **User Management:** View all registered accounts, change user roles, assign subscription tiers, or ban/activate accounts.
* **Billing & Plan Builder:** Define multi-tiered subscription plans (Free, Pro, Enterprise) with granular feature gating:
  * Maximum connected social accounts
  * Maximum posts per month/week/day
  * AI credit limits
  * Maximum storage size and single-file upload size
  * Allowed RSS feeds and Channel Automations
  * Maximum mailing lists and subscribers
* **Plugin Configuration:** Enable, disable, and configure global API keys and OAuth secrets for all third-party social plugins.
* **Payment Gateways & Commissions:** Set master Stripe and PayPal credentials and configure platform commissions on creator paid lists.
* **Automated Batch Translator:** One-click batch translator for all UI language files (`/lang/*.json`).
* **Cron & Worker Monitor:** Real-time visibility into pending queues, task execution intervals, failed jobs, and one-click post retries.
* **SMTP & Mail Configuration:** Setup local PHP mail or custom authenticated SMTP with SSL/TLS and test mail capabilities.
* **Custom Scripts & Monetization:** Inject custom header/footer tracking scripts (Google Analytics, Pixel) and ad banner codes (AdSense) targeted to free-tier users.
* **One-Click GitHub Updater:** Check and apply updates directly from GitHub releases with automated database migrations.

---

## System Requirements

| Requirement | Supported Versions |
| :--- | :--- |
| **PHP** | 8.2 or 8.3+ |
| **Database** | MySQL 8.0+ or MariaDB 10.5+ |
| **Web Server** | Apache (with `mod_rewrite`) or Nginx |
| **PHP Extensions** | `pdo_mysql`, `curl`, `openssl`, `zip`, `mbstring`, `fileinfo` |

---

## Installation Guide

### 1. Clone Repository
Clone the repository to your server's web directory:
```bash
git clone https://github.com/OskarCosimo/My-Social-Manager.git /var/www/socialmanager
cd /var/www/socialmanager
```

### 2. File & Directory Permissions (Critical)
The webserver user (typically `www-data` on Ubuntu/Debian, or `nginx`/`apache` on CentOS/RHEL) **must have recursive group ownership and write permissions** across the entire application root. This is required for:
1. Writing `app/Core/Config.php` during setup.
2. Generating media uploads in the file manager.
3. Performing **automatic one-click updates via GitHub releases** without terminal access.

Execute the following commands in your terminal:
```bash
# 1. Assign the www-data group recursively
sudo chgrp -R www-data /var/www/socialmanager/

# 2. Grant read/write/execute to directories (775)
sudo find /var/www/socialmanager/ -type d -exec chmod 775 {} +

# 3. Grant read/write to files (664)
sudo find /var/www/socialmanager/ -type f -exec chmod 664 {} +

# 4. Set the SGID bit on all directories so new files inherit www-data
sudo find /var/www/socialmanager/ -type d -exec chmod g+s {} +
```

### 3. Web Server Configuration

#### Apache
Ensure `mod_rewrite` is enabled:
```bash
sudo a2enmod rewrite
```
Configure your VirtualHost pointing to the application root:
```apache
<VirtualHost *:80>
    ServerName socialmanager.yourdomain.com
    DocumentRoot /var/www/socialmanager

    <Directory /var/www/socialmanager>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/msm_error.log
    CustomLog ${APACHE_LOG_DIR}/msm_access.log combined
</VirtualHost>
```

### 4. Run Guided Web Installer
1. Navigate to your domain in a web browser: `http://socialmanager.yourdomain.com/`
2. You will be automatically redirected to the installation wizard at `/install/index.php`.
3. **Step 1:** The installer validates all PHP modules and file permissions.
4. **Step 2:** Enter your MySQL database credentials, application URL, and create the Super Administrator account.
5. The installer imports `database/database.sql`, configures `app/Core/Config.php`, and creates `install/installed.lock`.
6. Once completed, log in at `/login`.

---

## Background Workers & Cron Setup

My Social Manager uses background worker processes to publish scheduled posts, process RSS feeds, generate AI campaigns, check channel automations, and reset monthly usage quotas.

You can run workers using either **Server Crontab (Recommended)** or the built-in **Internal Pseudo-Cron**.

### Option A: Server Crontab (Recommended)
Edit the server crontab for `www-data`:
```bash
sudo crontab -u www-data -e
```
Add the following cron entries:
```crontab
# 1. Post Publisher Worker (Every minute)
* * * * * /usr/bin/php /var/www/socialmanager/cron/publisher_worker.php >> /var/www/socialmanager/cron/publisher.log 2>&1

# 2. RSS Feeds Parser (Every 15 minutes)
*/15 * * * * /usr/bin/php /var/www/socialmanager/cron/rss_worker.php >> /var/www/socialmanager/cron/rss.log 2>&1

# 3. AI Campaign Generator (Every minute)
* * * * * /usr/bin/php /var/www/socialmanager/cron/ai_campaign_worker.php >> /var/www/socialmanager/cron/ai_campaign.log 2>&1

# 4. Channel Automations Worker (Every 15 minutes)
*/15 * * * * /usr/bin/php /var/www/socialmanager/cron/channel_worker.php >> /var/www/socialmanager/cron/channel.log 2>&1

# 5. Monthly Usage & Quota Reset (Daily at midnight)
0 0 * * * /usr/bin/php /var/www/socialmanager/cron/reset_monthly_usage.php >> /var/www/socialmanager/cron/reset.log 2>&1
```

### Option B: Internal PHP Pseudo-Cron
If you do not have root access to crontab on shared hosting:
1. Go to **Admin Area** -> **Cron Jobs**.
2. Enable **Internal PHP Cron Runner**.
3. Tasks will run seamlessly in the background during page visits.

---

## Automated System Updates

My Social Manager features an automated updater connected to GitHub Releases:

1. Navigate to **Admin Area** -> **System Updates** (or `/admin/updater`).
2. The system checks the latest release tag on GitHub against `Config::APP_VERSION`.
3. If an update is available, click **Download & Install Update**.
4. The system automatically:
   - Downloads the latest release archive.
   - Preserves your existing `app/Core/Config.php` and `install/installed.lock`.
   - Overwrites system core files safely.
   - Flushes PHP OPcache.
   - Runs incremental schema migrations via `migrate.php`.

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU Affero General Public License (AGPL-3.0)** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See the [LICENSE](LICENSE) file for more details.
