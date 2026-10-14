<?php
/**
 * upload_profile_picture.php
 * Shared endpoint for all roles (student, facilitator, admin) to upload a profile picture.
 * Accepts a POST with file input name "profile_picture".
 * Stores the image under uploads/profiles/ and updates the users table.
 */
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'student';

// Determine redirect target based on role
$redirectMap = [
    'admin'       => 'admin_dashboard.php',
    'facilitator' => 'facilitator_dashboard.php',
    'student'     => 'student_dashboard.php',
];
$redirect = $redirectMap[$role] ?? 'student_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['profile_picture']['name'])) {
    header("Location: $redirect");
    exit;
}

$file = $_FILES['profile_picture'];
$error = '';

// Validate upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error = 'Upload error (code: ' . $file['error'] . ')';
} elseif ($file['size'] > 5 * 1024 * 1024) {
    $error = 'Profile picture must be under 5 MB.';
} else {
    $allowedTypes = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $error = 'Allowed formats: JPG, PNG, WEBP, GIF.';
    }
}

if ($error !== '') {
    $_SESSION['profile_msg'] = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</div>";
    header("Location: $redirect");
    exit;
}

// Create profiles upload directory
$uploadDir = __DIR__ . '/uploads/profiles';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    $_SESSION['profile_msg'] = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not create upload directory.</div>";
    header("Location: $redirect");
    exit;
}

// Delete old profile picture if it exists
$oldStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$oldStmt->bind_param("i", $userId);
$oldStmt->execute();
$oldResult = $oldStmt->get_result()->fetch_assoc();
$oldStmt->close();
if (!empty($oldResult['profile_image'])) {
    $oldPath = __DIR__ . '/' . $oldResult['profile_image'];
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

// Save new file
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeName = 'profile_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    $_SESSION['profile_msg'] = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not save image file.</div>";
    header("Location: $redirect");
    exit;
}

$dbPath = 'uploads/profiles/' . $safeName;

// Update database
$stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
$stmt->bind_param("si", $dbPath, $userId);

if ($stmt->execute()) {
    // Update session so header reflects immediately
    $_SESSION['profile_image'] = $dbPath;
    $_SESSION['profile_msg'] = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Profile picture updated successfully!</div>";
} else {
    $_SESSION['profile_msg'] = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Database update failed.</div>";
}
$stmt->close();

header("Location: $redirect");
exit;
