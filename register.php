<?php
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter your full name and a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists. <a href="login.php" class="fw-bold">Login instead</a>.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $phoneValue = ($phone !== '') ? $phone : null;
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, phone) VALUES (?, ?, ?, 'student', ?)");
            $stmt->bind_param("ssss", $name, $email, $hashedPassword, $phoneValue);
            try {
                $stmt->execute();
                $success = 'Registration successful! You can now <a href="login.php" class="fw-bold text-decoration-none">sign in</a>.';
            } catch (mysqli_sql_exception $e) {
                $error = 'Registration failed. Please try again.';
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}
?>
<?php include 'includes/header.php'; ?>

<section class="d-flex align-items-center justify-content-center py-4 py-md-5" style="min-height: calc(100vh - 280px); background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
          <div class="text-center text-white py-4" style="background: linear-gradient(135deg, #0b6623, #084919);">
            <i class="fa-solid fa-user-plus fa-3x mb-2 opacity-75"></i>
            <h4 class="fw-bold mb-0">Create Your Account</h4>
            <small class="opacity-75">Join Kibiya Hub's learning community</small>
          </div>
          <div class="card-body p-4">
            <?php if ($error): ?>
              <div class="alert alert-danger py-2 small"><i class="fa-solid fa-circle-exclamation me-1"></i><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
              <div class="alert alert-success py-2 small"><i class="fa-solid fa-check-circle me-1"></i><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST" novalidate>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Full Name</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                  <input type="text" name="full_name" class="form-control border-start-0 ps-0" placeholder="Enter your full name" required maxlength="100" value="<?php echo htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Email Address</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                  <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="you@example.com" required maxlength="100" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Phone Number <span class="text-muted">(optional)</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-phone text-muted"></i></span>
                  <input type="tel" name="phone" class="form-control border-start-0 ps-0" placeholder="+234 800 000 0000" maxlength="20" value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Password</label>
                  <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Confirm Password</label>
                  <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required minlength="8" autocomplete="new-password">
                </div>
              </div>
              <button type="submit" class="btn btn-hub w-100 py-2 fw-bold">
                <i class="fa-solid fa-user-check me-2"></i>Create Account
              </button>
            </form>
            <div class="text-center mt-3">
              <small class="text-muted">Already have an account? <a href="login.php" class="text-hub fw-bold text-decoration-none">Sign in</a></small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
