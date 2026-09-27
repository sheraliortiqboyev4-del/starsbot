<?php
require_once __DIR__ . '/_auth.php';
$activePage = 'dashboard';

$pdo = db();
$totalUsers = $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$totalOrders = $pdo->query('SELECT COUNT(*) c FROM orders')->fetch()['c'];
$completedOrders = $pdo->query("SELECT COUNT(*) c FROM orders WHERE status = 'completed'")->fetch()['c'];
$pendingOrders = $pdo->query("SELECT COUNT(*) c FROM orders WHERE status = 'awaiting_manual'")->fetch()['c'];
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(price),0) s FROM orders WHERE status = 'completed'")->fetch()['s'];
$totalTopups = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM topups WHERE status = 'paid'")->fetch()['s'];
$totalWalletBalance = $pdo->query("SELECT COALESCE(SUM(balance),0) s FROM users")->fetch()['s'];

$last7 = $pdo->query(
    "SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(price),0) s FROM orders
     WHERE status = 'completed' AND created_at >= NOW() - INTERVAL 7 DAY
     GROUP BY DATE(created_at) ORDER BY d"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Statistika — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="stats">
    <div class="stat-card"><span><?= $totalUsers ?></span>Foydalanuvchilar</div>
    <div class="stat-card"><span><?= $totalOrders ?></span>Jami buyurtmalar</div>
    <div class="stat-card"><span><?= $completedOrders ?></span>Bajarilgan</div>
    <div class="stat-card warn"><span><?= $pendingOrders ?></span>Kutilmoqda (qo'lda)</div>
    <div class="stat-card"><span><?= number_format($totalRevenue, 0, '.', ' ') ?></span>Sotuv tushumi (so'm)</div>
    <div class="stat-card"><span><?= number_format($totalTopups, 0, '.', ' ') ?></span>Jami to'ldirilgan (so'm)</div>
    <div class="stat-card"><span><?= number_format($totalWalletBalance, 0, '.', ' ') ?></span>Foydalanuvchilar balansi (so'm)</div>
</div>

<div class="panel">
    <h3>So'nggi 7 kunlik sotuvlar</h3>
    <table>
        <thead><tr><th>Sana</th><th>Buyurtmalar</th><th>Tushum</th></tr></thead>
        <tbody>
        <?php foreach ($last7 as $r): ?>
            <tr><td><?= $r['d'] ?></td><td><?= $r['c'] ?></td><td><?= number_format($r['s'], 0, '.', ' ') ?> so'm</td></tr>
        <?php endforeach; ?>
        <?php if (!$last7): ?><tr><td colspan="3" style="text-align:center;">Ma'lumot yo'q</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pendingOrders > 0): ?>
<div class="panel notice">
    ⚠️ <?= $pendingOrders ?> ta buyurtma qo'lda bajarilishi kutilmoqda.
    <a href="orders.php?status=awaiting_manual">Ko'rish →</a>
</div>
<?php endif; ?>

</body>
</html>
