<?php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (hash_equals(ADMIN_PANEL_USERNAME, $username) && hash_equals(ADMIN_PANEL_PASSWORD, $password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    $error = "Login yoki parol noto'g'ri";
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Admin kirish</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <form method="post" class="login-box">
        <h2>🔐 Admin panel</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <input type="text" name="username" placeholder="Login" required autofocus>
        <input type="password" name="password" placeholder="Parol" required>
        <button type="submit">Kirish</button>
    </form>
</body>
</html>
