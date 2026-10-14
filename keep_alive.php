<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'unauthenticated',
        'message' => 'User session has expired or is invalid.'
    ]);
    exit;
}

// Refresh session last activity timestamp
$_SESSION['last_activity'] = time();

$role = $_SESSION['role'] ?? 'student';
$timeout = defined('SESSION_IDLE_TIMEOUT') ? SESSION_IDLE_TIMEOUT : 300;

echo json_encode([
    'status' => 'success',
    'message' => 'Session activity successfully refreshed.',
    'role' => $role,
    'last_activity' => $_SESSION['last_activity'],
    'timeout' => $timeout
]);
exit;
