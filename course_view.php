<?php
require_once 'config/db.php';

$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$course_id) {
    header('Location: courses.php');
    exit;
}

// Fetch course details
$stmt = $conn->prepare("SELECT c.*, u.full_name AS facilitator_name, u.email AS facilitator_email,
        (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS module_count,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
        FROM courses c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: courses.php');
    exit;
}

// Check student enrollment status
$isEnrolled = false;
$enrollmentStatus = null;
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    $studentId = (int)$_SESSION['user_id'];
    $enrStmt = $conn->prepare("SELECT id, status, quiz_score FROM enrollments WHERE student_id = ? AND course_id = ?");
    $enrStmt->bind_param("ii", $studentId, $course_id);
    $enrStmt->execute();
    $enrRes = $enrStmt->get_result();
    if ($enrRow = $enrRes->fetch_assoc()) {
        $isEnrolled = true;
        $enrollmentStatus = $enrRow['status'];
    }
    $enrStmt->close();
}

// Fetch all modules
$modStmt = $conn->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY id ASC");
$modStmt->bind_param("i", $course_id);
$modStmt->execute();
$modules = $modStmt->get_result();

// Course styling helper
function getCourseTheme($title) {
    $t = strtolower($title);
    if (str_contains($t, 'automation') || str_contains($t, 'agent')) {
        return ['icon' => 'fa-solid fa-robot', 'color' => '#10b981', 'gradient' => 'linear-gradient(135deg, #059669, #10b981)'];
    }
    if (str_contains($t, 'animation') || str_contains($t, 'content creation')) {
        return ['icon' => 'fa-solid fa-wand-magic-sparkles', 'color' => '#8b5cf6', 'gradient' => 'linear-gradient(135deg, #6d28d9, #8b5cf6)'];
    }
    if (str_contains($t, 'app development') || str_contains($t, 'mobile')) {
        return ['icon' => 'fa-solid fa-mobile-screen-button', 'color' => '#3b82f6', 'gradient' => 'linear-gradient(135deg, #1d4ed8, #3b82f6)'];
    }
    if (str_contains($t, 'cloud')) {
        return ['icon' => 'fa-solid fa-cloud', 'color' => '#0284c7', 'gradient' => 'linear-gradient(135deg, #0369a1, #0284c7)'];
    }
    if (str_contains($t, 'cybersecurity') || str_contains($t, 'security')) {
        return ['icon' => 'fa-solid fa-shield-halved', 'color' => '#ef4444', 'gradient' => 'linear-gradient(135deg, #b91c1c, #ef4444)'];
    }
    if (str_contains($t, 'data analysis') || str_contains($t, 'analytics')) {
        return ['icon' => 'fa-solid fa-chart-pie', 'color' => '#f59e0b', 'gradient' => 'linear-gradient(135deg, #d97706, #f59e0b)'];
    }
    if (str_contains($t, 'dev ops') || str_contains($t, 'devops')) {
        return ['icon' => 'fa-solid fa-gears', 'color' => '#6366f1', 'gradient' => 'linear-gradient(135deg, #4338ca, #6366f1)'];
    }
    if (str_contains($t, 'digital marketing') || str_contains($t, 'marketing')) {
        return ['icon' => 'fa-solid fa-bullhorn', 'color' => '#ec4899', 'gradient' => 'linear-gradient(135deg, #be185d, #ec4899)'];
    }
    if (str_contains($t, 'graphics design') || str_contains($t, 'graphic')) {
        return ['icon' => 'fa-solid fa-palette', 'color' => '#a855f7', 'gradient' => 'linear-gradient(135deg, #7e22ce, #a855f7)'];
    }
    if (str_contains($t, 'machine learning')) {
        return ['icon' => 'fa-solid fa-brain', 'color' => '#06b6d4', 'gradient' => 'linear-gradient(135deg, #0891b2, #06b6d4)'];
    }
    if (str_contains($t, 'python')) {
        return ['icon' => 'fa-brands fa-python', 'color' => '#0284c7', 'gradient' => 'linear-gradient(135deg, #0284c7, #38bdf8)'];
    }
    if (str_contains($t, 'ui/ux') || str_contains($t, 'ux')) {
        return ['icon' => 'fa-solid fa-bezier-curve', 'color' => '#f43f5e', 'gradient' => 'linear-gradient(135deg, #e11d48, #f43f5e)'];
    }
    if (str_contains($t, 'web development') || str_contains($t, 'web')) {
        return ['icon' => 'fa-solid fa-code', 'color' => '#0b6623', 'gradient' => 'linear-gradient(135deg, #0b6623, #15803d)'];
    }
    if (str_contains($t, 'livestock') || str_contains($t, 'farming')) {
        return ['icon' => 'fa-solid fa-cow', 'color' => '#65a30d', 'gradient' => 'linear-gradient(135deg, #4d7c0f, #65a30d)'];
    }
    if (str_contains($t, 'hardware') || str_contains($t, 'gsm')) {
        return ['icon' => 'fa-solid fa-microchip', 'color' => '#d97706', 'gradient' => 'linear-gradient(135deg, #b45309, #f59e0b)'];
    }
    if (str_contains($t, 'leather')) {
        return ['icon' => 'fa-solid fa-scissors', 'color' => '#9a3412', 'gradient' => 'linear-gradient(135deg, #7c2d12, #c2410c)'];
    }
    if (str_contains($t, 'satellite') || str_contains($t, 'cctv')) {
        return ['icon' => 'fa-solid fa-satellite-dish', 'color' => '#0284c7', 'gradient' => 'linear-gradient(135deg, #0369a1, #0ea5e9)'];
    }
    if (str_contains($t, 'networking')) {
        return ['icon' => 'fa-solid fa-network-wired', 'color' => '#1d4ed8', 'gradient' => 'linear-gradient(135deg, #1e40af, #2563eb)'];
    }
    if (str_contains($t, 'catering') || str_contains($t, 'hospitality')) {
        return ['icon' => 'fa-solid fa-utensils', 'color' => '#ea580c', 'gradient' => 'linear-gradient(135deg, #c2410c, #f97316)'];
    }
    if (str_contains($t, 'refrigerator') || str_contains($t, 'air-condition')) {
        return ['icon' => 'fa-solid fa-snowflake', 'color' => '#0284c7', 'gradient' => 'linear-gradient(135deg, #0369a1, #38bdf8)'];
    }
    return ['icon' => 'fa-solid fa-book-open', 'color' => '#0b6623', 'gradient' => 'linear-gradient(135deg, #0b6623, #0d7a2b)'];
}

