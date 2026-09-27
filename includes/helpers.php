<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reseller_api.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/settings.php';

function get_or_create_user(array $tgUser): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
    $stmt->execute([$tgUser['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (telegram_id, username, first_name) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $tgUser['id'],
            $tgUser['username'] ?? null,
            $tgUser['first_name'] ?? null,
        ]);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
        $stmt->execute([$tgUser['id']]);
        $user = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare('UPDATE users SET username = ?, first_name = ? WHERE id = ?');
        $stmt->execute([$tgUser['username'] ?? null, $tgUser['first_name'] ?? null, $user['id']]);
    }

    return $user;
}

function set_state(int $telegramId, string $state, array $data = []): void
{
    $stmt = db()->prepare(
        'INSERT INTO user_states (telegram_id, state, data) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)'
    );
    $stmt->execute([$telegramId, $state, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function get_state(int $telegramId): array
{
    $stmt = db()->prepare('SELECT * FROM user_states WHERE telegram_id = ?');
    $stmt->execute([$telegramId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['state' => 'idle', 'data' => []];
    }
    return [
        'state' => $row['state'],
        'data'  => json_decode($row['data'] ?? '{}', true) ?: [],
    ];
}

function reset_state(int $telegramId): void
{
    set_state($telegramId, 'idle', []);
}

function add_balance(int $userId, float $amount): void
{
    $stmt = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
    $stmt->execute([$amount, $userId]);
}

function get_user_by_id(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    return $u ?: null;
}

/**
 * Stars/Premium sotib olish: balansdan bir zumda yechiladi va
 * darhol yetkazib berishga urinadi (hamkor API orqali). API hali
 * ulanmagan bo'lsa — buyurtma "qo'lda bajarish" holatida qoladi va
 * admin panelda ko'rinadi.
 */
function purchase(int $userId, string $type, string $targetUsername, int $amount, float $price): array
{
    $pdo = db();
    $user = get_user_by_id($userId);
    if (!$user) {
        return ['success' => false, 'message' => 'Foydalanuvchi topilmadi.', 'order_id' => null];
    }
    if ((float) $user['balance'] < $price) {
        return ['success' => false, 'message' => 'insufficient_balance', 'order_id' => null];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?');
        $stmt->execute([$price, $userId, $price]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'insufficient_balance', 'order_id' => null];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO orders (user_id, type, target_username, amount, price, status)
             VALUES (?, ?, ?, ?, ?, "awaiting_manual")'
        );
        $stmt->execute([$userId, $type, $targetUsername, $amount, $price]);
        $orderId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('purchase() xatosi: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ichki xatolik yuz berdi.', 'order_id' => null];
    }

    fulfill_order($orderId);

    return ['success' => true, 'message' => 'ok', 'order_id' => $orderId];
}

function get_order(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function update_order_status(int $orderId, string $status, ?string $errorMessage = null, ?string $resellerOrderId = null): void
{
    $stmt = db()->prepare(
        'UPDATE orders SET status = ?, error_message = ?, reseller_order_id = COALESCE(?, reseller_order_id) WHERE id = ?'
    );
    $stmt->execute([$status, $errorMessage, $resellerOrderId, $orderId]);
}

function fulfill_order(int $orderId): void
{
    $order = get_order($orderId);
    if (!$order) {
        return;
    }
    $user = get_user_by_id((int) $order['user_id']);
    if (!$user) {
        return;
    }

    try {
        if ($order['type'] === 'stars') {
            $result = reseller_buy_stars($order['target_username'], (int) $order['amount']);
        } else {
            $result = reseller_buy_premium($order['target_username'], (int) $order['amount']);
        }

        if (!empty($result['success'])) {
            update_order_status($orderId, 'completed', null, $result['external_order_id'] ?? null);
            notify_order_completed($user, $order);
        } else {
            throw new Exception($result['message'] ?? "Noma'lum xatolik");
        }
    } catch (ResellerApiNotConfigured $e) {
        // Hamkor API hali ulanmagan — normal holat, admin qo'lda bajaradi.
        tg_send_message(
            (int) $user['telegram_id'],
            "✅ To'lovingiz qabul qilindi!\n\n" .
            "🧾 Buyurtma #{$orderId} tez orada qo'lda tekshirilib, yetkazib beriladi. " .
            "Savol bo'lsa qo'llab-quvvatlashga yozing."
        );
        foreach (ADMIN_CHAT_IDS as $adminId) {
            $label = $order['type'] === 'stars'
                ? "⭐ {$order['amount']} Stars"
                : "💎 {$order['amount']} oy Premium";
            tg_send_message(
                (int) $adminId,
                "🆕 Yangi buyurtma #{$orderId} (qo'lda bajarish kerak)\n" .
                "{$label} → @{$order['target_username']}\n" .
                "Xaridor: @{$user['username']} (ID: {$user['telegram_id']})\n" .
                "Narx: " . format_sum((float) $order['price']) . "\n\n" .
                "Admin panelda 'Bajarildi' deb belgilang."
            );
        }
    } catch (Throwable $e) {
        update_order_status($orderId, 'failed', $e->getMessage());
        error_log('Fulfill order #' . $orderId . ' xatosi: ' . $e->getMessage());
        add_balance((int) $user['id'], (float) $order['price']);

        tg_send_message(
            (int) $user['telegram_id'],
            "⚠️ Buyurtma #{$orderId} bajarilmadi, mablag' hisobingizga qaytarildi."
        );
        foreach (ADMIN_CHAT_IDS as $adminId) {
            tg_send_message((int) $adminId, "🚨 Buyurtma #{$orderId} xato: " . $e->getMessage());
        }
    }
}

function notify_order_completed(array $user, array $order): void
{
    $label = $order['type'] === 'stars'
        ? "⭐ {$order['amount']} ta Stars"
        : "💎 {$order['amount']} oylik Premium";
    tg_send_message(
        (int) $user['telegram_id'],
        "✅ Xarid muvaffaqiyatli amalga oshirildi!\n\n{$label} — @{$order['target_username']} akkauntiga yuborildi."
    );
}

/** Admin panelda "Bajarildi" deb belgilanganda chaqiriladi. */
function mark_order_completed_manually(int $orderId): bool
{
    $order = get_order($orderId);
    if (!$order || $order['status'] !== 'awaiting_manual') {
        return false;
    }
    $user = get_user_by_id((int) $order['user_id']);
    update_order_status($orderId, 'completed');
    if ($user) {
        notify_order_completed($user, $order);
    }
    return true;
}

function get_topup(int $topupId): ?array
{
    $stmt = db()->prepare('SELECT * FROM topups WHERE id = ?');
    $stmt->execute([$topupId]);
    $t = $stmt->fetch();
    return $t ?: null;
}

/** Karta orqali yuborilgan to'lovni admin tasdiqlaganda chaqiriladi. */
function approve_card_topup(int $topupId): bool
{
    $topup = get_topup($topupId);
    if (!$topup || $topup['status'] !== 'pending' || $topup['method'] !== 'card') {
        return false;
    }
    $stmt = db()->prepare('UPDATE topups SET status = "paid" WHERE id = ?');
    $stmt->execute([$topupId]);
    add_balance((int) $topup['user_id'], (float) $topup['amount']);

    $user = get_user_by_id((int) $topup['user_id']);
    if ($user) {
        tg_send_message(
            (int) $user['telegram_id'],
            "✅ To'lovingiz tasdiqlandi!\n\n💵 Qo'shildi: " . format_sum((float) $topup['amount']) .
            "\n💰 Yangi balans: " . format_sum((float) $user['balance'] + (float) $topup['amount'])
        );
    }
    return true;
}

/** Karta orqali yuborilgan to'lovni admin rad etganda chaqiriladi. */
function reject_card_topup(int $topupId, string $reason = ''): bool
{
    $topup = get_topup($topupId);
    if (!$topup || $topup['status'] !== 'pending' || $topup['method'] !== 'card') {
        return false;
    }
    $stmt = db()->prepare('UPDATE topups SET status = "cancelled" WHERE id = ?');
    $stmt->execute([$topupId]);

    $user = get_user_by_id((int) $topup['user_id']);
    if ($user) {
        $msg = "❌ To'lov cheki tasdiqlanmadi.";
        if ($reason) {
            $msg .= "\nSabab: {$reason}";
        }
        $msg .= "\n\nIltimos qaytadan to'g'ri chek yuboring yoki qo'llab-quvvatlashga murojaat qiling.";
        tg_send_message((int) $user['telegram_id'], $msg);
    }
    return true;
}
