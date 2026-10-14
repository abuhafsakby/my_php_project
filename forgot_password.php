<?php
/**
 * forgot_password.php
 * Allows users to recover their account by:
 *   1. Recovering their username (full name) via email lookup
 *   2. Sending a password reset link to their registered email
 */
require_once 'config/db.php';

// Redirect logged-in users to settings
if (isset($_SESSION['user_id'])) {
    header('Location: settings.php');
    exit;
}

// Ensure password_resets table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_token (token),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

$step        = 'form';   // form | sent | username_found
$successMsg  = '';
$errorMsg    = '';
$usernameMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email  = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please enter a valid email address.';
    } else {
        // Fetch user by email
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // ── Recover Username ─────────────────────────────
        if ($action === 'recover_username') {
            if ($found) {
                // We reveal name safely — never reveal password/role info
                $maskedName  = maskName($found['full_name']);
                $usernameMsg = $maskedName;
                $step        = 'username_found';
            } else {
                // Generic message — don't reveal if email exists
                $step        = 'username_found';
                $usernameMsg = null; // will show generic "check your email" info
            }
        }

        // ── Reset Password ───────────────────────────────
        if ($action === 'reset_password') {
            if ($found) {
                // Invalidate old tokens for this user
                $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $del->bind_param("i", $found['id']);
                $del->execute();
                $del->close();

                // Generate secure token
                $token     = bin2hex(random_bytes(48));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $ins->bind_param("iss", $found['id'], $token, $expiresAt);
                $ins->execute();
                $ins->close();

                // ── Email sending ──────────────────────────
                // In production use PHPMailer/SMTP. Here we use mail() as a fallback.
                $resetLink  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                              . '/kibiya_lms_project/reset_password.php?token=' . urlencode($token);

                $to      = $found['email'];
                $subject = 'Password Reset – Kibiya Hub LMS';
                $body    = "Hello " . $found['full_name'] . ",\n\n"
                         . "We received a request to reset your Kibiya Hub LMS password.\n"
                         . "Click the link below to reset your password (expires in 1 hour):\n\n"
                         . $resetLink . "\n\n"
                         . "If you did not request a reset, please ignore this email.\n\n"
                         . "— Kibiya Technology & Innovation Hub";
                $headers = "From: no-reply@kibiyahub.edu.ng\r\nX-Mailer: PHP/" . phpversion();

                @mail($to, $subject, $body, $headers);
            }
            // Always show the same message — security best practice
            $step = 'sent';
        }
    }
}

/**
 * Masks a full name, e.g. "Umar Bello" → "U*** B***"
 */
function maskName(string $name): string {
    $parts = explode(' ', $name);
    return implode(' ', array_map(function($part) {
        if (strlen($part) <= 1) return $part;
        return strtoupper($part[0]) . str_repeat('*', min(strlen($part) - 1, 4));
    }, $parts));
}

include 'includes/header.php';
?>

