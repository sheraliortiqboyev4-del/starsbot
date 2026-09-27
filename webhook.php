<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/channels.php';

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) {
    http_response_code(200);
    exit;
}

try {
    if (isset($update['callback_query'])) {
        handle_callback($update['callback_query']);
        exit;
    }
    if (isset($update['message'])) {
        handle_message($update['message']);
        exit;
    }
} catch (\Throwable $e) {
    // Har qanday kutilmagan xatoni (DB uzilishi, vaqtinchalik nosozlik va h.k.)
    // shu yerda ushlaymiz, shunda foydalanuvchi hech narsa ko'rmay qolmaydi
    // va tugma "muzlab" qolmaydi.
    error_log('Webhook xatosi: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

    try {
        if (isset($update['callback_query'])) {
            $chatId = $update['callback_query']['message']['chat']['id'] ?? null;
            $callbackId = $update['callback_query']['id'] ?? null;
            if ($callbackId) {
                tg_answer_callback($callbackId, '⚠️ Vaqtinchalik xatolik, qayta urining', true);
            }
            if ($chatId) {
                tg_send_message((int) $chatId, "⚠️ Vaqtinchalik xatolik yuz berdi. Iltimos, qayta urinib ko'ring yoki /start bosing.");
            }
        } elseif (isset($update['message']['chat']['id'])) {
            tg_send_message((int) $update['message']['chat']['id'], "⚠️ Vaqtinchalik xatolik yuz berdi. Iltimos, qayta urinib ko'ring yoki /start bosing.");
        }
    } catch (\Throwable $inner) {
        error_log('Webhook xato-xabari yuborishda ham xato: ' . $inner->getMessage());
    }

    http_response_code(200); // Telegram qayta-qayta yubormasligi uchun 200 qaytaramiz
    exit;
}
exit;

/* ==================================================================== */

function main_menu(): array
{
    return inline_keyboard([
        [['text' => '⭐ Stars olish', 'callback_data' => 'menu_stars']],
        [
            ['text' => '⭐ Premium olish', 'callback_data' => 'menu_premium'],
            ['text' => '💳 Hisob to\'ldirish', 'callback_data' => 'menu_topup'],
        ],
        [
            ['text' => '👤 Kabinet', 'callback_data' => 'menu_kabinet'],
            ['text' => '☎️ Yordam', 'callback_data' => 'menu_help'],
        ],
    ]);
}

function back_row(): array
{
    return [['text' => '↩️ Orqaga', 'callback_data' => 'menu_main']];
}

function welcome_text(array $user): string
{
    return "👋 Assalomu alaykum, {$user['first_name']}!\n\n" .
        "🪪 User ID: {$user['telegram_id']}\n" .
        "💰 Hisobingiz: <b>" . format_sum((float) $user['balance']) . "</b>\n\n" .
        "👇 Quyidagi menyudan keraklisini tanlang:";
}

function handle_message(array $message): void
{
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $tgUser = $message['from'];
    $user = get_or_create_user($tgUser);

    if ($text === '/start') {
        reset_state($chatId);
        send_main_menu($chatId, $user);
        return;
    }

    if ($text === '/panel') {
        if (in_array((int) $chatId, ADMIN_CHAT_IDS, true)) {
            tg_send_message($chatId, "🔐 Admin panel:", [
                'inline_keyboard' => [[
                    ['text' => '📊 Admin panelni ochish', 'web_app' => ['url' => SITE_URL . '/admin/login.php']],
                ]],
            ]);
        }
        return;
    }

    // Chek skrinshoti (rasm) kutilayotgan bo'lsa
    if (isset($message['photo'])) {
        handle_photo_message($chatId, $message);
        return;
    }

    $stateInfo = get_state($chatId);
    handle_state_input($chatId, $user, $stateInfo['state'], $stateInfo['data'], $text);
}

function handle_photo_message(int $chatId, array $message): void
{
    $stateInfo = get_state($chatId);
    if ($stateInfo['state'] !== 'awaiting_card_proof') {
        return; // kutilmagan rasm — e'tiborsiz qoldiramiz
    }
    $topupId = (int) ($stateInfo['data']['topup_id'] ?? 0);
    $topup = get_topup($topupId);
    if (!$topup || $topup['status'] !== 'pending') {
        tg_send_message($chatId, "❌ Bu to'lov so'rovi endi amal qilmaydi. Qaytadan boshlang: /start");
        reset_state($chatId);
        return;
    }

    $photos = $message['photo'];
    $fileId = end($photos)['file_id']; // eng katta o'lchamdagisi

    $stmt = db()->prepare('UPDATE topups SET proof_file_id = ? WHERE id = ?');
    $stmt->execute([$fileId, $topupId]);
    reset_state($chatId);

    tg_send_message($chatId, "✅ Chek qabul qilindi! Admin tez orada tekshirib, hisobingizni to'ldiradi.");

    $user = get_user_by_id((int) $topup['user_id']);
    foreach (ADMIN_CHAT_IDS as $adminId) {
        tg_api('sendPhoto', [
            'chat_id' => $adminId,
            'photo'   => $fileId,
            'caption' => "💳 Yangi to'lov so'rovi #{$topupId}\n" .
                "Summasi: " . format_sum((float) $topup['amount']) . "\n" .
                "Xaridor: @" . ($user['username'] ?? '-') . " (ID: {$user['telegram_id']})",
            'reply_markup' => json_encode(inline_keyboard([[
                ['text' => '✅ Tasdiqlash', 'callback_data' => "topup_ok_{$topupId}"],
                ['text' => '❌ Rad etish', 'callback_data' => "topup_no_{$topupId}"],
            ]])),
        ]);
    }
}

function send_main_menu(int $chatId, array $user, ?int $editMessageId = null): void
{
    $unsub = get_unsubscribed_channels((int) $user['telegram_id']);
    if ($unsub) {
        $text = "📢 Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:";
        if ($editMessageId) {
            tg_edit_message($chatId, $editMessageId, $text, subscription_keyboard($unsub));
        } else {
            tg_send_message($chatId, $text, subscription_keyboard($unsub));
        }
        return;
    }

    $text = welcome_text($user);
    if ($editMessageId) {
        tg_edit_message($chatId, $editMessageId, $text, main_menu());
    } else {
        tg_send_message($chatId, $text, main_menu());
    }
}

function handle_callback(array $callback): void
{
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $callbackId = $callback['id'];
    $data = $callback['data'];
    $tgUser = $callback['from'];
    $user = get_or_create_user($tgUser);

    tg_answer_callback($callbackId);

    if ($data === 'check_subs') {
        $unsub = get_unsubscribed_channels((int) $user['telegram_id']);
        if ($unsub) {
            tg_edit_message($chatId, $messageId, "❌ Hali barcha kanallarga a'zo bo'lmadingiz:", subscription_keyboard($unsub));
        } else {
            tg_edit_message($chatId, $messageId, welcome_text($user), main_menu());
        }
        return;
    }

    // Har doim majburiy obunani tekshiramiz (kanal talab qilinsa)
    if (get_unsubscribed_channels((int) $user['telegram_id'])) {
        send_main_menu($chatId, $user, $messageId);
        return;
    }

    switch (true) {
        case $data === 'menu_main':
            reset_state($chatId);
            send_main_menu($chatId, $user, $messageId);
            return;

        case $data === 'menu_stars':
            reset_state($chatId);
            set_state($chatId, 'awaiting_stars_username');
            tg_edit_message($chatId, $messageId,
                "⭐ Stars kimga yuborilsin?\nTelegram <b>username</b>ini yozing (masalan: @durov):",
                inline_keyboard([back_row()])
            );
            return;

        case $data === 'menu_premium':
            reset_state($chatId);
            set_state($chatId, 'awaiting_premium_username');
            tg_edit_message($chatId, $messageId,
                "⭐ Premium kimga faollashtirilsin?\nTelegram <b>username</b>ini yozing (masalan: @durov):",
                inline_keyboard([back_row()])
            );
            return;

        case $data === 'menu_topup':
            reset_state($chatId);
            set_state($chatId, 'awaiting_topup_amount');
            tg_edit_message($chatId, $messageId,
                "💳 Hisobni qancha summaga to'ldirmoqchisiz?\nSummani so'mda yozing (masalan: 50000):",
                inline_keyboard([back_row()])
            );
            return;

        case $data === 'menu_kabinet':
            send_kabinet($chatId, $user, $messageId);
            return;

        case $data === 'menu_help':
            tg_edit_message($chatId, $messageId,
                "☎️ Yordam\n\nSavollar bo'lsa admin bilan bog'laning: @your_admin_username\n\n" .
                "Foydalanish: menyudan bo'limni tanlang, so'ralganini yozing — xarid balansdan avtomatik amalga oshadi.",
                inline_keyboard([back_row()])
            );
            return;

        case str_starts_with($data, 'stars_amt_'):
            $amount = (int) str_replace('stars_amt_', '', $data);
            do_stars_purchase($chatId, $user, $messageId, $amount);
            return;

        case str_starts_with($data, 'prem_'):
            $months = (int) str_replace('prem_', '', $data);
            do_premium_purchase($chatId, $user, $messageId, $months);
            return;

        case str_starts_with($data, 'pay_'):
            [, $method, $topupId] = explode('_', $data);
            if ($method === 'card') {
                $topup = get_topup((int) $topupId);
                if ($topup) {
                    set_state($chatId, 'awaiting_card_proof', ['topup_id' => (int) $topupId]);
                    tg_edit_message($chatId, $messageId,
                        "💳 " . format_sum((float) $topup['amount']) . " miqdorida quyidagi kartaga o'tkazing:\n\n" .
                        "💳 <b>" . card_number() . "</b>\n👤 " . card_holder() . "\n\n" .
                        "To'lovni amalga oshirgach, chek/skrinshotni shu yerga rasm qilib yuboring 📸"
                    );
                }
                return;
            }
            send_topup_link($chatId, $messageId, (int) $topupId, $method);
            return;

        case str_starts_with($data, 'topup_ok_'):
            if (!in_array((int) $chatId, ADMIN_CHAT_IDS, true)) return;
            $tid = (int) str_replace('topup_ok_', '', $data);
            approve_card_topup($tid);
            tg_send_message($chatId, "✅ To'lov #{$tid} tasdiqlandi.");
            return;

        case str_starts_with($data, 'topup_no_'):
            if (!in_array((int) $chatId, ADMIN_CHAT_IDS, true)) return;
            $tid = (int) str_replace('topup_no_', '', $data);
            reject_card_topup($tid);
            tg_send_message($chatId, "❌ To'lov #{$tid} rad etildi.");
            return;
    }
}

function handle_state_input(int $chatId, array $user, string $state, array $data, string $text): void
{
    switch ($state) {
        case 'awaiting_stars_username':
            $username = ltrim($text, '@');
            if (!preg_match('/^[A-Za-z0-9_]{5,32}$/', $username)) {
                tg_send_message($chatId, "❌ Username noto'g'ri formatda. Qaytadan yozing (masalan: @durov):");
                return;
            }
            set_state($chatId, 'idle', ['target' => $username]);
            $pps = price_per_star();
            $amounts = [50, 75, 100, 150, 250, 350, 500, 750, 1000, 1500, 3000, 5000];
            $rows = [];
            foreach (array_chunk($amounts, 2) as $pair) {
                $row = [];
                foreach ($pair as $a) {
                    $row[] = ['text' => "⭐ {$a} — " . format_sum($a * $pps), 'callback_data' => "stars_amt_{$a}"];
                }
                $rows[] = $row;
            }
            $rows[] = back_row();
            tg_send_message($chatId, "Nechta Stars sotib olmoqchisiz?", inline_keyboard($rows));
            return;

        case 'awaiting_premium_username':
            $username = ltrim($text, '@');
            if (!preg_match('/^[A-Za-z0-9_]{5,32}$/', $username)) {
                tg_send_message($chatId, "❌ Username noto'g'ri formatda. Qaytadan yozing (masalan: @durov):");
                return;
            }
            set_state($chatId, 'idle', ['target' => $username]);
            $prices = premium_prices();
            $rows = [];
            foreach ($prices as $months => $price) {
                $rows[] = [['text' => "{$months} oy — " . format_sum($price), 'callback_data' => "prem_{$months}"]];
            }
            $rows[] = back_row();
            tg_send_message($chatId, "Qaysi muddatga Premium olmoqchisiz?", inline_keyboard($rows));
            return;

        case 'awaiting_topup_amount':
            $amount = (int) preg_replace('/\D/', '', $text);
            if ($amount < 1000) {
                tg_send_message($chatId, "❌ Iltimos, kamida 1000 so'm miqdorida summa kiriting:");
                return;
            }

            $methods = enabled_topup_methods();
            if (!$methods) {
                tg_send_message($chatId, "⚠️ Hozircha to'lov usullari sozlanmagan. Admin bilan bog'laning.");
                reset_state($chatId);
                return;
            }

            // Faqat karta usuli yoqilgan bo'lsa — to'g'ridan-to'g'ri karta ma'lumotini ko'rsatamiz
            if ($methods === ['card']) {
                $stmt = db()->prepare('INSERT INTO topups (user_id, method, amount, status) VALUES (?, "card", ?, "pending")');
                $stmt->execute([$user['id'], $amount]);
                $topupId = (int) db()->lastInsertId();
                set_state($chatId, 'awaiting_card_proof', ['topup_id' => $topupId]);

                tg_send_message($chatId,
                    "💳 " . format_sum($amount) . " miqdorida quyidagi kartaga o'tkazing:\n\n" .
                    "💳 <b>" . card_number() . "</b>\n👤 " . card_holder() . "\n\n" .
                    "To'lovni amalga oshirgach, chek/skrinshotni shu yerga rasm qilib yuboring 📸"
                );
                return;
            }

            // Bir nechta usul yoqilgan bo'lsa — tanlash tugmalarini ko'rsatamiz
            $stmt = db()->prepare('INSERT INTO topups (user_id, method, amount, status) VALUES (?, "card", ?, "pending")');
            $stmt->execute([$user['id'], $amount]);
            $topupId = (int) db()->lastInsertId();
            set_state($chatId, 'idle');

            $btns = [];
            if (in_array('card', $methods, true)) $btns[] = ['text' => '💳 Karta', 'callback_data' => "pay_card_{$topupId}"];
            if (in_array('click', $methods, true)) $btns[] = ['text' => '💳 Click', 'callback_data' => "pay_click_{$topupId}"];
            if (in_array('payme', $methods, true)) $btns[] = ['text' => '💳 Payme', 'callback_data' => "pay_payme_{$topupId}"];

            tg_send_message($chatId, "💳 " . format_sum($amount) . " miqdorida to'lov usulini tanlang:", inline_keyboard([$btns]));
            return;

        default:
            // holat yo'q — asosiy menyuni ko'rsatamiz
            send_main_menu($chatId, $user);
            return;
    }
}

function do_stars_purchase(int $chatId, array $user, int $messageId, int $amount): void
{
    $stateInfo = get_state($chatId);
    $target = $stateInfo['data']['target'] ?? null;
    if (!$target) {
        tg_edit_message($chatId, $messageId, "❌ Sessiya eskirdi, qaytadan boshlang: /start", inline_keyboard([back_row()]));
        return;
    }
    $price = round($amount * price_per_star(), 2);
    $result = purchase((int) $user['id'], 'stars', $target, $amount, $price);
    render_purchase_result($chatId, $messageId, $result, $price, $user);
}

function do_premium_purchase(int $chatId, array $user, int $messageId, int $months): void
{
    $stateInfo = get_state($chatId);
    $target = $stateInfo['data']['target'] ?? null;
    if (!$target) {
        tg_edit_message($chatId, $messageId, "❌ Sessiya eskirdi, qaytadan boshlang: /start", inline_keyboard([back_row()]));
        return;
    }
    $prices = premium_prices();
    $price = $prices[$months] ?? null;
    if ($price === null) {
        tg_edit_message($chatId, $messageId, "❌ Noto'g'ri muddat.", inline_keyboard([back_row()]));
        return;
    }
    $result = purchase((int) $user['id'], 'premium', $target, $months, $price);
    render_purchase_result($chatId, $messageId, $result, $price, $user);
}

function render_purchase_result(int $chatId, int $messageId, array $result, float $price, array $user): void
{
    if (!$result['success']) {
        if ($result['message'] === 'insufficient_balance') {
            tg_edit_message($chatId, $messageId,
                "❌ Balansingizda mablag' yetarli emas.\n\nKerak: <b>" . format_sum($price) . "</b>\n\nIltimos, avval hisobingizni to'ldiring.",
                inline_keyboard([
                    [['text' => "💳 Hisob to'ldirish", 'callback_data' => 'menu_topup']],
                    back_row(),
                ])
            );
        } else {
            tg_edit_message($chatId, $messageId, "❌ Xatolik: " . $result['message'], inline_keyboard([back_row()]));
        }
        return;
    }

    // Yangi balansni ko'rsatish uchun foydalanuvchini qayta o'qiymiz
    $fresh = get_user_by_id((int) $user['id']);
    $balance = $fresh ? (float) $fresh['balance'] : 0;

    tg_edit_message($chatId, $messageId,
        "🧾 Buyurtma #{$result['order_id']} qabul qilindi!\n💰 Yechildi: " . format_sum($price) .
        "\n💰 Qolgan balans: " . format_sum($balance) . "\n\nHolat haqida tez orada xabar beramiz.",
        main_menu()
    );
}

function send_kabinet(int $chatId, array $user, int $messageId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) c, COALESCE(SUM(price),0) s FROM orders WHERE user_id = ? AND status = "completed"');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    tg_edit_message($chatId, $messageId,
        "👤 Kabinet\n\n🪪 User ID: {$user['telegram_id']}\n💰 Hisobingiz: <b>" . format_sum((float) $user['balance']) . "</b>\n" .
        "🛍️ Buyurtmalaringiz: {$row['c']} ta\n💳 Jami xarajat: " . format_sum((float) $row['s']),
        inline_keyboard([
            [['text' => "💳 Hisob to'ldirish", 'callback_data' => 'menu_topup']],
            back_row(),
        ])
    );
}

