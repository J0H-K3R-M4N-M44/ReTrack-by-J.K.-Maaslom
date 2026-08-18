<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$hardcoded_username = 'admin';
$hardcoded_password = '123';

if ($username === $hardcoded_username && $password === $hardcoded_password) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_active'] = time();
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php?error=1');
exit;
