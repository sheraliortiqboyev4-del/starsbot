<?php
/**
 * Bu skriptni FAQAT BIR MARTA, brauzerda ochib ishga tushiring:
 * https://yourdomain.myxvest.ru/set_webhook.php
 * Ishlatib bo'lgach, xavfsizlik uchun serverdan o'chirib tashlang.
 */
require_once __DIR__ . '/config.php';

$webhookUrl = SITE_URL . '/webhook.php';

$url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/setWebhook?url=' . urlencode($webhookUrl);
$response = file_get_contents($url);

header('Content-Type: application/json');
echo $response;
