<?php
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    if ($role === 'admin') { header('Location: admin_dashboard.php'); exit; }
    if ($role === 'facilitator') { header('Location: facilitator_dashboard.php'); exit; }
    header('Location: student_dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role, profile_image FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'] ?? '';
                $_SESSION['last_activity'] = time();

                if ($user['role'] === 'admin') { header('Location: admin_dashboard.php'); exit; }
                if ($user['role'] === 'facilitator') { header('Location: facilitator_dashboard.php'); exit; }
                header('Location: student_dashboard.php'); exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}
?>
<?php include 'includes/header.php'; ?>

<section class="d-flex align-items-center justify-content-center py-4 py-md-5" style="min-height: calc(100vh - 280px); background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
          <div class="text-center text-white py-4" style="background: linear-gradient(135deg, #0b6623, #084919);">
            <?php if (!empty($logoSrc)): ?>
              <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Kibiya Hub Logo" class="rounded-circle shadow mb-2" style="width: 64px; height: 64px; object-fit: cover; border: 3px solid rgba(255,255,255,0.9); background: #ffffff;">
            <?php else: ?>
              <i class="fa-solid fa-user-circle fa-3x mb-2 opacity-75"></i>
            <?php endif; ?>
            <h4 class="fw-bold mb-0">Welcome Back</h4>
            <small class="opacity-75">Sign in to your Kibiya Hub account</small>
          </div>
          <div class="card-body p-4">
            <?php if (isset($_GET['logout'])): ?>
              <div class="alert alert-success py-2 small mb-3 border-0 bg-success bg-opacity-25 text-dark rounded-3 d-flex align-items-center">
                <i class="fa-solid fa-circle-check me-2 text-success"></i>
                <div><strong>Logged Out:</strong> You have been successfully logged out of your session.</div>
              </div>
            <?php endif; ?>
            <?php if (isset($_GET['timeout'])): ?>
              <div class="alert alert-warning py-2 small mb-3 border-0 bg-warning bg-opacity-25 text-dark rounded-3 d-flex align-items-center">
                <i class="fa-solid fa-clock-rotate-left me-2 text-warning fs-5"></i>
                <div><strong>Session Expired:</strong> You were automatically logged out due to 5 minutes of inactivity for security reasons. Please log in again to continue.</div>
              </div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="alert alert-danger py-2 small"><i class="fa-solid fa-circle-exclamation me-1"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="POST" novalidate>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Email Address</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                  <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <label class="form-label fw-semibold small mb-0">Password</label>
                  <a href="forgot_password.php" class="small text-hub text-decoration-none" style="font-size:0.8rem;">
                    <i class="fa-solid fa-circle-question me-1"></i>Forgot password?
                  </a>
                </div>
                <div class="input-group mt-1">
                  <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                  <input type="password" name="password" id="loginPassword" class="form-control border-start-0 ps-0 border-end-0" placeholder="Enter your password" required>
                  <button class="btn btn-outline-secondary border-start-0" type="button"
                          onclick="const i=document.getElementById('loginPassword');const ic=this.querySelector('i');if(i.type==='password'){i.type='text';ic.classList.replace('fa-eye','fa-eye-slash');}else{i.type='password';ic.classList.replace('fa-eye-slash','fa-eye');}">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="mb-4">
                <button type="submit" class="btn btn-hub w-100 py-2 fw-bold">
                  <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                </button>
              </div>
            </form>
            <div class="text-center">
              <small class="text-muted">Don't have an account? <a href="register.php" class="text-hub fw-bold text-decoration-none">Register here</a></small>
            </div>
            <div class="text-center mt-2">
              <a href="forgot_password.php" class="small text-muted text-decoration-none">
                <i class="fa-solid fa-key me-1"></i>Forgot your username or password?
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
