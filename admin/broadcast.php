<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/telegram.php';
$activePage = 'broadcast';

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    set_time_limit(0);
    $message = trim($_POST['message']);

    $pdo = db();
    $users = $pdo->query('SELECT telegram_id FROM users WHERE is_blocked = 0')->fetchAll();

    $sent = 0;
    $failed = 0;
    foreach ($users as $u) {
        $res = tg_send_message((int) $u['telegram_id'], $message);
        if (!empty($res['ok'])) {
            $sent++;
        } else {
            $failed++;
        }
        usleep(50000); // ~20 xabar/soniya — Telegram flood-limitidan saqlanish uchun
    }

    $stmt = $pdo->prepare('INSERT INTO broadcasts (message, sent_count, failed_count) VALUES (?, ?, ?)');
    $stmt->execute([$message, $sent, $failed]);

    $result = ['sent' => $sent, 'failed' => $failed, 'total' => count($users)];
}

$history = db()->query('SELECT * FROM broadcasts ORDER BY id DESC LIMIT 10')->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Xabar yuborish — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="panel" style="max-width:560px;">
    <h3>📣 Barcha foydalanuvchilarga xabar yuborish</h3>

    <?php if ($result): ?>
        <p class="success">✅ Yuborildi: <?= $result['sent'] ?> ta | ❌ Xato: <?= $result['failed'] ?> ta | Jami: <?= $result['total'] ?> ta</p>
    <?php endif; ?>

    <form method="post" class="form" onsubmit="return confirm('Xabar barcha foydalanuvchilarga yuboriladi. Davom etilsinmi?');">
        <label>Xabar matni (HTML formatlash qo'llab-quvvatlanadi: &lt;b&gt;, &lt;i&gt;, &lt;a&gt;)</label>
        <textarea name="message" rows="6" required placeholder="Assalomu alaykum! Yangilik: ..."></textarea>
        <button type="submit" class="btn-primary">📤 Yuborish</button>
    </form>
    <p class="hint-text">Katta bazalarda yuborish bir necha daqiqa vaqt olishi mumkin — sahifani yopmang.</p>
</div>

<div class="panel" style="max-width:560px;">
    <h3>Tarix</h3>
    <table>
        <thead><tr><th>Sana</th><th>Xabar</th><th>Yuborildi</th><th>Xato</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td><?= $h['created_at'] ?></td>
                <td><?= htmlspecialchars(mb_substr($h['message'], 0, 60)) ?>...</td>
                <td><?= $h['sent_count'] ?></td>
                <td><?= $h['failed_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="4" style="text-align:center;">Tarix bo'sh</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
