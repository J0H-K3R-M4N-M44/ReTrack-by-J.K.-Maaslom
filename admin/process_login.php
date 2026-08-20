<?php
// ========================================
// LOGIN AUTHENTICATION HANDLER
// ========================================

session_start();

// Reject non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Verify the login form came from this session.
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (
    $session_token === '' ||
    $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)
) {
    header('Location: login.php?error=1');
    exit;
}

// ========================================
// Get & Parse Input
// ========================================

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// ========================================
// Demo Credentials (Hardcoded)
// ========================================

$hardcoded_username = 'admin';
$hardcoded_password = '123';

// ========================================
// Validate Credentials
// ========================================

if ($username === $hardcoded_username && $password === $hardcoded_password) {
    // Valid login - create authenticated session
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_active'] = time();
    
    header('Location: dashboard.php');
    exit;
}

// Invalid login - redirect with error
header('Location: login.php?error=1');
exit;
