<?php
/**
 * PAYME.UZ WEBHOOK — Hisobni to'ldirish (topup)
 * URL: https://yourdomain.myxvest.ru/payments/payme.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/telegram.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expected = 'Basic ' . base64_encode('Paycom:' . PAYME_KEY);

$input = json_decode(file_get_contents('php://input'), true);
@file_put_contents(__DIR__ . '/../logs/payme.log', date('Y-m-d H:i:s') . ' ' . json_encode($input) . "\n", FILE_APPEND);

function payme_response($result = null, $error = null, $id = null): void
{
    header('Content-Type: application/json');
    $resp = ['jsonrpc' => '2.0', 'id' => $id];
    if ($error !== null) {
        $resp['error'] = $error;
    } else {
        $resp['result'] = $result;
    }
    echo json_encode($resp);
    exit;
}

const PAYME_ERR_PERM_DENIED     = -32504;
const PAYME_ERR_INVALID_AMOUNT  = -31001;
const PAYME_ERR_TRANS_NOT_FOUND = -31003;
const PAYME_ERR_UNABLE          = -31008;
const PAYME_ERR_NOT_FOUND       = -31050;

if (!hash_equals($expected, $authHeader)) {
    payme_response(null, ['code' => PAYME_ERR_PERM_DENIED, 'message' => 'Authorization failed'], $input['id'] ?? null);
}

$method = $input['method'] ?? '';
$params = $input['params'] ?? [];
$id = $input['id'] ?? null;
$pdo = db();

switch ($method) {

    case 'CheckPerformTransaction': {
        $topupId = (int) ($params['account']['topup_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM topups WHERE id = ?');
        $stmt->execute([$topupId]);
        $topup = $stmt->fetch();
        if (!$topup) {
            payme_response(null, ['code' => PAYME_ERR_NOT_FOUND, 'message' => 'Topup not found'], $id);
        }
        $amountTiyin = (int) round($topup['amount'] * 100);
        if ((int) $params['amount'] !== $amountTiyin) {
            payme_response(null, ['code' => PAYME_ERR_INVALID_AMOUNT, 'message' => 'Incorrect amount'], $id);
        }
        payme_response(['allow' => true], null, $id);
        break;
    }

    case 'CreateTransaction': {
        $topupId = (int) ($params['account']['topup_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM topups WHERE id = ?');
        $stmt->execute([$topupId]);
        $topup = $stmt->fetch();
        if (!$topup) {
            payme_response(null, ['code' => PAYME_ERR_NOT_FOUND, 'message' => 'Topup not found'], $id);
        }

        $stmt = $pdo->prepare('SELECT * FROM topups WHERE external_transaction_id = ?');
        $stmt->execute([$params['id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            payme_response([
                'create_time' => (int) (strtotime($existing['created_at']) * 1000),
                'transaction' => (string) $existing['id'],
                'state'       => 1,
            ], null, $id);
        }

        $amountTiyin = (int) round($topup['amount'] * 100);
        if ((int) $params['amount'] !== $amountTiyin) {
            payme_response(null, ['code' => PAYME_ERR_INVALID_AMOUNT, 'message' => 'Incorrect amount'], $id);
        }

        $stmt = $pdo->prepare('UPDATE topups SET external_transaction_id = ? WHERE id = ?');
        $stmt->execute([$params['id'], $topupId]);

        payme_response([
            'create_time' => (int) (microtime(true) * 1000),
            'transaction' => (string) $topupId,
            'state'       => 1,
        ], null, $id);
        break;
    }

    case 'PerformTransaction': {
        $stmt = $pdo->prepare('SELECT * FROM topups WHERE external_transaction_id = ?');
        $stmt->execute([$params['id']]);
        $topup = $stmt->fetch();
        if (!$topup) {
            payme_response(null, ['code' => PAYME_ERR_TRANS_NOT_FOUND, 'message' => 'Transaction not found'], $id);
        }

        if ($topup['status'] === 'paid') {
            payme_response([
                'transaction'  => (string) $topup['id'],
                'perform_time' => (int) (strtotime($topup['updated_at']) * 1000),
                'state'        => 2,
            ], null, $id);
        }

        $stmt = $pdo->prepare('UPDATE topups SET status = "paid" WHERE id = ?');
        $stmt->execute([$topup['id']]);
        add_balance((int) $topup['user_id'], (float) $topup['amount']);

        $user = get_user_by_id((int) $topup['user_id']);
        if ($user) {
            tg_send_message(
                (int) $user['telegram_id'],
                "✅ Hisobingiz muvaffaqiyatli to'ldirildi!\n\n💵 Qo'shildi: " . format_sum((float) $topup['amount']) .
                "\n💰 Yangi balans: " . format_sum((float) $user['balance'] + (float) $topup['amount'])
            );
        }

        payme_response([
            'transaction'  => (string) $topup['id'],
            'perform_time' => (int) (microtime(true) * 1000),
            'state'        => 2,
        ], null, $id);
        break;
    }

    case 'CancelTransaction': {
        $stmt = $pdo->prepare('SELECT * FROM topups WHERE external_transaction_id = ?');
        $stmt->execute([$params['id']]);
        $topup = $stmt->fetch();
        if (!$topup) {
            payme_response(null, ['code' => PAYME_ERR_TRANS_NOT_FOUND, 'message' => 'Transaction not found'], $id);
        }

        if ($topup['status'] !== 'paid') {
            $stmt = $pdo->prepare('UPDATE topups SET status = "cancelled" WHERE id = ?');
            $stmt->execute([$topup['id']]);
        }

        payme_response([
            'transaction' => (string) $topup['id'],
            'cancel_time' => (int) (microtime(true) * 1000),
            'state'       => $topup['status'] === 'paid' ? -2 : -1,
        ], null, $id);
        break;
    }

    case 'CheckTransaction': {
        $stmt = $pdo->prepare('SELECT * FROM topups WHERE external_transaction_id = ?');
        $stmt->execute([$params['id']]);
        $topup = $stmt->fetch();
        if (!$topup) {
            payme_response(null, ['code' => PAYME_ERR_TRANS_NOT_FOUND, 'message' => 'Transaction not found'], $id);
        }

        $stateMap = ['pending' => 1, 'paid' => 2, 'cancelled' => -1];
        payme_response([
            'create_time'  => (int) (strtotime($topup['created_at']) * 1000),
            'perform_time' => $topup['status'] === 'paid' ? (int) (strtotime($topup['updated_at']) * 1000) : 0,
            'cancel_time'  => $topup['status'] === 'cancelled' ? (int) (strtotime($topup['updated_at']) * 1000) : 0,
            'transaction'  => (string) $topup['id'],
            'state'        => $stateMap[$topup['status']] ?? 1,
            'reason'       => null,
        ], null, $id);
        break;
    }

    default:
        payme_response(null, ['code' => PAYME_ERR_UNABLE, 'message' => 'Method not found'], $id);
}
