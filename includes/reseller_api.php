<?php
require_once __DIR__ . '/../config.php';

/**
 * ====================================================================
 *  HAMKOR (RESELLER) API BILAN ISHLASH QATLAMI
 * ====================================================================
 * Hamkor tanlab, API hujjatini olganingizdan so'ng:
 *   1. config.php ichida RESELLER_API_BASE va RESELLER_API_KEY qiymatlarini kiriting.
 *   2. Pastdagi reseller_buy_stars() va reseller_buy_premium() ichidagi
 *      endpoint/parametr nomlarini hamkor hujjatiga moslab o'zgartiring.
 *   3. Shu ikkita funksiyadan tashqari HECH NARSANI o'zgartirish shart emas —
 *      qolgan bot avtomatik ravishda ishlay boshlaydi.
 *
 * API hali ulanmagan bo'lsa (RESELLER_API_KEY = placeholder), tizim buni
 * XATO emas, balki "hozircha qo'lda bajariladi" holati sifatida ko'radi —
 * to'lov baribir avtomatik qabul qilinadi, faqat yetkazib berish admin
 * tomonidan qo'lda tasdiqlanadi (admin panel → Buyurtmalar → Bajarildi).
 * ====================================================================
 */

class ResellerApiException extends Exception {}
class ResellerApiNotConfigured extends ResellerApiException {}

function reseller_request(string $endpoint, array $payload): array
{
    if (RESELLER_API_KEY === 'PLACEHOLDER_API_KEY') {
        throw new ResellerApiNotConfigured('Hamkor API hali ulanmagan.');
    }

    $url = rtrim(RESELLER_API_BASE, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . RESELLER_API_KEY, // TODO: hamkor talab qilgan header nomiga moslang
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new ResellerApiException('Tarmoq xatosi: ' . $err);
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400 || $data === null) {
        throw new ResellerApiException('Hamkor API xatosi (HTTP ' . $httpCode . '): ' . $response);
    }

    return $data;
}

function reseller_buy_stars(string $username, int $amount): array
{
    // TODO: haqiqiy hamkor endpoint/parametrlarga moslang. Namuna:
    // $result = reseller_request('/buyStars', ['username' => $username, 'amount' => $amount]);
    // return [
    //     'success'           => $result['success'] ?? false,
    //     'external_order_id' => $result['order_id'] ?? null,
    //     'message'           => $result['message'] ?? '',
    // ];

    throw new ResellerApiNotConfigured('reseller_buy_stars() hali sozlanmagan.');
}

function reseller_buy_premium(string $username, int $months): array
{
    // TODO: haqiqiy hamkor endpoint/parametrlarga moslang. Namuna:
    // $result = reseller_request('/buyPremium', ['username' => $username, 'months' => $months]);
    // return [
    //     'success'           => $result['success'] ?? false,
    //     'external_order_id' => $result['order_id'] ?? null,
    //     'message'           => $result['message'] ?? '',
    // ];

    throw new ResellerApiNotConfigured('reseller_buy_premium() hali sozlanmagan.');
}
