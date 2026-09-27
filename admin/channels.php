<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/channels.php';
$activePage = 'channels';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_channel'])) {
        $chatId = trim($_POST['chat_id']);
        $title = trim($_POST['title']) ?: null;
        $link = trim($_POST['invite_link']) ?: null;
        if ($chatId !== '') {
            add_required_channel($chatId, $title, $link);
        }
    } elseif (isset($_POST['delete_id'])) {
        remove_required_channel((int) $_POST['delete_id']);
    }
    header('Location: channels.php');
    exit;
}

$channels = get_required_channels();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Kanallar — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="panel" style="max-width:560px;">
    <h3>📢 Majburiy obuna kanallari</h3>
    <p class="hint-text">Bu yerga qo'shilgan kanallarga a'zo bo'lmagan foydalanuvchi botdan foydalana olmaydi.
    Botni kanalga <b>admin</b> qilib qo'yishni unutmang (a'zolikni tekshirish uchun shart).</p>

    <form method="post" class="form">
        <label>Kanal ID yoki username (masalan: @mychannel yoki -1001234567890)</label>
        <input type="text" name="chat_id" required>
        <label>Nomi (ixtiyoriy, tugmada ko'rinadi)</label>
        <input type="text" name="title">
        <label>Taklif havolasi (ixtiyoriy, agar kanal yopiq bo'lsa)</label>
        <input type="text" name="invite_link" placeholder="https://t.me/+xxxxx">
        <button type="submit" name="add_channel" value="1" class="btn-primary">➕ Qo'shish</button>
    </form>
</div>

<table style="max-width:560px; margin-left:24px;">
    <thead><tr><th>ID</th><th>Chat ID</th><th>Nomi</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($channels as $c): ?>
        <tr>
            <td>#<?= $c['id'] ?></td>
            <td><?= htmlspecialchars($c['chat_id']) ?></td>
            <td><?= htmlspecialchars($c['title'] ?? '-') ?></td>
            <td>
                <form method="post" onsubmit="return confirm('O\'chirilsinmi?');">
                    <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn-small danger">O'chirish</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$channels): ?>
        <tr><td colspan="4" style="text-align:center;">Kanal qo'shilmagan (majburiy obuna o'chirilgan holatda)</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
