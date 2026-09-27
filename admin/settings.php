<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/settings.php';
$activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_setting('price_per_star', (string) max(0, (float) $_POST['price_per_star']));
    set_setting('premium_3_price', (string) max(0, (float) $_POST['premium_3_price']));
    set_setting('premium_6_price', (string) max(0, (float) $_POST['premium_6_price']));
    set_setting('premium_12_price', (string) max(0, (float) $_POST['premium_12_price']));
    set_setting('method_card_enabled', isset($_POST['method_card_enabled']) ? '1' : '0');
    set_setting('method_click_enabled', isset($_POST['method_click_enabled']) ? '1' : '0');
    set_setting('method_payme_enabled', isset($_POST['method_payme_enabled']) ? '1' : '0');
    set_setting('card_number', trim($_POST['card_number']));
    set_setting('card_holder', trim($_POST['card_holder']));
    header('Location: settings.php?saved=1');
    exit;
}
$saved = isset($_GET['saved']);

$pps = price_per_star();
$prem = premium_prices();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Sozlamalar — Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>

<div class="panel" style="max-width:480px;">
    <h3>💰 Narxlarni tahrirlash</h3>
    <?php if ($saved): ?><p class="success">✅ Saqlandi!</p><?php endif; ?>

    <form method="post" class="form">
        <label>1 dona Stars narxi (so'm)</label>
        <input type="number" step="0.01" name="price_per_star" value="<?= htmlspecialchars($pps) ?>" required>

        <label>Premium 3 oy narxi (so'm)</label>
        <input type="number" step="0.01" name="premium_3_price" value="<?= htmlspecialchars($prem[3]) ?>" required>

        <label>Premium 6 oy narxi (so'm)</label>
        <input type="number" step="0.01" name="premium_6_price" value="<?= htmlspecialchars($prem[6]) ?>" required>

        <label>Premium 12 oy narxi (so'm)</label>
        <input type="number" step="0.01" name="premium_12_price" value="<?= htmlspecialchars($prem[12]) ?>" required>

        <hr style="border:none; border-top:1px solid #eee; margin:6px 0;">
        <h4 style="margin:0 0 4px;">💳 To'lov usullari</h4>

        <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
            <input type="checkbox" name="method_card_enabled" style="width:auto;" <?= is_method_enabled('card') ? 'checked' : '' ?>>
            Karta orqali qo'lda (UzCard/Humo) — admin tasdiqlaydi
        </label>
        <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
            <input type="checkbox" name="method_click_enabled" style="width:auto;" <?= is_method_enabled('click') ? 'checked' : '' ?>>
            Click — avtomatik (YaTT/merchant kerak)
        </label>
        <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
            <input type="checkbox" name="method_payme_enabled" style="width:auto;" <?= is_method_enabled('payme') ? 'checked' : '' ?>>
            Payme — avtomatik (YaTT/merchant kerak)
        </label>

        <label>Karta raqami (foydalanuvchiga ko'rsatiladi)</label>
        <input type="text" name="card_number" value="<?= htmlspecialchars(card_number()) ?>" placeholder="8600 1234 5678 9012">

        <label>Karta egasi F.I.Sh.</label>
        <input type="text" name="card_holder" value="<?= htmlspecialchars(card_holder()) ?>" placeholder="Aziz Azizov">

        <button type="submit" class="btn-primary">💾 Saqlash</button>
    </form>
    <p class="hint-text">Narxlar va to'lov usullari saqlangach, bot ichida darhol ishlaydi — kod yoki serverni qayta ishga tushirish shart emas.</p>
</div>
</body>
</html>
