<?php
/**
 * ASOSIY SOZLAMALAR FAYLI
 * Barcha maxfiy kalitlarni shu yerga kiriting.
 * Bu faylni hech qachon public papkaga yoki GitHub'ga ochiq qo'ymang!
 */

// ==================== TELEGRAM BOT ====================
define('BOT_TOKEN', '8928348194:AAGJ2leUwXNUZD1Z2ugMMQyZpJOP7hctqYQ'); // @BotFather dan oling
define('BOT_USERNAME', 'AvtixMarketBot');           // @ belgisiz
define('ADMIN_CHAT_IDS', [8290819594]);                 // Admin(lar)ning Telegram ID raqami(lari)

// ==================== ADMIN PANEL KIRISH ====================
// Admin panel (/admin) ga kirish uchun login va parol — shu yerda o'zgartiring.
// Boshqa hech qanday sozlash (URL ochish, fayl o'chirish) shart emas.
define('ADMIN_PANEL_USERNAME', 'x7');
define('ADMIN_PANEL_PASSWORD', 'x7007');

// ==================== DATABASE ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'avtobot_db');
define('DB_USER', 'avtobot_user');
define('DB_PASS', 'avtobot01');


// ==================== CLICK.UZ ====================
define('CLICK_MERCHANT_ID', 'XXXX');
define('CLICK_SERVICE_ID', 'XXXX');
define('CLICK_SECRET_KEY', 'XXXX');
define('CLICK_MERCHANT_USER_ID', 'XXXX');

// ==================== PAYME.UZ ====================
define('PAYME_MERCHANT_ID', 'XXXX');
define('PAYME_KEY', 'XXXX'); // Test yoki Production kaliti

// ==================== RESELLER / HAMKOR API ====================
// Stars va Premium'ni haqiqatda yetkazib beruvchi tashqi API.
// Hamkor tanlab, key olganingizdan so'ng shu yerga kiriting.
define('RESELLER_API_BASE', 'https://saleseen.uz/api/v2'); // TODO: hamkor bergan bazaviy URL
define('RESELLER_API_KEY', '4f15cfe78492bca651f662b895fdb3bf');                // TODO: hamkor bergan API key

// ==================== NARXLAR ====================
// DIQQAT: Narxlar endi bazadagi `settings` jadvalida saqlanadi va
// ADMIN PANEL orqali (Sozlamalar bo'limi) istalgan vaqt o'zgartiriladi.
// Bu yerda hech narsa o'zgartirish shart emas — pastdagi include_once
// get_setting() funksiyasini beradi, u includes/settings.php'da.

// ==================== UMUMIY ====================
define('SITE_URL', 'https://bot.sheralidev.uz'); // Botning joylashgan domeni (https shart)
date_default_timezone_set('Asia/Tashkent');
error_reporting(E_ALL);
ini_set('display_errors', 0); // productionda 0, debugda 1 qiling
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');