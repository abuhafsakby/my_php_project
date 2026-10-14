<?php
require_once 'config/db.php';

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$codeParam = trim($_GET['code'] ?? '');

$enrollment = null;

if ($codeParam !== '') {
    // Lookup by public certificate code
    $stmt = $conn->prepare("SELECT e.*, c.title AS course_title, c.category, u.full_name AS student_name, fac.full_name AS facilitator_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN users u ON e.student_id = u.id
        LEFT JOIN users fac ON c.created_by = fac.id
        WHERE e.certificate_code = ? AND e.status = 'completed' LIMIT 1");
    $stmt->bind_param("s", $codeParam);
    $stmt->execute();
    $enrollment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($course_id) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $sessionUserId = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? 'student';

    if ($role === 'student') {
        $stmt = $conn->prepare("SELECT e.*, c.title AS course_title, c.category, u.full_name AS student_name, fac.full_name AS facilitator_name
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            JOIN users u ON e.student_id = u.id
            LEFT JOIN users fac ON c.created_by = fac.id
            WHERE e.student_id = ? AND e.course_id = ? AND e.status = 'completed' LIMIT 1");
        $stmt->bind_param("ii", $sessionUserId, $course_id);
    } else {
        // Admin or Facilitator viewing student certificate
        $studentIdParam = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0;
        $sql = "SELECT e.*, c.title AS course_title, c.category, u.full_name AS student_name, fac.full_name AS facilitator_name
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            JOIN users u ON e.student_id = u.id
            LEFT JOIN users fac ON c.created_by = fac.id
            WHERE e.course_id = ? AND e.status = 'completed'";
        if ($studentIdParam > 0) {
            $sql .= " AND e.student_id = ?";
            $stmt = $conn->prepare($sql . " LIMIT 1");
            $stmt->bind_param("ii", $course_id, $studentIdParam);
        } else {
            $stmt = $conn->prepare($sql . " LIMIT 1");
            $stmt->bind_param("i", $course_id);
        }
    }
    $stmt->execute();
    $enrollment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$enrollment) {
    header('Location: student_dashboard.php');
    exit;
}

// Ensure certificate code exists
if (empty($enrollment['certificate_code'])) {
    $newCode = 'KH-' . strtoupper(bin2hex(random_bytes(6)));
    $upStmt = $conn->prepare("UPDATE enrollments SET certificate_code = ? WHERE id = ?");
    $upStmt->bind_param("si", $newCode, $enrollment['id']);
    $upStmt->execute();
    $upStmt->close();
    $enrollment['certificate_code'] = $newCode;
}

$student_name = $enrollment['student_name'] ?? ($_SESSION['full_name'] ?? 'Graduate');
$cert_code = $enrollment['certificate_code'];
$completion_date = date('F d, Y', strtotime($enrollment['completed_at'] ?? 'now'));
$score = (int)($enrollment['quiz_score'] ?? 0);
$facilitator_name = !empty($enrollment['facilitator_name']) ? $enrollment['facilitator_name'] : 'Lead Faculty Director';

// Generate cryptographic hash signature
$hashSeed = $cert_code . '|' . $student_name . '|' . $enrollment['course_id'] . '|' . ($enrollment['completed_at'] ?? '') . '|KIBIYA_SECURE_AUTH';
$securityHash = hash('sha256', $hashSeed);
$shortHash = substr($securityHash, 0, 16) . '...' . substr($securityHash, -12);

// Verification URL
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$verifyUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/verify_certificate.php?code=' . urlencode($cert_code);
$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=" . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?> - Kibiya Tech Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts for High-End Certificate Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Cinzel+Decorative:wght@700;900&family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600;1,700&display=swap" rel="stylesheet">

    <style>
      :root {
        --cert-gold: #c59b27;
        --cert-gold-light: #fbf5b7;
        --cert-gold-dark: #926f1a;
        --cert-green: #074e20;
        --cert-green-dark: #043314;
        --cert-cream: #fdfbf7;
      }

      body {
        background-color: #e5e9ec;
        font-family: 'Montserrat', sans-serif;
        color: #1f2937;
        margin: 0;
        padding: 0;
      }

      /* Control Toolbar */
      .cert-toolbar {
        background: #ffffff;
        border-bottom: 1px solid #d1d5db;
        padding: 12px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      }

      /* Certificate Canvas */
      .certificate-wrapper {
        padding: 40px 15px;
        display: flex;
        justify-content: center;
      }

      .certificate-canvas {
        width: 1080px;
        min-height: 760px;
        background: var(--cert-cream);
        position: relative;
        box-shadow: 0 25px 60px rgba(0,0,0,0.18), 0 5px 15px rgba(0,0,0,0.08);
        border-radius: 4px;
        box-sizing: border-box;
        overflow: hidden;
        /* Subtle Guilloche Watermark Pattern */
        background-image: 
          radial-gradient(#c59b27 0.75px, transparent 0.75px),
          radial-gradient(#074e20 0.5px, #fdfbf7 0.5px);
        background-size: 30px 30px;
        background-position: 0 0, 15px 15px;
      }

      /* Multi-layer Security Borders */
      .cert-border-outer {
        position: absolute;
        top: 20px;
        left: 20px;
        right: 20px;
        bottom: 20px;
        border: 4px solid var(--cert-green);
        box-shadow: inset 0 0 0 3px var(--cert-gold);
        pointer-events: none;
      }

      .cert-border-inner {
        position: absolute;
        top: 32px;
        left: 32px;
        right: 32px;
        bottom: 32px;
        border: 1px dashed rgba(197, 155, 39, 0.6);
        pointer-events: none;
      }

      /* Classical Corner Ornaments */
      .corner-ornament {
        position: absolute;
        width: 60px;
        height: 60px;
        pointer-events: none;
        z-index: 3;
      }
      .corner-tl { top: 18px; left: 18px; border-top: 5px solid var(--cert-gold); border-left: 5px solid var(--cert-gold); }
      .corner-tr { top: 18px; right: 18px; border-top: 5px solid var(--cert-gold); border-right: 5px solid var(--cert-gold); }
      .corner-bl { bottom: 18px; left: 18px; border-bottom: 5px solid var(--cert-gold); border-left: 5px solid var(--cert-gold); }
      .corner-br { bottom: 18px; right: 18px; border-bottom: 5px solid var(--cert-gold); border-right: 5px solid var(--cert-gold); }

      /* Watermark Crest Background */
      .cert-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 380px;
        color: rgba(7, 78, 32, 0.035);
        pointer-events: none;
        z-index: 1;
      }

      /* Content Container */
      .cert-content {
        position: relative;
        z-index: 2;
        padding: 55px 75px;
        text-align: center;
      }

      /* Typography */
      .org-name {
        font-family: 'Cinzel', serif;
        font-size: 26px;
        letter-spacing: 5px;
        font-weight: 800;
        color: var(--cert-green);
        text-transform: uppercase;
        margin-bottom: 3px;
      }

      .org-sub {
        font-family: 'Montserrat', sans-serif;
        font-size: 11px;
        letter-spacing: 3px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        margin-bottom: 25px;
      }

      .cert-heading {
        font-family: 'Cinzel Decorative', 'Cinzel', serif;
        font-size: 38px;
        font-weight: 900;
        color: #111827;
        letter-spacing: 6px;
        text-transform: uppercase;
        margin: 10px 0;
        background: linear-gradient(135deg, #074e20 0%, #1a3822 50%, #074e20 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
      }

      .cert-proclamation {
        font-family: 'Playfair Display', serif;
        font-style: italic;
        font-size: 16px;
        color: #4b5563;
        margin-top: 15px;
        margin-bottom: 10px;
      }

      .student-name {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 46px;
        font-weight: 700;
        color: #043314;
        margin: 12px 0 15px 0;
        letter-spacing: 1px;
        position: relative;
        display: inline-block;
        padding: 0 35px 8px 35px;
        border-bottom: 2px solid var(--cert-gold);
      }

      .student-name::after {
        content: '♦';
        position: absolute;
        bottom: -9px;
        left: 50%;
        transform: translateX(-50%);
        color: var(--cert-gold);
        background: var(--cert-cream);
        padding: 0 8px;
        font-size: 13px;
      }

      .course-statement {
        font-size: 14px;
        color: #4b5563;
        max-width: 720px;
        margin: 15px auto 8px auto;
        line-height: 1.6;
      }

      .course-title-display {
        font-family: 'Cinzel', serif;
        font-size: 26px;
        font-weight: 800;
        color: var(--cert-green);
        letter-spacing: 1.5px;
        margin: 8px 0 6px 0;
      }

      .course-grade-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #1f2937;
        background: rgba(197, 155, 39, 0.15);
        border: 1px solid rgba(197, 155, 39, 0.4);
        padding: 3px 14px;
        border-radius: 20px;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 30px;
      }

      /* Bottom Footer Area */
      .cert-footer {
        margin-top: 25px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
      }

      .signature-block {
        width: 250px;
        text-align: center;
      }

      .signature-script {
        font-family: 'Alex Brush', cursive;
        font-size: 38px;
        color: #1e3a24;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .signature-line {
        border-top: 1.5px solid #374151;
        margin-top: 4px;
        padding-top: 5px;
      }

      .signatory-name {
        font-family: 'Montserrat', sans-serif;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        margin: 0;
      }

      .signatory-title {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      /* 3D Gold Embossed Seal */
      .seal-container {
        position: relative;
        width: 140px;
        height: 140px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
      }

      .gold-seal-badge {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 35%, #fbf5b7 0%, #dfba4a 40%, #c59b27 70%, #996515 100%);
        box-shadow: 0 8px 20px rgba(153, 101, 21, 0.35), inset 0 2px 4px rgba(255, 255, 255, 0.7);
        border: 3px dashed #7a540f;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #4a3407;
        position: relative;
        z-index: 2;
      }

      .gold-seal-badge i {
        font-size: 26px;
        color: #4a3407;
        margin-bottom: 2px;
      }

      .seal-text-top {
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
      }

      .seal-text-bottom {
        font-size: 7.5px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
      }

      /* Seal Ribbons */
      .seal-ribbon-left, .seal-ribbon-right {
        position: absolute;
        bottom: -15px;
        width: 24px;
        height: 48px;
        background: #074e20;
        z-index: 1;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      }
      .seal-ribbon-left {
        left: 46px;
        transform: rotate(18deg);
        clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 80%, 0 100%);
      }
      .seal-ribbon-right {
        right: 46px;
        transform: rotate(-18deg);
        clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 80%, 0 100%);
      }

      /* QR Code & Security Block */
      .cert-security-bar {
        position: absolute;
        bottom: 34px;
        left: 50px;
        right: 50px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid rgba(197, 155, 39, 0.3);
        padding-top: 10px;
        font-size: 9.5px;
        color: #6b7280;
      }

      .qr-block {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
      }

      .qr-img {
        width: 60px;
        height: 60px;
        border: 2px solid var(--cert-gold);
        border-radius: 4px;
        padding: 2px;
        background: #fff;
      }

      .security-meta {
        text-align: right;
      }

      /* Print Optimizations */
      @media print {
        @page {
          size: landscape;
          margin: 0;
        }
        body {
          background: #fff !important;
          padding: 0 !important;
          margin: 0 !important;
        }
        .cert-toolbar, .no-print {
          display: none !important;
        }
        .certificate-wrapper {
          padding: 0 !important;
          margin: 0 !important;
        }
        .certificate-canvas {
          box-shadow: none !important;
          width: 100vw !important;
          height: 100vh !important;
          max-height: 100vh !important;
          border-radius: 0 !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
      }

      @media (max-width: 991.98px) {
        .certificate-wrapper {
          padding: 20px 10px;
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          justify-content: flex-start;
        }
        .cert-mobile-hint {
          display: block !important;
        }
        .cert-toolbar .btn {
          font-size: 13px;
          padding: 6px 10px;
        }
      }
      .cert-mobile-hint {
        display: none;
      }
    </style>
</head>
<body>

<!-- Control Bar (hidden when printing) -->
<div class="cert-toolbar no-print">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="student_dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Portal
      </a>
      <span class="badge bg-success-subtle text-success border border-success-subtle px-2 px-sm-3 py-2 fw-semibold">
        <i class="fa-solid fa-shield-halved me-1"></i>Verified Credential
      </span>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm fw-semibold">
        <i class="fa-solid fa-qrcode me-1"></i>Verify
      </a>
      <button onclick="copyVerifyUrl()" class="btn btn-outline-secondary btn-sm fw-semibold" id="copyBtn">
        <i class="fa-solid fa-link me-1"></i>Copy Link
      </button>
      <button onclick="window.print()" class="btn btn-hub btn-sm fw-bold px-3" style="background:#0b6623; color:#fff;">
        <i class="fa-solid fa-print me-1"></i>Print / PDF
      </button>
    </div>
  </div>
</div>

<div class="cert-mobile-hint no-print bg-light border-bottom text-center py-2 px-3 small text-muted">
  <i class="fa-solid fa-rotate text-success me-1"></i> <strong>Mobile Tip:</strong> Rotate to landscape or swipe left/right to inspect the full certificate canvas.
</div>

<!-- Main Certificate Canvas -->
<div class="certificate-wrapper">
  <div class="certificate-canvas">
    
    <!-- Multi-layered Outer & Inner Borders -->
    <div class="cert-border-outer"></div>
    <div class="cert-border-inner"></div>

    <!-- Classical Gold Corner Rosettes -->
    <div class="corner-ornament corner-tl"></div>
    <div class="corner-ornament corner-tr"></div>
    <div class="corner-ornament corner-bl"></div>
    <div class="corner-ornament corner-br"></div>

    <!-- Background Watermark Crest -->
    <div class="cert-watermark">
      <i class="fa-solid fa-certificate"></i>
    </div>

    <!-- Certificate Body Content -->
    <div class="cert-content">
      <!-- Top Crest & Organization Title -->
      <div class="mb-1">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width: 52px; height: 52px; background: radial-gradient(circle, #dfba4a, #c59b27); color: #074e20; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">
          <i class="fa-solid fa-lightbulb fa-lg text-white"></i>
        </div>
      </div>
      <div class="org-name">Kibiya Technology &amp; Innovation Hub</div>
      <div class="org-sub">Kano State, Nigeria &bull; Center for Excellence &bull; Digital &bull; Technical &bull; Vocational</div>

      <!-- Main Heading -->
      <div class="cert-heading">Certificate of Completion</div>
      <div class="cert-proclamation">This is to officially certify that</div>

      <!-- Graduate Name -->
      <div class="student-name">
        <?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <!-- Narrative -->
      <div class="course-statement">
        has successfully met all academic standards, completed the full curriculum, and demonstrated verified competency in the professional specialization program:
      </div>

      <!-- Course Title -->
      <div class="course-title-display">
        <?php echo htmlspecialchars($enrollment['course_title'], ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <!-- Distinction & Category Badge -->
      <div>
        <span class="course-grade-badge">
          <i class="fa-solid fa-award text-warning"></i>
          <?php echo htmlspecialchars($enrollment['category'] ?? 'Professional Certificate', ENT_QUOTES, 'UTF-8'); ?>
          <?php if ($score > 0): ?>
            &bull; Score: <?php echo $score; ?>% (Pass with Distinction)
          <?php endif; ?>
        </span>
      </div>

      <!-- Signatures & Official Gold Seal Section -->
      <div class="cert-footer">
        
        <!-- Signature 1: Academic Facilitator -->
        <div class="signature-block">
          <div class="signature-script">
            <?php echo htmlspecialchars(explode(' ', $facilitator_name)[0] ?? 'Aminu', ENT_QUOTES, 'UTF-8'); ?>
          </div>
          <div class="signature-line">
            <p class="signatory-name"><?php echo htmlspecialchars($facilitator_name, ENT_QUOTES, 'UTF-8'); ?></p>
            <span class="signatory-title">Lead Instructor &bull; Faculty Chair</span>
          </div>
        </div>

        <!-- Center: 3D Gold Foil Embossed Seal -->
        <div class="seal-container">
          <div class="seal-ribbon-left"></div>
          <div class="seal-ribbon-right"></div>
          <div class="gold-seal-badge">
            <span class="seal-text-top">KIBIYA HUB</span>
            <i class="fa-solid fa-award"></i>
            <span class="seal-text-bottom">OFFICIAL SEAL</span>
          </div>
        </div>

        <!-- Signature 2: Executive Director -->
        <div class="signature-block">
          <div class="signature-script" style="font-family:'Alex Brush'; color:#0b6623;">
            B. Sani Kibiya
          </div>
          <div class="signature-line">
            <p class="signatory-name">Dr. Bashir Sani Kibiya</p>
            <span class="signatory-title">Executive Director &bull; Kibiya Hub</span>
          </div>
        </div>

      </div>

    </div>

    <!-- Security, QR Code & Verification Bar at Bottom -->
    <div class="cert-security-bar">
      <!-- QR Code and Serial -->
      <div class="qr-block">
        <img src="<?php echo htmlspecialchars($qrApiUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Verification QR Code" class="qr-img">
        <div>
          <div class="fw-bold text-dark" style="font-size: 10.5px;">SERIAL NO: <?php echo htmlspecialchars($cert_code, ENT_QUOTES, 'UTF-8'); ?></div>
          <div>Issued: <strong><?php echo $completion_date; ?></strong></div>
          <div class="text-muted" style="font-size: 8.5px;">Scan QR to verify credential authenticity</div>
        </div>
      </div>

      <!-- Security Hash Fingerprint -->
      <div class="security-meta">
        <div class="fw-bold text-dark" style="font-size: 9.5px;">
          <i class="fa-solid fa-fingerprint me-1 text-success"></i>DIGITAL SECURITY HASH (SHA-256)
        </div>
        <div class="font-monospace" style="font-size: 8.5px; color:#4b5563;">
          <?php echo htmlspecialchars($shortHash, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div style="font-size: 8px; color: #9ca3af;">
          Kibiya Technology &amp; Innovation Hub LMS &bull; Kano State, Nigeria
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function copyVerifyUrl() {
  const url = "<?php echo addslashes($verifyUrl); ?>";
  navigator.clipboard.writeText(url).then(() => {
    const btn = document.getElementById('copyBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Link Copied!';
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-success');
    setTimeout(() => {
      btn.innerHTML = orig;
      btn.classList.remove('btn-success');
      btn.classList.add('btn-outline-secondary');
    }, 2500);
  });
}
</script>
<?php if (isset($_SESSION['user_id'])): ?>
<script>
(function() {
  const INACTIVITY_LIMIT_MS = 10 * 60 * 1000;
  let inactivityTimer;

  function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(function() {
      window.location.href = 'logout.php?timeout=1';
    }, INACTIVITY_LIMIT_MS);
  }

  const userActivityEvents = ['mousemove', 'mousedown', 'keydown', 'keypress', 'scroll', 'touchstart', 'click'];
  userActivityEvents.forEach(function(eventType) {
    window.addEventListener(eventType, resetInactivityTimer, { passive: true });
  });

  resetInactivityTimer();
})();
</script>
<?php endif; ?>

</body>
</html>
