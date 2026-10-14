<?php
/**
 * reset_password.php
 * Validates a password reset token and lets the user choose a new password.
 */
require_once 'config/db.php';

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    header('Location: settings.php');
    exit;
}

$token      = trim($_GET['token'] ?? '');
$errorMsg   = '';
$successMsg = '';
$tokenValid = false;
$tokenRow   = null;

if ($token === '') {
    $errorMsg = 'Invalid or missing reset token.';
} else {
    // Look up the token
    $stmt = $conn->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email, u.full_name
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $tokenRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tokenRow) {
        $errorMsg = 'This reset link is invalid.';
    } elseif ($tokenRow['used']) {
        $errorMsg = 'This reset link has already been used. Please request a new one.';
    } elseif (strtotime($tokenRow['expires_at']) < time()) {
        $errorMsg = 'This reset link has expired (valid for 1 hour). Please request a new one.';
    } else {
        $tokenValid = true;
    }
}

// Handle form submission
if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (strlen($newPw) < 8) {
        $errorMsg   = 'Password must be at least 8 characters.';
        $tokenValid = true; // keep form visible
    } elseif ($newPw !== $confirmPw) {
        $errorMsg   = 'Passwords do not match.';
        $tokenValid = true;
    } else {
        $hash = password_hash($newPw, PASSWORD_DEFAULT);

        // Update password
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hash, $tokenRow['user_id']);
        $upd->execute();
        $upd->close();

        // Mark token as used
        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $mark->bind_param("i", $tokenRow['id']);
        $mark->execute();
        $mark->close();

        $successMsg = 'Password reset successfully! You can now log in with your new password.';
        $tokenValid = false;
    }
}

include 'includes/header.php';
?>

<style>
  .reset-section {
    min-height: calc(100vh - 280px);
    background: linear-gradient(135deg, #f0fdf4 0%, #e9ecef 100%);
    display: flex;
    align-items: center;
  }
  .reset-card {
    border: none;
    border-radius: 24px;
    box-shadow: 0 8px 40px rgba(11,102,35,0.12);
    overflow: hidden;
  }
  .reset-card-header {
    background: linear-gradient(135deg, #0b6623, #084919);
    color: #fff;
    padding: 2rem;
    text-align: center;
  }
  .form-control:focus {
    border-color: #0b6623;
    box-shadow: 0 0 0 0.2rem rgba(11,102,35,0.15);
  }
  .strength-bar { height: 5px; border-radius: 4px; transition: width 0.3s, background 0.3s; }
</style>

<section class="reset-section py-4 py-md-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

        <?php if ($successMsg): ?>
        <!-- ── Success ─────────────────────── -->
        <div class="reset-card text-center p-5">
          <i class="fa-solid fa-circle-check fa-3x text-success mb-3"></i>
          <h4 class="fw-bold text-hub mb-2">Password Reset!</h4>
          <p class="text-muted mb-4"><?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?></p>
          <a href="login.php" class="btn btn-hub w-100 py-2 fw-bold">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Login Now
          </a>
        </div>

        <?php elseif ($errorMsg && !$tokenValid): ?>
        <!-- ── Error / Expired ─────────────── -->
        <div class="reset-card text-center p-5">
          <i class="fa-solid fa-triangle-exclamation fa-3x text-warning mb-3"></i>
          <h4 class="fw-bold mb-2">Link Invalid</h4>
          <p class="text-muted mb-4"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></p>
          <a href="forgot_password.php" class="btn btn-hub w-100 py-2 fw-bold mb-2">
            <i class="fa-solid fa-rotate-right me-2"></i>Request New Link
          </a>
          <a href="login.php" class="btn btn-outline-secondary w-100 fw-semibold">
            <i class="fa-solid fa-right-to-bracket me-1"></i>Back to Login
          </a>
        </div>

        <?php else: ?>
        <!-- ── Reset Form ──────────────────── -->
        <div class="reset-card">
          <div class="reset-card-header">
            <?php if (!empty($logoSrc)): ?>
              <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>"
                   alt="Kibiya Hub" class="rounded-circle mb-2"
                   style="width:56px;height:56px;object-fit:cover;border:3px solid rgba(255,255,255,0.9);">
            <?php else: ?>
              <i class="fa-solid fa-key fa-2x mb-2 opacity-75"></i>
            <?php endif; ?>
            <h5 class="fw-bold mb-1">Set New Password</h5>
            <p class="opacity-75 small mb-0">
              For <strong><?php echo htmlspecialchars($tokenRow['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
            </p>
          </div>

          <div class="card-body p-4">
            <?php if ($errorMsg): ?>
              <div class="alert alert-danger border-0 rounded-3 small d-flex align-items-center gap-2 mb-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>

            <form method="POST" novalidate>
              <div class="mb-3">
                <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
                  <input type="password" name="new_password" id="newPw" class="form-control"
                         placeholder="Min. 8 characters" required oninput="checkStrength(this.value)">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePw('newPw', this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
                <!-- Strength indicator -->
                <div class="mt-2">
                  <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Password Strength</small>
                    <small id="strengthLabel" class="fw-semibold text-muted">—</small>
                  </div>
                  <div class="bg-light rounded" style="height:5px;">
                    <div id="strengthBar" class="strength-bar bg-danger" style="width:0%;"></div>
                  </div>
                </div>
              </div>

              <div class="mb-4">
                <label class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="fa-solid fa-shield-halved text-muted"></i></span>
                  <input type="password" name="confirm_password" id="confirmPw" class="form-control"
                         placeholder="Repeat password" required oninput="checkMatch()">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePw('confirmPw', this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
                <div id="matchMsg" class="form-text mt-1"></div>
              </div>

              <button type="submit" class="btn btn-hub w-100 py-2 fw-bold">
                <i class="fa-solid fa-shield-halved me-2"></i>Reset My Password
              </button>
            </form>

            <div class="text-center mt-3">
              <a href="login.php" class="small text-muted text-decoration-none">
                <i class="fa-solid fa-right-to-bracket me-1"></i>Back to Login
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
  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    if (inp.type === 'password') {
      inp.type = 'text';
      ico.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      inp.type = 'password';
      ico.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }

  function checkStrength(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const configs = [
      { w: '0%',   c: '#dc3545', t: '—' },
      { w: '25%',  c: '#dc3545', t: 'Weak' },
      { w: '50%',  c: '#fd7e14', t: 'Fair' },
      { w: '75%',  c: '#ffc107', t: 'Good' },
      { w: '100%', c: '#0b6623', t: 'Strong' },
    ];
    const cfg = configs[score];
    bar.style.width      = cfg.w;
    bar.style.background = cfg.c;
    label.textContent    = cfg.t;
    label.style.color    = cfg.c;
  }

  function checkMatch() {
    const np  = document.getElementById('newPw').value;
    const cp  = document.getElementById('confirmPw').value;
    const msg = document.getElementById('matchMsg');
    if (!cp) { msg.textContent = ''; return; }
    msg.innerHTML = np === cp
      ? '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Passwords match!</span>'
      : '<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Passwords do not match.</span>';
  }
</script>

<?php include 'includes/footer.php'; ?>
