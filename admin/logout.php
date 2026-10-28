<?php
require_once __DIR__ . '/../app/auth.php';
startAppSession();
logoutAdmin();

header('Location: login.php');
exit();