<style>
  .forgot-section {
    min-height: calc(100vh - 280px);
    background: linear-gradient(135deg, #f0fdf4 0%, #e9ecef 100%);
    display: flex;
    align-items: center;
  }
  .forgot-card {
    border: none;
    border-radius: 24px;
    box-shadow: 0 8px 40px rgba(11,102,35,0.12);
    overflow: hidden;
  }
  .forgot-card-header {
    background: linear-gradient(135deg, #0b6623, #084919);
    color: #fff;
    padding: 2rem 2rem 1.5rem;
    text-align: center;
  }
  .tab-btn {
    border: none;
    background: none;
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
    transition: all 0.2s;
    font-size: 0.9rem;
  }
  .tab-btn.active {
    background: rgba(255,255,255,0.2);
    color: #fff;
  }
  .tab-btn:hover { color: #fff; }
  .form-control:focus {
    border-color: #0b6623;
    box-shadow: 0 0 0 0.2rem rgba(11,102,35,0.15);
  }
  .success-icon { font-size: 3.5rem; color: #0b6623; }
  .info-icon    { font-size: 3.5rem; color: #f4b41a; }
  .divider-text {
    position: relative; text-align: center; color: #adb5bd; font-size: 0.8rem;
  }
  .divider-text::before, .divider-text::after {
    content: ''; position: absolute; top: 50%;
    width: 40%; height: 1px; background: #dee2e6;
  }
  .divider-text::before { left: 0; }
  .divider-text::after  { right: 0; }
</style>

<section class="forgot-section py-4 py-md-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

        <?php if ($step === 'sent'): ?>
        <!-- ── Success: Reset link sent ───────────────── -->
        <div class="forgot-card text-center p-5">
          <div class="success-icon mb-3"><i class="fa-solid fa-paper-plane"></i></div>
          <h4 class="fw-bold text-hub mb-2">Reset Link Sent!</h4>
          <p class="text-muted mb-4">
            If an account with that email exists, we've sent a password reset link.
            Please check your inbox (and spam folder) — the link expires in <strong>1 hour</strong>.
          </p>
          <a href="login.php" class="btn btn-hub w-100 fw-bold py-2">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Back to Login
          </a>
          <div class="mt-3">
            <a href="forgot_password.php" class="small text-hub text-decoration-none">
              <i class="fa-solid fa-rotate-right me-1"></i>Try again
            </a>
          </div>
        </div>

        <?php elseif ($step === 'username_found'): ?>
        <!-- ── Username Recovery Result ───────────────── -->
        <div class="forgot-card text-center p-5">
          <?php if ($usernameMsg): ?>
            <div class="success-icon mb-3"><i class="fa-solid fa-user-check"></i></div>
            <h4 class="fw-bold text-hub mb-2">Account Found!</h4>
            <p class="text-muted mb-1">The registered name on that account is:</p>
            <div class="alert alert-success fw-bold fs-5 my-3 border-0 rounded-3">
              <?php echo htmlspecialchars($usernameMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <p class="text-muted small mb-4">Use your full name along with your email to identify your account.</p>
          <?php else: ?>
            <div class="info-icon mb-3"><i class="fa-solid fa-circle-info"></i></div>
            <h4 class="fw-bold mb-2">Check Your Email</h4>
            <p class="text-muted mb-4">If an account exists with that email, your registered name has been sent to it.</p>
          <?php endif; ?>
          <a href="login.php" class="btn btn-hub w-100 fw-bold py-2 mb-2">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Go to Login
          </a>
          <a href="forgot_password.php" class="btn btn-outline-secondary w-100 fw-semibold">
            <i class="fa-solid fa-rotate-right me-1"></i>Try Again
          </a>
        </div>

        <?php else: ?>
        <!-- ── Form ───────────────────────────────────── -->
        <div class="forgot-card">
          <div class="forgot-card-header">
            <div class="mb-3">
              <?php if (!empty($logoSrc)): ?>
                <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="Kibiya Hub" class="rounded-circle" style="width:56px;height:56px;object-fit:cover;border:3px solid rgba(255,255,255,0.9);">
              <?php else: ?>
                <i class="fa-solid fa-circle-question fa-2x opacity-75"></i>
              <?php endif; ?>
            </div>
            <h5 class="fw-bold mb-1">Account Recovery</h5>
            <p class="opacity-75 small mb-3">Recover your password or find your username</p>

            <!-- Mode tabs -->
            <div class="d-flex justify-content-center gap-2">
              <button class="tab-btn active" id="tabResetBtn" onclick="switchTab('reset')">
                <i class="fa-solid fa-key me-1"></i>Reset Password
              </button>
              <button class="tab-btn" id="tabUsernameBtn" onclick="switchTab('username')">
                <i class="fa-solid fa-user me-1"></i>Find Username
              </button>
            </div>
          </div>

          <div class="card-body p-4">
            <?php if ($errorMsg): ?>
              <div class="alert alert-danger border-0 rounded-3 small d-flex align-items-center gap-2 mb-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>

            <!-- Reset Password Form -->
            <div id="formReset">
              <p class="text-muted small mb-3">
                <i class="fa-solid fa-circle-info me-1"></i>
                Enter your registered email and we'll send you a secure reset link.
              </p>
              <form method="POST" novalidate>
                <input type="hidden" name="action" value="reset_password">
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="you@example.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                </div>
                <button type="submit" class="btn btn-hub w-100 py-2 fw-bold">
                  <i class="fa-solid fa-paper-plane me-2"></i>Send Reset Link
                </button>
              </form>
            </div>

            <!-- Recover Username Form -->
            <div id="formUsername" style="display:none;">
              <p class="text-muted small mb-3">
                <i class="fa-solid fa-circle-info me-1"></i>
                Enter your registered email and we'll show your account name.
              </p>
              <form method="POST" novalidate>
                <input type="hidden" name="action" value="recover_username">
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="you@example.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                </div>
                <button type="submit" class="btn fw-bold w-100 py-2"
                        style="background:linear-gradient(135deg,#f4b41a,#e6a800);color:#fff;">
                  <i class="fa-solid fa-user-magnifying-glass me-2"></i>Find My Username
                </button>
              </form>
            </div>

            <div class="divider-text my-3">or</div>
            <div class="text-center">
              <a href="login.php" class="small text-hub fw-semibold text-decoration-none">
                <i class="fa-solid fa-right-to-bracket me-1"></i>Back to Login
              </a>
              &nbsp;·&nbsp;
              <a href="register.php" class="small text-muted text-decoration-none">
                <i class="fa-solid fa-user-plus me-1"></i>Register
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<script>
  function switchTab(mode) {
    const isReset = mode === 'reset';
    document.getElementById('formReset').style.display    = isReset ? '' : 'none';
    document.getElementById('formUsername').style.display = isReset ? 'none' : '';
    document.getElementById('tabResetBtn').classList.toggle('active', isReset);
    document.getElementById('tabUsernameBtn').classList.toggle('active', !isReset);
  }

  <?php if (($_POST['action'] ?? '') === 'recover_username'): ?>
    // Restore tab state on reload
    switchTab('username');
  <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
