<?php
/**
 * settings.php
 * Unified account settings page for all roles (admin, facilitator, student).
 * Handles: profile update (name, email, phone), password change, profile picture.
 */
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';

// Redirect map
$dashboardMap = [
    'admin'       => 'admin_dashboard.php',
    'facilitator' => 'facilitator_dashboard.php',
    'student'     => 'student_dashboard.php',
];
$dashboard = $dashboardMap[$userRole] ?? 'student_dashboard.php';

// Fetch current user data
$stmt = $conn->prepare("SELECT full_name, email, phone, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$successMsg = '';
$errorMsg   = '';

// ── Handle Profile Update ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $newName  = trim($_POST['full_name'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');

    if ($newName === '' || $newEmail === '') {
        $errorMsg = 'Full name and email are required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please enter a valid email address.';
    } else {
        // Check email uniqueness (excluding self)
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->bind_param("si", $newEmail, $userId);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errorMsg = 'That email address is already in use by another account.';
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $upd->bind_param("sssi", $newName, $newEmail, $newPhone, $userId);
            if ($upd->execute()) {
                $_SESSION['full_name'] = $newName;
                $_SESSION['email']     = $newEmail;
                $user['full_name']     = $newName;
                $user['email']         = $newEmail;
                $user['phone']         = $newPhone;
                $successMsg = 'Profile updated successfully!';
            } else {
                $errorMsg = 'Failed to update profile. Please try again.';
            }
            $upd->close();
        }
        $chk->close();
    }
}

// ── Handle Password Change ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPw  = $_POST['current_password'] ?? '';
    $newPw      = $_POST['new_password'] ?? '';
    $confirmPw  = $_POST['confirm_password'] ?? '';

    // Fetch current hash
    $phStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $phStmt->bind_param("i", $userId);
    $phStmt->execute();
    $phRow = $phStmt->get_result()->fetch_assoc();
    $phStmt->close();

    if (!password_verify($currentPw, $phRow['password'])) {
        $errorMsg = 'Current password is incorrect.';
    } elseif (strlen($newPw) < 8) {
        $errorMsg = 'New password must be at least 8 characters.';
    } elseif ($newPw !== $confirmPw) {
        $errorMsg = 'New passwords do not match.';
    } else {
        $hash   = password_hash($newPw, PASSWORD_DEFAULT);
        $pwUpd  = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pwUpd->bind_param("si", $hash, $userId);
        if ($pwUpd->execute()) {
            $successMsg = 'Password changed successfully!';
        } else {
            $errorMsg = 'Failed to change password. Please try again.';
        }
        $pwUpd->close();
    }
}

// Profile picture display
$profileImg       = $_SESSION['profile_image'] ?? '';
$profileImgExists = ($profileImg !== '' && file_exists(__DIR__ . '/' . $profileImg));

// Consume flash messages from profile upload
if (!empty($_SESSION['profile_msg'])) {
    $profileMsg = $_SESSION['profile_msg'];
    unset($_SESSION['profile_msg']);
}

// Role badge color
$roleBadge = ['admin' => 'danger', 'facilitator' => 'primary', 'student' => 'success'];
$badge     = $roleBadge[$userRole] ?? 'secondary';

include 'includes/header.php';
?>

