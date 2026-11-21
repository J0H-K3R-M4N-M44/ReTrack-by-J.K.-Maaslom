<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
startAppSession();
$user = requireAccount();
$guideRole = $user['role'];
require __DIR__ . '/../views/learning/center.php';
