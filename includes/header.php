<?php
require_once __DIR__ . '/../config/db.php';

// Hub Brand Logo resolution
$logoWebPath = 'uploads/kibiya_logo.png';
$logoDiskPath = __DIR__ . '/../' . $logoWebPath;
$uploadedLogoSource = 'C:/Users/umarb/.gemini/antigravity-ide/brain/1acce7cc-f5d5-4e5b-bfbd-7362bd6e8a7c/.user_uploaded/media_1791892515196.png';

if (!file_exists($logoDiskPath) && file_exists($uploadedLogoSource)) {
    @copy($uploadedLogoSource, $logoDiskPath);
}

$logoSrc = file_exists($logoDiskPath) ? $logoWebPath : '';
if (!$logoSrc && file_exists($uploadedLogoSource)) {
    $imgData = @file_get_contents($uploadedLogoSource);
    if ($imgData !== false) {
        $logoSrc = 'data:image/png;base64,' . base64_encode($imgData);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#0b6623">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <?php
    // Dynamic page title — pages can override by setting $pageTitle before including this header
    if (empty($pageTitle)) {
        $pageTitleMap = [
            'index.php'               => 'Home',
            'about.php'               => 'About Us',
            'courses.php'             => 'Courses',
            'course_view.php'         => 'Course',
            'news.php'                => 'News & Opportunities',
            'contact.php'             => 'Contact Us',
            'gallery.php'             => 'Gallery',
            'management.php'          => 'Management',
            'partners.php'            => 'Partners',
            'login.php'               => 'Login',
            'register.php'            => 'Register',
            'logout.php'              => 'Logout',
            'student_dashboard.php'   => 'Student Portal',
            'facilitator_dashboard.php' => 'Facilitator Dashboard',
            'admin_dashboard.php'     => 'Admin Dashboard',
            'settings.php'            => 'Account Settings',
            'forgot_password.php'     => 'Account Recovery',
            'reset_password.php'      => 'Reset Password',
            'take_quiz.php'           => 'Assessment',
            'generate_certificate.php'=> 'Certificate',
            'verify_certificate.php'  => 'Verify Certificate',
            'upload_profile_picture.php' => 'Profile',
        ];
        $currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        $pageLabel   = $pageTitleMap[$currentPage] ?? ucwords(str_replace(['-', '_', '.php'], [' ', ' ', ''], $currentPage));
        $pageTitle   = $pageLabel . ' – Kibiya Hub LMS';
    }
    ?>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (!empty($logoSrc)): ?>
      <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #0b6623; 
            --primary-dark: #084919; 
            --secondary-color: #f4b41a; 
        }

        /* ── Base & Global Cross-Device Ergonomics ── */
        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            -webkit-text-size-adjust: 100%;
            -webkit-font-smoothing: antialiased;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .bg-hub { background-color: var(--primary-color) !important; }
        .btn-hub { background-color: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .btn-hub:hover, .btn-hub:focus { background-color: var(--primary-dark); color: #fff; border-color: var(--primary-dark); }
        .text-hub { color: var(--primary-color) !important; }

        /* ── Navbar & Brand ── */
        .navbar {
            box-shadow: 0 2px 14px rgba(0,0,0,0.12);
            transition: padding 0.25s ease;
        }

        .navbar-brand-logo {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.9);
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            flex-shrink: 0;
        }
        .navbar-brand:hover .navbar-brand-logo {
            transform: scale(1.06);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.28);
        }

        .brand-text-main {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
        }
        .brand-text-sub {
            font-size: 0.65rem;
            letter-spacing: 0.8px;
        }

        .navbar-profile-avatar {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.85);
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }

        .navbar-toggler {
            border: 1.5px solid rgba(255,255,255,0.4);
            border-radius: 10px;
            padding: 0.45rem 0.65rem;
        }
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.2rem rgba(244,180,26,0.35);
        }

        /* ── Mobile Navigation Drawer (< 992px) ── */
        @media (max-width: 991.98px) {
            .navbar-brand-logo {
                width: 40px;
                height: 40px;
            }
            .brand-text-main {
                font-size: 1.15rem;
            }
            .brand-text-sub {
                font-size: 0.58rem;
                letter-spacing: 0.5px;
            }

            .navbar-collapse {
                background: linear-gradient(180deg, #09531d 0%, #063914 100%);
                border-radius: 18px;
                padding: 1.15rem 1rem;
                margin-top: 0.75rem;
                box-shadow: 0 16px 36px rgba(0,0,0,0.35);
                border: 1px solid rgba(255,255,255,0.14);
            }

            .navbar-nav .nav-link {
                padding: 0.65rem 0.85rem !important;
                border-radius: 10px;
                margin-bottom: 2px;
                font-size: 0.95rem;
                font-weight: 500;
            }
            .navbar-nav .nav-link:hover, .navbar-nav .nav-link:focus {
                background: rgba(255,255,255,0.12);
                color: #fff !important;
            }

            .mobile-nav-actions {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                border-top: 1px solid rgba(255,255,255,0.16);
            }
            .mobile-nav-actions .btn {
                width: 100%;
                text-align: center;
                padding: 0.55rem 1rem;
                font-size: 0.9rem;
            }

            .navbar-nav .dropdown-menu {
                background: #ffffff;
                border-radius: 14px;
                padding: 0.6rem;
                margin-top: 0.4rem;
                box-shadow: 0 10px 28px rgba(0,0,0,0.25);
                border: none;
            }
            .navbar-nav .dropdown-item {
                border-radius: 8px;
                padding: 0.55rem 0.85rem;
            }
        }

        /* ── Touch-Friendly Forms & Safe iOS Inputs ── */
        @media (max-width: 767.98px) {
            .form-control, .form-select, input, select, textarea {
                font-size: 16px !important; /* Prevents auto-zoom on iOS Safari */
            }
            .btn {
                min-height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .display-4 { font-size: 2.15rem !important; }
            .display-5 { font-size: 1.75rem !important; }
            h1 { font-size: 1.75rem !important; }
            h2 { font-size: 1.45rem !important; }
            h3 { font-size: 1.25rem !important; }

            /* Sticky sidebars can block small phone screens, make them flow smoothly */
            .sticky-top {
                position: static !important;
                top: auto !important;
            }
        }

        /* ── Horizontal Scrollable Tabs for Dashboards on Mobile ── */
        @media (max-width: 991.98px) {
            .nav-pills {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: thin;
                padding-bottom: 6px !important;
            }
            .nav-pills .nav-item {
                flex: 0 0 auto !important;
            }
        }

        /* ── Responsive Tables with Smooth Touch Momentum ── */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
            overflow-x: auto;
        }

        /* ── Card Touch Feedbacks ── */
        @media (hover: hover) and (pointer: fine) {
            .hover-lift {
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            }
            .hover-lift:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 30px rgba(0,0,0,0.12) !important;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-hub py-2 py-lg-3 sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2 text-decoration-none" href="index.php">
      <?php if (!empty($logoSrc)): ?>
        <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Kibiya Hub Logo" class="navbar-brand-logo">
      <?php else: ?>
        <i class="fa-solid fa-lightbulb text-warning fs-3 me-1"></i>
      <?php endif; ?>
      <div class="d-flex flex-column justify-content-center">
        <span class="brand-text-main text-white lh-1">Kibiya Hub</span>
        <span class="brand-text-sub text-warning text-uppercase fw-semibold">Tech &amp; Innovation LMS</span>
      </div>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
        <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
        <li class="nav-item"><a class="nav-link" href="news.php">News &amp; Opportunities</a></li>
        <li class="nav-item"><a class="nav-link" href="management.php">Management</a></li>
        <li class="nav-item"><a class="nav-link" href="partners.php">Partners</a></li>
        <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact Us</a></li>
        
        <?php if (isset($_SESSION['user_id'])): ?>
          <?php
            $navProfileImg = $_SESSION['profile_image'] ?? '';
            $navProfileImgExists = ($navProfileImg !== '' && file_exists(__DIR__ . '/../' . $navProfileImg));
          ?>
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle text-white d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php if ($navProfileImgExists): ?>
                <img src="<?php echo htmlspecialchars($navProfileImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="navbar-profile-avatar">
              <?php else: ?>
                <i class="fa-solid fa-circle-user text-warning fs-5"></i>
              <?php endif; ?>
              <span class="fw-semibold text-truncate" style="max-width: 140px;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Account', ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="badge bg-warning text-dark text-uppercase small ms-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($_SESSION['role'] ?? 'user', ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
              <li><h6 class="dropdown-header text-muted"><i class="fa-solid fa-id-badge me-1"></i>Active Session</h6></li>
              <li><span class="dropdown-item-text small text-muted"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></li>
              <li><hr class="dropdown-divider"></li>
              <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <li><a class="dropdown-item fw-semibold" href="admin_dashboard.php"><i class="fa-solid fa-user-shield text-success me-2"></i>Admin Panel</a></li>
              <?php elseif (($_SESSION['role'] ?? '') === 'facilitator'): ?>
                <li><a class="dropdown-item fw-semibold" href="facilitator_dashboard.php"><i class="fa-solid fa-chalkboard-user text-primary me-2"></i>Facilitator Panel</a></li>
              <?php else: ?>
                <li><a class="dropdown-item fw-semibold" href="student_dashboard.php"><i class="fa-solid fa-graduation-cap text-success me-2"></i>Student Portal</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item fw-semibold" href="settings.php"><i class="fa-solid fa-gear text-secondary me-2"></i>Account Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item text-danger fw-bold" href="logout.php">
                  <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                </a>
              </li>
            </ul>
          </li>
          <!-- Mobile / Desktop Auth Action Buttons -->
          <li class="nav-item d-none d-lg-block">
            <a class="btn btn-outline-light btn-sm ms-lg-2 fw-semibold d-inline-flex align-items-center" href="settings.php" title="Account Settings">
              <i class="fa-solid fa-gear me-1"></i>Settings
            </a>
          </li>
          <li class="nav-item d-none d-lg-block">
            <a class="btn btn-outline-light btn-sm ms-lg-2 fw-semibold d-inline-flex align-items-center" href="logout.php" title="End Session">
              <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
            </a>
          </li>
          <li class="nav-item d-lg-none w-100">
            <div class="mobile-nav-actions">
              <a class="btn btn-outline-light btn-sm fw-semibold" href="settings.php">
                <i class="fa-solid fa-gear me-1"></i>Account Settings
              </a>
              <a class="btn btn-danger btn-sm fw-semibold" href="logout.php">
                <i class="fa-solid fa-right-from-bracket me-1"></i>Log Out
              </a>
            </div>
          </li>
        <?php else: ?>
          <li class="nav-item d-none d-lg-block">
            <a class="btn btn-outline-light btn-sm ms-lg-2" href="login.php">Login</a>
          </li>
          <li class="nav-item d-none d-lg-block">
            <a class="btn btn-warning btn-sm ms-lg-2 fw-bold" href="register.php">Register</a>
          </li>
          <li class="nav-item d-lg-none w-100">
            <div class="mobile-nav-actions">
              <a class="btn btn-outline-light btn-sm fw-semibold" href="login.php">
                <i class="fa-solid fa-right-to-bracket me-1"></i>Login
              </a>
              <a class="btn btn-warning btn-sm fw-bold" href="register.php">
                <i class="fa-solid fa-user-plus me-1"></i>Register Free
              </a>
            </div>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