$theme = getCourseTheme($course['title']);
?>
<?php include 'includes/header.php'; ?>

<!-- Course Banner -->
<div class="py-5 text-white position-relative" style="background: <?php echo $theme['gradient']; ?>;">
  <div class="container position-relative" style="z-index: 2;">
    <div class="d-flex align-items-center gap-2 mb-3">
      <a href="courses.php" class="text-white-50 text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>All Courses</a>
      <span class="text-white-50">/</span>
      <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($course['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="row align-items-center">
      <div class="col-lg-8">
        <h1 class="fw-bold display-5 mb-3"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="lead opacity-90 mb-4"><?php echo htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="d-flex flex-wrap gap-4 align-items-center small">
          <span><i class="fa-solid fa-user-tie me-1"></i> Facilitator: <strong><?php echo htmlspecialchars($course['facilitator_name'] ?? 'Hub Staff', ENT_QUOTES, 'UTF-8'); ?></strong></span>
          <span><i class="fa-solid fa-layer-group me-1"></i> <strong><?php echo (int)$course['module_count']; ?></strong> Modules</span>
          <span><i class="fa-solid fa-users me-1"></i> <strong><?php echo (int)$course['student_count']; ?></strong> Students</span>
        </div>
      </div>
      <div class="col-lg-4 text-center mt-4 mt-lg-0">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow" style="width: 130px; height: 130px; background: rgba(255,255,255,0.18); backdrop-filter: blur(8px);">
          <i class="<?php echo $theme['icon']; ?> fa-4x text-white"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container my-5">
  <div class="row g-4">
    <!-- Main Content: Modules & Curriculum -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
        <h3 class="fw-bold text-hub mb-3"><i class="fa-solid fa-book-bookmark me-2"></i>Curriculum &amp; Learning Modules</h3>
        <p class="text-muted">Explore the syllabus and materials uploaded for this program.</p>

        <?php if ($modules->num_rows > 0): ?>
          <div class="accordion accordion-flush" id="curriculumAccordion">
            <?php $i = 1; while ($m = $modules->fetch_assoc()): ?>
              <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
                <h2 class="accordion-header" id="heading<?php echo $m['id']; ?>">
                  <button class="accordion-button <?php echo $i > 1 ? 'collapsed' : ''; ?> fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $m['id']; ?>">
                    <span class="badge bg-hub text-white me-2">Module <?php echo $i; ?></span>
                    <?php echo htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                </h2>
                <div id="collapse<?php echo $m['id']; ?>" class="accordion-collapse collapse <?php echo $i === 1 ? 'show' : ''; ?>" data-bs-parent="#curriculumAccordion">
                  <div class="accordion-body bg-light">
                    <?php if (!empty($m['content'])): ?>
                      <div class="mb-3">
                        <h6 class="fw-bold text-muted small text-uppercase">Overview / Notes</h6>
                        <p class="mb-0 text-secondary" style="white-space: pre-line;"><?php echo htmlspecialchars($m['content'], ENT_QUOTES, 'UTF-8'); ?></p>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($m['file_path']) && file_exists(__DIR__ . '/' . $m['file_path'])): ?>
                      <?php 
                        $ext = strtolower(pathinfo($m['file_path'], PATHINFO_EXTENSION));
                        $fileIcon = 'fa-file-lines';
                        if ($ext === 'pdf') $fileIcon = 'fa-file-pdf text-danger';
                        elseif (in_array($ext, ['doc', 'docx'])) $fileIcon = 'fa-file-word text-primary';
                        elseif (in_array($ext, ['zip', 'rar'])) $fileIcon = 'fa-file-zipper text-warning';
                        elseif (in_array($ext, ['ppt', 'pptx'])) $fileIcon = 'fa-file-powerpoint text-danger';
                        elseif ($ext === 'mp4') $fileIcon = 'fa-file-video text-info';
                        
                        $canAccessMaterial = in_array(($_SESSION['role'] ?? ''), ['admin', 'facilitator']) || ($isEnrolled && in_array($enrollmentStatus, ['enrolled', 'completed']));
                      ?>
                      <div class="p-3 bg-white border rounded d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                          <i class="fa-solid <?php echo $fileIcon; ?> fa-2x"></i>
                          <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars(basename($m['file_path']), ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="text-muted">Format: <?php echo strtoupper($ext); ?> (Uploaded: <?php echo date('M d, Y', strtotime($m['uploaded_at'])); ?>)</small>
                          </div>
                        </div>
                        <?php if ($canAccessMaterial): ?>
                          <a href="<?php echo htmlspecialchars($m['file_path'], ENT_QUOTES, 'UTF-8'); ?>" download class="btn btn-outline-success btn-sm">
                            <i class="fa-solid fa-download me-1"></i>Download
                          </a>
                        <?php elseif ($isEnrolled && $enrollmentStatus === 'pending'): ?>
                          <span class="badge bg-light text-muted border py-2 px-3 small">
                            <i class="fa-solid fa-lock text-warning me-1"></i>Locked (Approval Pending)
                          </span>
                        <?php else: ?>
                          <span class="badge bg-light text-muted border py-2 px-3 small">
                            <i class="fa-solid fa-lock me-1"></i>Enroll to Access
                          </span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php $i++; endwhile; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info py-4 text-center">
            <i class="fa-solid fa-hourglass-half fa-2x mb-2 text-info d-block"></i>
            <h6>Curriculum Modules are being updated</h6>
            <p class="small mb-0 text-muted">The instructor is preparing downloadable resources for this course. Check back soon!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sidebar: Enrollment & Actions -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm p-4 sticky-top" style="border-radius: 16px; top: 20px;">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-graduation-cap me-2 text-hub"></i>Course Enrollment</h5>

        <?php if ($isEnrolled && $enrollmentStatus === 'pending'): ?>
          <div class="alert alert-warning border-0 shadow-sm rounded-3 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="fa-solid fa-clock-rotate-left fa-lg text-warning"></i>
              <strong class="text-dark">Enrollment Pending</strong>
            </div>
            <p class="small text-secondary mb-0">Your request to join this course is awaiting administrator approval. You will receive access to lessons and assessments as soon as an admin approves your enrollment.</p>
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-secondary fw-semibold py-2" disabled title="Waiting for administrator to grant access">
              <i class="fa-solid fa-lock me-2"></i>Awaiting Admin Access
            </button>
            <a href="student_dashboard.php" class="btn btn-outline-secondary">
              <i class="fa-solid fa-user me-2"></i>Go to Student Portal
            </a>
          </div>

        <?php elseif ($isEnrolled && $enrollmentStatus === 'rejected'): ?>
          <div class="alert alert-danger border-0 shadow-sm rounded-3 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="fa-solid fa-ban fa-lg text-danger"></i>
              <strong class="text-dark">Access Declined</strong>
            </div>
            <p class="small text-secondary mb-0">Your enrollment access was declined by the administrator. You may submit a new request if needed.</p>
          </div>
          <div class="d-grid gap-2">
            <a href="student_dashboard.php?enroll_id=<?php echo (int)$course_id; ?>" class="btn btn-warning fw-bold">
              <i class="fa-solid fa-rotate-right me-2"></i>Request Access Again
            </a>
            <a href="student_dashboard.php" class="btn btn-outline-secondary">
              <i class="fa-solid fa-user me-2"></i>Go to Student Portal
            </a>
          </div>

        <?php elseif ($isEnrolled): ?>
          <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-circle-check fa-lg"></i>
            <div>
              <strong>You have access!</strong><br>
              <small>Status: <?php echo ucfirst($enrollmentStatus); ?></small>
            </div>
          </div>
          <div class="d-grid gap-2">
            <?php if ($enrollmentStatus === 'completed'): ?>
              <a href="generate_certificate.php?course_id=<?php echo (int)$course_id; ?>" class="btn btn-warning fw-bold">
                <i class="fa-solid fa-award me-2"></i>Download Certificate
              </a>
            <?php else: ?>
              <a href="take_quiz.php?course_id=<?php echo (int)$course_id; ?>" class="btn btn-hub fw-bold">
                <i class="fa-solid fa-pen-to-square me-2"></i>Take Assessment
              </a>
            <?php endif; ?>
            <a href="student_dashboard.php" class="btn btn-outline-secondary">
              <i class="fa-solid fa-user me-2"></i>Go to Student Portal
            </a>
          </div>
        <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student'): ?>
          <p class="text-muted small">Enroll in this course today for 100% free access to all lessons, resources, and certification.</p>
          <div class="d-grid gap-2">
            <a href="student_dashboard.php?enroll_id=<?php echo (int)$course_id; ?>" class="btn btn-hub btn-lg fw-bold">
              <i class="fa-solid fa-user-plus me-2"></i>Enroll Now (Free)
            </a>
          </div>
        <?php elseif (isset($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), ['admin', 'facilitator'])): ?>
          <div class="alert alert-secondary small mb-3">
            <i class="fa-solid fa-shield-halved me-1"></i> You are logged in as <strong><?php echo ucfirst($_SESSION['role']); ?></strong>.
          </div>
          <div class="d-grid gap-2">
            <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'facilitator_dashboard.php'; ?>" class="btn btn-hub">
              <i class="fa-solid fa-gauge me-2"></i>Manage Courses in Dashboard
            </a>
          </div>
        <?php else: ?>
          <p class="text-muted small">Join thousands of students learning free technical and digital skills at Kibiya Hub.</p>
          <div class="d-grid gap-2">
            <a href="login.php" class="btn btn-hub btn-lg fw-bold">
              <i class="fa-solid fa-right-to-bracket me-2"></i>Login to Enroll
            </a>
            <a href="register.php" class="btn btn-outline-success">
              <i class="fa-solid fa-user-plus me-2"></i>Create Free Account
            </a>
          </div>
        <?php endif; ?>

        <hr class="my-4">

        <h6 class="fw-bold mb-3">Course Key Features:</h6>
        <ul class="list-unstyled small text-muted">
          <li class="mb-2"><i class="fa-solid fa-certificate text-warning me-2"></i>Verified Certificate of Completion</li>
          <li class="mb-2"><i class="fa-solid fa-download text-success me-2"></i>Downloadable Resource Files &amp; Guides</li>
          <li class="mb-2"><i class="fa-solid fa-clock text-primary me-2"></i>Self-Paced Flexible Online Learning</li>
          <li class="mb-2"><i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i>100% Free Sponsored Program</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
