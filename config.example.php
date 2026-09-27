<?php
/**
 * Example configuration file.
 * Copy this to config.php and fill in your real values before running the bot.
 */

// ==================== TELEGRAM BOT ====================
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('BOT_USERNAME', 'YourBotUsername');
define('ADMIN_CHAT_IDS', [123456789]);

// ==================== ADMIN PANEL KIRISH ====================
define('ADMIN_PANEL_USERNAME', 'admin');
define('ADMIN_PANEL_PASSWORD', 'change_this_password');

// ==================== DATABASE ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// ==================== CLICK.UZ ====================
define('CLICK_MERCHANT_ID', 'XXXX');
define('CLICK_SERVICE_ID', 'XXXX');
define('CLICK_SECRET_KEY', 'XXXX');
define('CLICK_MERCHANT_USER_ID', 'XXXX');

// ==================== PAYME.UZ ====================
define('PAYME_MERCHANT_ID', 'XXXX');
define('PAYME_KEY', 'XXXX');

// ==================== RESELLER / HAMKOR API ====================
define('RESELLER_API_BASE', 'https://example.com/api/v2');
define('RESELLER_API_KEY', 'your_api_key');

// ==================== UMUMIY ====================
define('SITE_URL', 'https://your-domain.com');
date_default_timezone_set('Asia/Tashkent');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
