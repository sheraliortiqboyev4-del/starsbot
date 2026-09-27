<?php
/**
 * Telegram'da yuborilgan chek rasmini admin panelda ko'rsatish uchun proxy.
 * To'g'ridan-to'g'ri Telegram file URL bermaslik uchun (bot tokenni yashirish),
 * shu skript orqali oqim (stream) qilinadi.
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config.php';

$fileId = $_GET['file_id'] ?? '';
if (!$fileId) {
    http_response_code(400);
    exit('file_id kerak');
}

$res = json_decode(file_get_contents(
    'https://api.telegram.org/bot' . BOT_TOKEN . '/getFile?file_id=' . urlencode($fileId)
), true);

$filePath = $res['result']['file_path'] ?? null;
if (!$filePath) {
    http_response_code(404);
    exit('Fayl topilmadi');
}

$url = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $filePath;
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
readfile($url);
