<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';

function get_required_channels(): array
{
    return db()->query('SELECT * FROM required_channels ORDER BY id')->fetchAll();
}

function add_required_channel(string $chatId, ?string $title, ?string $inviteLink): void
{
    $stmt = db()->prepare('INSERT INTO required_channels (chat_id, title, invite_link) VALUES (?, ?, ?)');
    $stmt->execute([$chatId, $title, $inviteLink]);
}

function remove_required_channel(int $id): void
{
    $stmt = db()->prepare('DELETE FROM required_channels WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Foydalanuvchi barcha majburiy kanallarga a'zo bo'lganini tekshiradi.
 * @return array bo'sh array = hammasiga a'zo. Aks holda a'zo bo'lmagan kanallar ro'yxati.
 */
function get_unsubscribed_channels(int $telegramId): array
{
    $channels = get_required_channels();
    if (!$channels) {
        return [];
    }

    $unsubscribed = [];
    foreach ($channels as $ch) {
        $res = tg_api('getChatMember', [
            'chat_id' => $ch['chat_id'],
            'user_id' => $telegramId,
        ]);

        $status = $res['result']['status'] ?? null;
        $isMember = in_array($status, ['member', 'administrator', 'creator'], true);

        if (!$isMember) {
            $unsubscribed[] = $ch;
        }
    }
    return $unsubscribed;
}

function subscription_keyboard(array $unsubscribedChannels): array
{
    $rows = [];
    foreach ($unsubscribedChannels as $ch) {
        $url = $ch['invite_link'] ?: ('https://t.me/' . ltrim($ch['chat_id'], '@'));
        $rows[] = [['text' => '➕ ' . ($ch['title'] ?: $ch['chat_id']), 'url' => $url]];
    }
    $rows[] = [['text' => '✅ A\'zo bo\'ldim, tekshirish', 'callback_data' => 'check_subs']];
    return ['inline_keyboard' => $rows];
}
