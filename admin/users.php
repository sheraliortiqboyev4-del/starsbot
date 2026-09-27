<?php
require_once __DIR__ . '/_auth.php';
$activePage = 'users';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_user_id'])) {
    $userId = (int) $_POST['adjust_user_id'];
    $amount = (float) $_POST['adjust_amount'];
    if ($amount !== 0.0) {
        $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([$amount, $userId]);
    }
    header('Location: users.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM users';
$params = [];
if ($search) {
    $sql .= ' WHERE username LIKE ? OR telegram_id LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY id DESC LIMIT 150';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Foydalanuvchilar — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="panel">
    <form method="get" style="display:flex; gap:8px;">
        <input type="text" name="q" placeholder="Username yoki Telegram ID bo'yicha qidirish..." value="<?= htmlspecialchars($search) ?>" style="flex:1; padding:8px; border-radius:6px; border:1px solid #ddd;">
        <button type="submit" class="btn-small">Qidirish</button>
    </form>
</div>

<table>
    <thead>
    <tr><th>ID</th><th>Username</th><th>Telegram ID</th><th>Balans</th><th>Ro'yxatdan o'tgan</th><th>Balansni tuzatish</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td>#<?= $u['id'] ?></td>
            <td>@<?= htmlspecialchars($u['username'] ?? '-') ?> (<?= htmlspecialchars($u['first_name'] ?? '') ?>)</td>
            <td><?= $u['telegram_id'] ?></td>
            <td><b><?= number_format($u['balance'], 0, '.', ' ') ?> so'm</b></td>
            <td><?= $u['created_at'] ?></td>
            <td>
                <form method="post" style="display:flex; gap:4px;">
                    <input type="hidden" name="adjust_user_id" value="<?= $u['id'] ?>">
                    <input type="number" name="adjust_amount" placeholder="+/- summa" style="width:110px; padding:5px; border-radius:5px; border:1px solid #ddd;">
                    <button type="submit" class="btn-small">Qo'shish</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?>
        <tr><td colspan="6" style="text-align:center;">Foydalanuvchilar topilmadi</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
