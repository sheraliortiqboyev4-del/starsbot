<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
