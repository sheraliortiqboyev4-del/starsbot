<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$activePage = 'topups';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_id'])) {
        approve_card_topup((int) $_POST['approve_id']);
    } elseif (isset($_POST['reject_id'])) {
        reject_card_topup((int) $_POST['reject_id'], trim($_POST['reason'] ?? ''));
    }
    header('Location: topups.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$statusFilter = $_GET['status'] ?? 'pending';
$pdo = db();
$sql = "SELECT t.*, u.username, u.telegram_id FROM topups t JOIN users u ON u.id = t.user_id WHERE t.method = 'card'";
$params = [];
if ($statusFilter) {
    $sql .= ' AND t.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY t.id DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topups = $stmt->fetchAll();

$statusLabels = ['pending' => '⏳ Kutilmoqda', 'paid' => '✅ Tasdiqlandi', 'cancelled' => '❌ Rad etildi'];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>To'lov so'rovlari — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="filters">
    <a href="?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">⏳ Kutilmoqda</a>
    <a href="?status=paid" class="<?= $statusFilter === 'paid' ? 'active' : '' ?>">✅ Tasdiqlangan</a>
    <a href="?status=cancelled" class="<?= $statusFilter === 'cancelled' ? 'active' : '' ?>">❌ Rad etilgan</a>
    <a href="?status=" class="<?= $statusFilter === '' ? 'active' : '' ?>">Hammasi</a>
</div>

<div class="topup-grid">
    <?php foreach ($topups as $t): ?>
        <div class="topup-card">
            <div class="topup-card-head">
                <b>#<?= $t['id'] ?> — <?= number_format($t['amount'], 0, '.', ' ') ?> so'm</b>
                <span class="status-<?= $t['status'] ?>"><?= $statusLabels[$t['status']] ?></span>
            </div>
            <p>👤 @<?= htmlspecialchars($t['username'] ?? '-') ?> (ID: <?= $t['telegram_id'] ?>)</p>
            <p class="muted"><?= $t['created_at'] ?></p>
            <?php if ($t['proof_file_id']): ?>
                <img src="photo_proxy.php?file_id=<?= urlencode($t['proof_file_id']) ?>" class="proof-img" loading="lazy" alt="chek">
            <?php else: ?>
                <p class="muted">Chek hali yuborilmagan</p>
            <?php endif; ?>

            <?php if ($t['status'] === 'pending'): ?>
                <div class="topup-actions">
                    <form method="post" onsubmit="return confirm('To\'lov tasdiqlansinmi? Foydalanuvchi balansiga pul qo\'shiladi.');">
                        <input type="hidden" name="approve_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn-small">✅ Tasdiqlash</button>
                    </form>
                    <form method="post" onsubmit="return confirm('To\'lov rad etilsinmi?');">
                        <input type="hidden" name="reject_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn-small danger">❌ Rad etish</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$topups): ?>
        <p style="padding:0 24px;">So'rovlar topilmadi</p>
    <?php endif; ?>
</div>
</body>
</html>
