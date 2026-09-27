<?php
/**
 * CLICK.UZ WEBHOOK — Hisobni to'ldirish (topup)
 * URL: https://yourdomain.myxvest.ru/payments/click.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/telegram.php';

$post = $_POST;
@file_put_contents(__DIR__ . '/../logs/click.log', date('Y-m-d H:i:s') . ' ' . json_encode($post) . "\n", FILE_APPEND);

$clickTransId      = $post['click_trans_id'] ?? '';
$serviceId          = $post['service_id'] ?? '';
$merchantTransId    = $post['merchant_trans_id'] ?? ''; // bizning topup id
$amount             = $post['amount'] ?? '';
$action             = $post['action'] ?? '';
$signTime           = $post['sign_time'] ?? '';
$signString         = $post['sign_string'] ?? '';
$error              = $post['error'] ?? 0;
$merchantPrepareId  = $post['merchant_prepare_id'] ?? '';

function click_response(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($action === '0') {
    $checkSign = md5($clickTransId . $serviceId . CLICK_SECRET_KEY . $merchantTransId . $amount . $action . $signTime);
} else {
    $checkSign = md5($clickTransId . $serviceId . CLICK_SECRET_KEY . $merchantTransId . $merchantPrepareId . $amount . $action . $signTime);
}
if ($checkSign !== $signString) {
    click_response(['error' => -1, 'error_note' => 'SIGN CHECK FAILED']);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM topups WHERE id = ?');
$stmt->execute([(int) $merchantTransId]);
$topup = $stmt->fetch();

if (!$topup) {
    click_response(['error' => -5, 'error_note' => 'Topup not found']);
}
if ((float) $amount !== (float) $topup['amount']) {
    click_response(['error' => -2, 'error_note' => 'Incorrect amount']);
}

if ($action === '0') {
    if ($topup['status'] === 'paid') {
        click_response(['error' => -4, 'error_note' => 'Already paid']);
    }
    $stmt = $pdo->prepare('UPDATE topups SET external_transaction_id = ? WHERE id = ?');
    $stmt->execute([$clickTransId, $topup['id']]);

    click_response([
        'click_trans_id'      => $clickTransId,
        'merchant_trans_id'   => $merchantTransId,
        'merchant_prepare_id' => $topup['id'],
        'error'               => 0,
        'error_note'          => 'Success',
    ]);
}

if ($action === '1') {
    if ((int) $error < 0) {
        $stmt = $pdo->prepare('UPDATE topups SET status = "cancelled" WHERE id = ?');
        $stmt->execute([$topup['id']]);
        click_response(['error' => -9, 'error_note' => 'Transaction cancelled']);
    }

    if ($topup['status'] !== 'paid') {
        $stmt = $pdo->prepare('UPDATE topups SET status = "paid" WHERE id = ?');
        $stmt->execute([$topup['id']]);

        add_balance((int) $topup['user_id'], (float) $topup['amount']);

        $user = get_user_by_id((int) $topup['user_id']);
        if ($user) {
            tg_send_message(
                (int) $user['telegram_id'],
                "✅ Hisobingiz muvaffaqiyatli to'ldirildi!\n\n" .
                "💵 Qo'shildi: " . format_sum((float) $topup['amount']) . "\n" .
                "💰 Yangi balans: " . format_sum((float) $user['balance'] + (float) $topup['amount'])
            );
        }
    }

    click_response([
        'click_trans_id'      => $clickTransId,
        'merchant_trans_id'   => $merchantTransId,
        'merchant_confirm_id' => $merchantPrepareId,
        'error'               => 0,
        'error_note'          => 'Success',
    ]);
}

click_response(['error' => -3, 'error_note' => 'Action not found']);