function send_topup_link(int $chatId, int $messageId, int $topupId, string $method): void
{
    $stmt = db()->prepare('SELECT * FROM topups WHERE id = ?');
    $stmt->execute([$topupId]);
    $topup = $stmt->fetch();
    if (!$topup) {
        tg_edit_message($chatId, $messageId, "❌ Topilmadi.", inline_keyboard([back_row()]));
        return;
    }

    $stmt = db()->prepare('UPDATE topups SET method = ? WHERE id = ?');
    $stmt->execute([$method, $topupId]);

    if ($method === 'click') {
        $link = 'https://my.click.uz/services/pay?service_id=' . CLICK_SERVICE_ID .
            '&merchant_id=' . CLICK_MERCHANT_ID .
            '&amount=' . $topup['amount'] .
            '&transaction_param=' . $topupId .
            '&return_url=' . urlencode(SITE_URL);
    } else {
        $params = base64_encode('m=' . PAYME_MERCHANT_ID . ';ac.topup_id=' . $topupId . ';a=' . ($topup['amount'] * 100));
        $link = 'https://checkout.paycom.uz/' . $params;
    }

    tg_edit_message($chatId, $messageId,
        "💳 To'lovni yakunlash uchun havolaga o'ting:\n{$link}\n\nTo'lov tasdiqlangach hisobingiz avtomatik to'ldiriladi. ✅",
        inline_keyboard([back_row()])
    );
}
