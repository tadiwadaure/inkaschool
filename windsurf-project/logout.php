<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Logout the user
$auth->logout();

// This should never execute as logout() redirects, but just in case
header('Location: /login.php');
exit();
?>