<style>
  .settings-hero {
    background: linear-gradient(135deg, #0b6623 0%, #084919 60%, #f4b41a 100%);
    padding: 3rem 0 2.5rem;
    color: #fff;
    position: relative;
    overflow: hidden;
  }
  .settings-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
  }
  .settings-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(244,180,26,0.12);
    border-radius: 50%;
  }
  .avatar-wrap {
    position: relative;
    display: inline-block;
  }
  .avatar-img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.9);
    box-shadow: 0 6px 24px rgba(0,0,0,0.25);
  }
  .avatar-initials {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 4px solid rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.4rem;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 6px 24px rgba(0,0,0,0.2);
  }
  .avatar-edit-btn {
    position: absolute;
    bottom: 2px;
    right: 2px;
    background: #f4b41a;
    color: #fff;
    border: 2px solid #fff;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s;
    font-size: 0.75rem;
  }
  .avatar-edit-btn:hover { transform: scale(1.15); }
  .settings-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(11,102,35,0.10);
    overflow: hidden;
  }
  .settings-card .card-header {
    background: linear-gradient(90deg, #0b6623, #15803d);
    color: #fff;
    border: none;
    padding: 1.1rem 1.5rem;
    font-weight: 700;
    font-size: 1rem;
  }
  .settings-card .card-header.pw-header {
    background: linear-gradient(90deg, #1a237e, #283593);
  }
  .nav-pills .nav-link {
    border-radius: 12px;
    color: #0b6623;
    font-weight: 600;
    transition: all 0.2s;
    padding: 0.7rem 1.2rem;
  }
  .nav-pills .nav-link.active {
    background: linear-gradient(135deg, #0b6623, #15803d);
    color: #fff;
    box-shadow: 0 4px 12px rgba(11,102,35,0.25);
  }
  .nav-pills .nav-link:hover:not(.active) {
    background: rgba(11,102,35,0.08);
  }
  .form-control:focus {
    border-color: #0b6623;
    box-shadow: 0 0 0 0.2rem rgba(11,102,35,0.15);
  }
  .strength-bar { height: 5px; border-radius: 4px; transition: width 0.3s, background 0.3s; }
  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.18);
    color: #fff;
    border: 1.5px solid rgba(255,255,255,0.4);
    border-radius: 30px;
    padding: 0.35rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
    margin-bottom: 1rem;
    backdrop-filter: blur(4px);
  }
  .back-btn:hover { background: rgba(255,255,255,0.3); color: #fff; }
  @media (max-width: 767px) { .settings-hero { padding: 2rem 0 2rem; } }
</style>

<!-- Hero Banner -->
<div class="settings-hero">
  <div class="container position-relative" style="z-index:1;">
    <a href="<?php echo htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>" class="back-btn">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
    <div class="d-flex align-items-center gap-4 flex-wrap">
      <!-- Avatar with upload -->
      <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" id="avatarForm">
        <div class="avatar-wrap">
          <?php if ($profileImgExists): ?>
            <img src="<?php echo htmlspecialchars($profileImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="avatar-img">
          <?php else:
            $parts    = explode(' ', $_SESSION['full_name'] ?? 'U');
            $initials = strtoupper(substr($parts[0], 0, 1));
            if (count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));
          ?>
            <div class="avatar-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <label for="avatarInput" class="avatar-edit-btn" title="Change profile photo">
            <i class="fa-solid fa-camera"></i>
          </label>
          <input type="file" id="avatarInput" name="profile_picture" accept="image/*" class="d-none"
                 onchange="document.getElementById('avatarForm').submit();">
        </div>
      </form>
      <div>
        <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="mb-1 opacity-75" style="font-size:0.9rem;">
          <i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <span class="badge bg-<?php echo $badge; ?> text-uppercase" style="font-size:0.7rem; letter-spacing:1px;">
          <i class="fa-solid fa-id-badge me-1"></i><?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>
        </span>
      </div>
    </div>
  </div>
</div>

<div class="container py-5">
  <?php if (!empty($profileMsg ?? '')): ?>
    <?php echo $profileMsg; ?>
  <?php endif; ?>
  <?php if ($successMsg): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 border-0 shadow-sm rounded-3">
      <i class="fa-solid fa-circle-check fs-5"></i>
      <div><?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 border-0 shadow-sm rounded-3">
      <i class="fa-solid fa-circle-exclamation fs-5"></i>
      <div><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Sidebar Tabs -->
    <div class="col-md-3">
      <div class="card settings-card mb-3">
        <div class="card-body p-2 p-md-3">
          <div class="nav flex-row flex-md-column nav-pills gap-1" id="settingsTabs" role="tablist">
            <button class="nav-link active text-center text-md-start flex-grow-1 flex-md-grow-0" id="tab-profile" data-bs-toggle="pill"
                    data-bs-target="#pane-profile" type="button" role="tab">
              <i class="fa-solid fa-user me-1 me-md-2"></i><span>Profile</span>
            </button>
            <button class="nav-link text-center text-md-start flex-grow-1 flex-md-grow-0" id="tab-password" data-bs-toggle="pill"
                    data-bs-target="#pane-password" type="button" role="tab">
              <i class="fa-solid fa-lock me-1 me-md-2"></i><span>Password</span>
            </button>
            <hr class="my-2 d-none d-md-block">
            <a href="<?php echo htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>"
               class="nav-link text-start text-danger d-none d-md-block">
              <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="logout.php" class="nav-link text-start text-danger d-none d-md-block">
              <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Panes -->
    <div class="col-md-9">
      <div class="tab-content">

        <!-- ── Profile Update Pane ──────────────────────── -->
        <div class="tab-pane fade show active" id="pane-profile" role="tabpanel">
          <div class="card settings-card">
            <div class="card-header">
              <i class="fa-solid fa-user me-2"></i>Edit Profile Information
            </div>
            <div class="card-body p-4">
              <form method="POST" novalidate>
                <input type="hidden" name="action" value="update_profile">

                <!-- Profile Photo (inline in form) -->
                <div class="mb-4 text-center text-md-start">
                  <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:0.5px;">
                    Profile Photo
                  </label>
                  <div class="d-flex align-items-center gap-3 flex-wrap">
                    <?php if ($profileImgExists): ?>
                      <img src="<?php echo htmlspecialchars($profileImg, ENT_QUOTES, 'UTF-8'); ?>"
                           alt="Profile" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:3px solid #0b6623;">
                    <?php else: ?>
                      <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#0b6623,#15803d);
                                  display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;font-weight:700;">
                        <?php echo htmlspecialchars($initials ?? 'U', ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    <?php endif; ?>
                    <div>
                      <label for="profilePhotoBtn" class="btn btn-outline-success btn-sm fw-semibold" style="cursor:pointer;">
                        <i class="fa-solid fa-camera me-1"></i><?php echo $profileImgExists ? 'Change Photo' : 'Upload Photo'; ?>
                      </label>
                      <p class="text-muted small mb-0 mt-1">JPG, PNG, WebP — max 5 MB</p>
                    </div>
                  </div>
                  <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" id="inlinePhotoForm">
                    <input type="file" id="profilePhotoBtn" name="profile_picture" accept="image/*" class="d-none"
                           onchange="document.getElementById('inlinePhotoForm').submit();">
                  </form>
                </div>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                      <input type="text" name="full_name" class="form-control"
                             value="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                             placeholder="Your full name" required>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                      <input type="email" name="email" class="form-control"
                             value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                             placeholder="you@example.com" required>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                      <input type="tel" name="phone" class="form-control"
                             value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                             placeholder="+234 800 0000 000">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold small">Role</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="fa-solid fa-id-badge text-muted"></i></span>
                      <input type="text" class="form-control bg-light text-muted"
                             value="<?php echo ucfirst(htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8')); ?>"
                             readonly>
                    </div>
                    <div class="form-text">Role cannot be changed here.</div>
                  </div>
                </div>

                <div class="d-flex justify-content-end mt-4 gap-2">
                  <a href="<?php echo htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>"
                     class="btn btn-outline-secondary">Cancel</a>
                  <button type="submit" class="btn btn-hub px-4 fw-bold">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- ── Change Password Pane ──────────────────────── -->
        <div class="tab-pane fade" id="pane-password" role="tabpanel">
          <div class="card settings-card">
            <div class="card-header pw-header">
              <i class="fa-solid fa-lock me-2"></i>Change Password
            </div>
            <div class="card-body p-4">
              <form method="POST" novalidate id="pwForm">
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                  <label class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="current_password" id="currentPw" class="form-control"
                           placeholder="Enter current password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('currentPw', this)">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                </div>

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
                  <!-- Strength bar -->
                  <div class="mt-2">
                    <div class="d-flex justify-content-between mb-1">
                      <small class="text-muted">Password Strength</small>
                      <small id="strengthLabel" class="fw-semibold text-muted">—</small>
                    </div>
                    <div class="bg-light rounded" style="height:5px;">
                      <div id="strengthBar" class="strength-bar bg-danger" style="width:0%;"></div>
                    </div>
                  </div>
                  <div class="form-text mt-1">
                    <i class="fa-solid fa-circle-info me-1 text-muted"></i>
                    Use at least 8 characters with a mix of letters, numbers, and symbols.
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-shield-halved text-muted"></i></span>
                    <input type="password" name="confirm_password" id="confirmPw" class="form-control"
                           placeholder="Repeat new password" required oninput="checkMatch()">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('confirmPw', this)">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                  <div id="matchMsg" class="form-text mt-1"></div>
                </div>

                <div class="alert alert-info border-0 rounded-3 small d-flex align-items-center gap-2" style="background:rgba(11,102,35,0.07);">
                  <i class="fa-solid fa-shield-halved text-hub fs-5"></i>
                  <div>After a successful password change, you will remain logged in. Next login will require your new password.</div>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                  <a href="forgot_password.php" class="small text-hub text-decoration-none">
                    <i class="fa-solid fa-circle-question me-1"></i>Forgot your current password?
                  </a>
                  <button type="submit" class="btn fw-bold px-4" style="background:linear-gradient(135deg,#1a237e,#283593);color:#fff;">
                    <i class="fa-solid fa-shield-halved me-2"></i>Update Password
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

      </div><!-- /tab-content -->
    </div>
  </div>
</div>

<script>
  // Toggle password visibility
  function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      inp.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }

  // Password strength checker
  function checkStrength(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const configs = [
      { w: '0%',   color: '#dc3545', text: '—' },
      { w: '25%',  color: '#dc3545', text: 'Weak' },
      { w: '50%',  color: '#fd7e14', text: 'Fair' },
      { w: '75%',  color: '#ffc107', text: 'Good' },
      { w: '100%', color: '#0b6623', text: 'Strong' },
    ];
    const cfg = configs[score];
    bar.style.width      = cfg.w;
    bar.style.background = cfg.color;
    label.textContent    = cfg.text;
    label.style.color    = cfg.color;
  }

  // Confirm password match
  function checkMatch() {
    const np  = document.getElementById('newPw').value;
    const cp  = document.getElementById('confirmPw').value;
    const msg = document.getElementById('matchMsg');
    if (cp === '') { msg.textContent = ''; return; }
    if (np === cp) {
      msg.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Passwords match!</span>';
    } else {
      msg.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Passwords do not match.</span>';
    }
  }

  // Activate password tab if action was change_password
  <?php if (($_POST['action'] ?? '') === 'change_password'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      const pwTab = document.getElementById('tab-password');
      if (pwTab) { bootstrap.Tab.getOrCreateInstance(pwTab).show(); }
    });
  <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
