<?php
require_once __DIR__ . '/../config.php';

/**
 * Telegram Bot API'ga so'rov yuborish
 */
function tg_api(string $method, array $params = [])
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('Telegram API cURL xatosi: ' . $err);
        return null;
    }
    return json_decode($response, true);
}

function tg_send_message(int $chatId, string $text, ?array $keyboard = null, string $parseMode = 'HTML')
{
    $params = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => $parseMode,
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    return tg_api('sendMessage', $params);
}

function tg_answer_callback(string $callbackId, string $text = '', bool $alert = false)
{
    return tg_api('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text'              => $text,
        'show_alert'        => $alert,
    ]);
}

function tg_edit_message(int $chatId, int $messageId, string $text, ?array $keyboard = null, string $parseMode = 'HTML')
{
    $params = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text,
        'parse_mode' => $parseMode,
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    return tg_api('editMessageText', $params);
}

/** Asosiy pastki menyu (Reply Keyboard) */
function main_menu_keyboard(): array
{
    return [
        'keyboard' => [
            [
                ['text' => '⭐ Stars sotib olish', 'style' => 'success'],
                ['text' => '💎 Premium sotib olish', 'style' => 'success'],
            ],
            [
                ['text' => '📦 Buyurtmalarim', 'style' => 'primary'],
                ['text' => '💰 Narxlar', 'style' => 'primary'],
            ],
            [['text' => 'ℹ️ Yordam', 'style' => 'primary']],
        ],
        'resize_keyboard'   => true,
        'is_persistent'     => true,
    ];
}

function inline_keyboard(array $rows): array
{
    foreach ($rows as &$row) {
        foreach ($row as &$button) {
            if (!is_array($button) || isset($button['style'])) {
                continue;
            }

            $callback = (string) ($button['callback_data'] ?? '');
            $text = mb_strtolower((string) ($button['text'] ?? ''), 'UTF-8');

            if (in_array($callback, ['menu_stars', 'menu_gift'], true) ||
                str_starts_with($callback, 'stars_amt_') ||
                str_starts_with($callback, 'prem_') ||
                str_starts_with($callback, 'topup_ok_') ||
                $callback === 'check_subs' ||
                str_contains($text, 'tasdiqlash') ||
                str_contains($text, 'bajarildi')) {
                $button['style'] = 'success';
            } elseif (str_starts_with($callback, 'topup_no_') ||
                str_contains($text, 'rad etish') ||
                str_contains($text, 'bekor')) {
                $button['style'] = 'danger';
            } else {
                $button['style'] = 'primary';
            }
        }
        unset($button);
    }
    unset($row);

    return ['inline_keyboard' => $rows];
}
