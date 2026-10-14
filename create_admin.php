<?php
/**
 * One-time admin account creator.
 * Visit: http://localhost/kibiya_lms_project/create_admin.php
 * Creates the default admin then DELETE this file for security.
 */
require_once 'config/db.php';

$adminEmail = 'admin@kibiyahub.org';
$adminPassword = 'password123'; // Change this!
$adminName = 'Hub Administrator';

// Check if admin already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $adminEmail);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo "<h2>Admin account already exists.</h2>";
    echo "<p>Email: <strong>$adminEmail</strong></p>";
    echo "<p>You can <a href='login.php'>login here</a>.</p>";
} else {
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->bind_param("sss", $adminName, $adminEmail, $hash);
    $stmt->execute();
    $stmt->close();

    echo "<h2 style='color:green;'>✅ Admin account created successfully!</h2>";
    echo "<p><strong>Email:</strong> $adminEmail</p>";
    echo "<p><strong>Password:</strong> $adminPassword</p>";
    echo "<p>⚠️ <strong>DELETE this file (create_admin.php) after use for security.</strong></p>";
    echo "<p><a href='login.php'>Go to Login →</a></p>";
}
$check->close();
