<?php
/** @var string $activePage */
?>
<div class="topbar">
    <h1>⭐ Stars Shop — Admin</h1>
    <nav>
        <a href="index.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">📊 Statistika</a>
        <a href="orders.php" class="<?= $activePage === 'orders' ? 'active' : '' ?>">📦 Buyurtmalar</a>
        <a href="topups.php" class="<?= $activePage === 'topups' ? 'active' : '' ?>">💳 To'lov so'rovlari</a>
        <a href="users.php" class="<?= $activePage === 'users' ? 'active' : '' ?>">👥 Foydalanuvchilar</a>
        <a href="settings.php" class="<?= $activePage === 'settings' ? 'active' : '' ?>">⚙️ Narxlar</a>
        <a href="channels.php" class="<?= $activePage === 'channels' ? 'active' : '' ?>">📢 Kanallar</a>
        <a href="broadcast.php" class="<?= $activePage === 'broadcast' ? 'active' : '' ?>">📣 Xabar yuborish</a>
    </nav>
    <a href="logout.php" class="logout">Chiqish</a>
</div>
