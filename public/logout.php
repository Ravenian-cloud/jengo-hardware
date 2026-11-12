<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/remember_me.php';

$rememberRevocationFailed = false;
try {
    require_once __DIR__ . '/../config/db.php';
    forgetRememberMeToken($pdo);
} catch (\PDOException $e) {
    error_log('Unable to revoke remember-me token during logout: ' . $e->getMessage());
    clearRememberMeCookie();
    $rememberRevocationFailed = true;
}

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php' . ($rememberRevocationFailed ? '?remember_logout_failed=1' : ''));
exit;