<?php
require_once 'config/db.php';

$redirectParam = isset($_GET['timeout']) ? '?timeout=1' : '?logout=1';

// Clear all session variables
$_SESSION = [];
session_unset();

// Delete session cookie
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

// Destroy session data on server
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: login.php' . $redirectParam);
exit;

