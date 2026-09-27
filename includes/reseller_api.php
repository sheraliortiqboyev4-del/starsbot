<?php
require_once __DIR__ . '/../config.php';

/** Hamkorning Stars va Premium API integratsiyasi. */

class ResellerApiException extends Exception {}
class ResellerApiNotConfigured extends ResellerApiException {}
class ResellerApiUncertainException extends ResellerApiException
{
    public $externalOrderId;

    public function __construct(string $message, ?string $externalOrderId = null)
    {
        parent::__construct($message);
        $this->externalOrderId = $externalOrderId;
    }
}

function reseller_request(string $method, string $endpoint, array $payload = [], ?string $idempotencyKey = null): array
{
    if (RESELLER_API_KEY === '' || RESELLER_API_KEY === 'PLACEHOLDER_API_KEY' || RESELLER_API_KEY === 'your_api_key') {
        throw new ResellerApiNotConfigured('Hamkor API hali ulanmagan.');
    }

    $url = rtrim(RESELLER_API_BASE, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        throw new ResellerApiException('Hamkor API so\'rovini boshlash imkonsiz.');
    }

    $headers = [
        'Accept: application/json',
        'X-API-Key: ' . RESELLER_API_KEY,
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if (strtoupper($method) === 'POST') {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            curl_close($ch);
            throw new ResellerApiException('Hamkor API uchun so\'rovni JSON ga aylantirib bo\'lmadi.');
        }
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    } else {
        $options[CURLOPT_HTTPGET] = true;
    }

    if ($idempotencyKey !== null) {
        $options[CURLOPT_HTTPHEADER][] = 'X-Idempotency-Key: ' . $idempotencyKey;
    }
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new ResellerApiUncertainException('Hamkor API bilan aloqa uzildi: ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpCode >= 500 || $httpCode === 408) {
        throw new ResellerApiUncertainException('Hamkor API vaqtinchalik xato qaytardi (HTTP ' . $httpCode . ').');
    }
    if ($httpCode >= 400) {
        throw new ResellerApiException(reseller_api_error_message($data, 'Hamkor API HTTP ' . $httpCode . ' xatosi.'));
    }
    if (!is_array($data) || !array_key_exists('ok', $data)) {
        throw new ResellerApiUncertainException('Hamkor API yaroqsiz javob qaytardi.');
    }
    if ($data['ok'] !== true) {
        throw new ResellerApiException(reseller_api_error_message($data, 'Hamkor API so\'rovni rad etdi.'));
    }
    if (!isset($data['result']) || !is_array($data['result'])) {
        throw new ResellerApiUncertainException('Hamkor API javobida buyurtma natijasi yo\'q.');
    }

    return $data['result'];
}

function reseller_api_error_message(?array $data, string $fallback): string
{
    if (!$data) {
        return $fallback;
    }

    $parts = array_filter([
        isset($data['code']) ? (string) $data['code'] : '',
        isset($data['message']) ? (string) $data['message'] : '',
    ]);
    return $parts ? implode(': ', $parts) : $fallback;
}

function reseller_purchase_result(array $result): array
{
    $orderId = isset($result['order_id']) ? (string) $result['order_id'] : null;
    $status = $result['status'] ?? '';

    if ($status === 'success') {
        return ['success' => true, 'external_order_id' => $orderId];
    }
    if ($status === 'failed') {
        throw new ResellerApiException('Hamkor buyurtmani bajara olmadi; hamkor balansingizga pulni qaytargan.');
    }
    if ($status !== 'processing' || !$orderId) {
        throw new ResellerApiUncertainException('Hamkor buyurtmasining yakuniy holati noma\'lum.', $orderId);
    }

    for ($attempt = 0; $attempt < 3; $attempt++) {
        sleep(1);
        try {
            $result = reseller_request('GET', '/order/' . rawurlencode($orderId));
        } catch (Throwable $e) {
            throw new ResellerApiUncertainException(
                'Hamkor buyurtmasining holatini tekshirib bo\'lmadi.',
                $orderId
            );
        }

        $status = $result['status'] ?? '';
        if ($status === 'success') {
            return ['success' => true, 'external_order_id' => $orderId];
        }
        if ($status === 'failed') {
            throw new ResellerApiException('Hamkor buyurtmani bajara olmadi; hamkor balansingizga pulni qaytargan.');
        }
        if ($status !== 'processing') {
            throw new ResellerApiUncertainException('Hamkor buyurtmasining yakuniy holati noma\'lum.', $orderId);
        }
    }

    throw new ResellerApiUncertainException('Hamkor buyurtmasi hali jarayonda.', $orderId);
}

function reseller_buy_stars(string $username, int $amount, string $idempotencyKey): array
{
    $result = reseller_request('POST', '/stars/buy', [
        'username' => ltrim($username, '@'),
        'amount' => $amount,
    ], $idempotencyKey);

    return reseller_purchase_result($result);
}

function reseller_buy_premium(string $username, int $months, string $idempotencyKey): array
{
    $result = reseller_request('POST', '/premium/buy', [
        'username' => ltrim($username, '@'),
        'duration' => $months,
    ], $idempotencyKey);

    return reseller_purchase_result($result);
}
