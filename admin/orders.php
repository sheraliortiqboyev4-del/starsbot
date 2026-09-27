<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$activePage = 'orders';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order_id'])) {
    mark_order_completed_manually((int) $_POST['complete_order_id']);
    header('Location: orders.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT o.*, u.username, u.telegram_id FROM orders o JOIN users u ON u.id = o.user_id';
$params = [];
if ($statusFilter) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY o.id DESC LIMIT 150';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusLabels = [
    'completed' => '✅ Bajarildi',
    'awaiting_manual' => '⏳ Qo\'lda kutilmoqda',
    'failed' => '❌ Xato',
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Buyurtmalar — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="filters">
    <a href="orders.php" class="<?= $statusFilter === '' ? 'active' : '' ?>">Hammasi</a>
    <?php foreach ($statusLabels as $key => $label): ?>
        <a href="?status=<?= $key ?>" class="<?= $statusFilter === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<table>
    <thead>
    <tr>
        <th>#</th><th>Foydalanuvchi</th><th>Turi</th><th>Qabul qiluvchi</th>
        <th>Miqdor</th><th>Narx</th><th>Holat</th><th>Sana</th><th>Amal</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td>#<?= $o['id'] ?></td>
            <td>@<?= htmlspecialchars($o['username'] ?? '-') ?><br><small><?= $o['telegram_id'] ?></small></td>
            <td><?= $o['type'] === 'stars' ? '⭐ Stars' : '💎 Premium' ?></td>
            <td>@<?= htmlspecialchars($o['target_username']) ?></td>
            <td><?= $o['amount'] ?><?= $o['type'] === 'premium' ? ' oy' : '' ?></td>
            <td><?= number_format($o['price'], 0, '.', ' ') ?> so'm</td>
            <td class="status-<?= $o['status'] ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></td>
            <td><?= $o['created_at'] ?></td>
            <td>
                <?php if ($o['status'] === 'awaiting_manual'): ?>
                    <form method="post" onsubmit="return confirm('Bu buyurtma qo\'lda yetkazib berildi deb belgilanadi. Davom etilsinmi?');">
                        <input type="hidden" name="complete_order_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="btn-small">✅ Bajarildi</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
        <tr><td colspan="9" style="text-align:center;">Buyurtmalar topilmadi</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
