<?php
require_once 'config/db.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Get all unique categories for filter
$categoriesResult = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$allCategories = [];
while ($catRow = $categoriesResult->fetch_assoc()) {
    $allCategories[] = $catRow['category'];
}

// Student enrolled course IDs
$studentEnrollments = []; // course_id => status
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    $studentId = (int)$_SESSION['user_id'];
    $enrRes = $conn->query("SELECT course_id, status FROM enrollments WHERE student_id = $studentId");
    if ($enrRes) {
        while ($r = $enrRes->fetch_assoc()) {
            $studentEnrollments[(int)$r['course_id']] = $r['status'];
        }
    }
}

// Build query
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $types .= 'ss';
}
if ($category !== '') {
    $where[] = "c.category = ?";
    $params[] = &$category;
    $types .= 's';
}

$sql = "SELECT c.*, u.full_name AS facilitator_name,
        (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS module_count,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
        FROM courses c
        LEFT JOIN users u ON c.created_by = u.id";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY c.id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result();

function getCourseCardTheme($title) {
    $t = strtolower($title);
    if (str_contains($t, 'automation') || str_contains($t, 'agent')) {
        return ['icon' => 'fa-solid fa-robot', 'gradient' => 'linear-gradient(135deg, #059669, #10b981)'];
    }
    if (str_contains($t, 'animation') || str_contains($t, 'content creation')) {
        return ['icon' => 'fa-solid fa-wand-magic-sparkles', 'gradient' => 'linear-gradient(135deg, #6d28d9, #8b5cf6)'];
    }
    if (str_contains($t, 'app development') || str_contains($t, 'mobile')) {
        return ['icon' => 'fa-solid fa-mobile-screen-button', 'gradient' => 'linear-gradient(135deg, #1d4ed8, #3b82f6)'];
    }
    if (str_contains($t, 'cloud')) {
        return ['icon' => 'fa-solid fa-cloud', 'gradient' => 'linear-gradient(135deg, #0369a1, #0284c7)'];
    }
    if (str_contains($t, 'cybersecurity') || str_contains($t, 'security')) {
        return ['icon' => 'fa-solid fa-shield-halved', 'gradient' => 'linear-gradient(135deg, #b91c1c, #ef4444)'];
    }
    if (str_contains($t, 'data analysis') || str_contains($t, 'analytics')) {
        return ['icon' => 'fa-solid fa-chart-pie', 'gradient' => 'linear-gradient(135deg, #d97706, #f59e0b)'];
    }
    if (str_contains($t, 'dev ops') || str_contains($t, 'devops')) {
        return ['icon' => 'fa-solid fa-gears', 'gradient' => 'linear-gradient(135deg, #4338ca, #6366f1)'];
    }
    if (str_contains($t, 'digital marketing') || str_contains($t, 'marketing')) {
        return ['icon' => 'fa-solid fa-bullhorn', 'gradient' => 'linear-gradient(135deg, #be185d, #ec4899)'];
    }
    if (str_contains($t, 'graphics design') || str_contains($t, 'graphic')) {
        return ['icon' => 'fa-solid fa-palette', 'gradient' => 'linear-gradient(135deg, #7e22ce, #a855f7)'];
    }
    if (str_contains($t, 'machine learning')) {
        return ['icon' => 'fa-solid fa-brain', 'gradient' => 'linear-gradient(135deg, #0891b2, #06b6d4)'];
    }
    if (str_contains($t, 'python')) {
        return ['icon' => 'fa-brands fa-python', 'gradient' => 'linear-gradient(135deg, #0284c7, #38bdf8)'];
    }
    if (str_contains($t, 'ui/ux') || str_contains($t, 'ux')) {
        return ['icon' => 'fa-solid fa-bezier-curve', 'gradient' => 'linear-gradient(135deg, #e11d48, #f43f5e)'];
    }
    if (str_contains($t, 'web development') || str_contains($t, 'web')) {
        return ['icon' => 'fa-solid fa-code', 'gradient' => 'linear-gradient(135deg, #0b6623, #15803d)'];
    }
    if (str_contains($t, 'livestock') || str_contains($t, 'farming')) {
        return ['icon' => 'fa-solid fa-cow', 'gradient' => 'linear-gradient(135deg, #4d7c0f, #65a30d)'];
    }
    if (str_contains($t, 'hardware') || str_contains($t, 'gsm')) {
        return ['icon' => 'fa-solid fa-microchip', 'gradient' => 'linear-gradient(135deg, #b45309, #f59e0b)'];
    }
    if (str_contains($t, 'leather')) {
        return ['icon' => 'fa-solid fa-scissors', 'gradient' => 'linear-gradient(135deg, #7c2d12, #c2410c)'];
    }
    if (str_contains($t, 'satellite') || str_contains($t, 'cctv')) {
        return ['icon' => 'fa-solid fa-satellite-dish', 'gradient' => 'linear-gradient(135deg, #0369a1, #0ea5e9)'];
    }
    if (str_contains($t, 'networking')) {
        return ['icon' => 'fa-solid fa-network-wired', 'gradient' => 'linear-gradient(135deg, #1e40af, #2563eb)'];
    }
    if (str_contains($t, 'catering') || str_contains($t, 'hospitality')) {
        return ['icon' => 'fa-solid fa-utensils', 'gradient' => 'linear-gradient(135deg, #c2410c, #ea580c)'];
    }
    if (str_contains($t, 'refrigerator') || str_contains($t, 'air-condition')) {
        return ['icon' => 'fa-solid fa-snowflake', 'gradient' => 'linear-gradient(135deg, #0369a1, #38bdf8)'];
    }
    return ['icon' => 'fa-solid fa-book-open', 'gradient' => 'linear-gradient(135deg, #0b6623, #0d7a2b)'];
}
?>
<?php include 'includes/header.php'; ?>

<!-- Header Banner -->
<div class="bg-light py-5 border-bottom">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <span class="badge bg-hub text-white px-3 py-2 rounded-pill mb-2"><i class="fa-solid fa-graduation-cap me-1"></i> Kibiya Hub Curriculum</span>
        <h1 class="fw-bold text-hub display-5"><i class="fa-solid fa-book-open me-2"></i>Explore All Courses</h1>
        <p class="text-muted mb-0 lead">Browse our 20+ free tech, digital, and vocational training programmes designed for career and entrepreneurial excellence.</p>
      </div>
      <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
        <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin'): ?>
          <a href="admin_dashboard.php" class="btn btn-warning fw-bold"><i class="fa-solid fa-plus-circle me-1"></i>Add / Manage Courses</a>
        <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student'): ?>
          <a href="student_dashboard.php" class="btn btn-hub"><i class="fa-solid fa-user-graduate me-1"></i>My Student Portal</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="container my-5">
  <!-- Search & Filter Card -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
      <form method="GET" class="row g-3 align-items-center">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0" placeholder="Search courses (e.g. AI, Python, Livestock, CCTV...)" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>
        <div class="col-md-4">
          <select name="category" class="form-select">
            <option value="">All Categories (<?php echo count($allCategories); ?>)</option>
            <?php foreach ($allCategories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-hub flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filter</button>
          <?php if ($search !== '' || $category !== ''): ?>
            <a href="courses.php" class="btn btn-outline-secondary" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Category quick pill badges -->
      <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
        <small class="text-muted align-self-center me-1">Popular:</small>
        <a href="courses.php" class="badge text-decoration-none <?php echo empty($category) ? 'bg-hub text-white' : 'bg-light text-dark border'; ?> py-2 px-3 rounded-pill">All Courses</a>
        <?php foreach (array_slice($allCategories, 0, 7) as $cat): ?>
          <a href="courses.php?category=<?php echo urlencode($cat); ?>" class="badge text-decoration-none <?php echo ($category === $cat) ? 'bg-hub text-white' : 'bg-light text-dark border'; ?> py-2 px-3 rounded-pill">
            <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Course Grid -->
  <div class="row g-4">
    <?php if ($courses->num_rows > 0): ?>
      <?php while ($c = $courses->fetch_assoc()): ?>
        <?php 
          $theme = getCourseCardTheme($c['title']);
          $courseId = (int)$c['id'];
          $enrStatus = $studentEnrollments[$courseId] ?? null;
          $isEnrolled = ($enrStatus !== null);
        ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100 d-flex flex-column" style="border-radius: 16px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 14px 34px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div class="p-4 text-center position-relative" style="background: <?php echo $theme['gradient']; ?>; min-height: 110px; display:flex; align-items:center; justify-content:center;">
              <i class="<?php echo $theme['icon']; ?> fa-3x text-white opacity-75"></i>
              <span class="position-absolute top-0 end-0 m-3 badge bg-white text-dark shadow-sm">
                #<?php echo $courseId; ?>
              </span>
            </div>
            <div class="card-body p-4 d-flex flex-column flex-grow-1">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($c['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($enrStatus === 'pending'): ?>
                  <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending Access</span>
                <?php elseif ($enrStatus === 'enrolled'): ?>
                  <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Active</span>
                <?php elseif ($enrStatus === 'completed'): ?>
                  <span class="badge bg-primary"><i class="fa-solid fa-award me-1"></i>Completed</span>
                <?php elseif ($enrStatus === 'rejected'): ?>
                  <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Declined</span>
                <?php endif; ?>
              </div>
              <h5 class="fw-bold mb-2">
                <a href="course_view.php?id=<?php echo $courseId; ?>" class="text-dark text-decoration-none">
                  <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </h5>
              <p class="text-muted small flex-grow-1"><?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 130, '...'), ENT_QUOTES, 'UTF-8'); ?></p>
              
              <div class="d-flex justify-content-between align-items-center py-2 px-3 bg-light rounded-3 mb-3 small">
                <span class="text-muted"><i class="fa-solid fa-layer-group text-hub me-1"></i><strong><?php echo (int)$c['module_count']; ?></strong> Modules</span>
                <span class="text-muted"><i class="fa-solid fa-users text-hub me-1"></i><strong><?php echo (int)$c['student_count']; ?></strong> Students</span>
              </div>

              <div class="d-flex justify-content-between align-items-center pt-2 border-top flex-wrap gap-2">
                <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-outline-secondary btn-sm">
                  <i class="fa-solid fa-circle-info me-1"></i>Details
                </a>

                <?php if ($enrStatus === 'pending'): ?>
                  <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-warning btn-sm fw-semibold">
                    <i class="fa-solid fa-clock me-1"></i>Pending Approval
                  </a>
                <?php elseif (in_array($enrStatus, ['enrolled', 'completed'])): ?>
                  <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-success btn-sm fw-semibold">
                    <i class="fa-solid fa-book-open me-1"></i>Learn Now
                  </a>
                <?php elseif ($enrStatus === 'rejected'): ?>
                  <a href="student_dashboard.php?enroll_id=<?php echo $courseId; ?>" class="btn btn-outline-warning btn-sm fw-semibold">
                    <i class="fa-solid fa-rotate-right me-1"></i>Request Again
                  </a>
                <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student'): ?>
                  <a href="student_dashboard.php?enroll_id=<?php echo $courseId; ?>" class="btn btn-hub btn-sm fw-semibold">
                    <i class="fa-solid fa-user-plus me-1"></i>Enroll Free
                  </a>
                <?php elseif (isset($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), ['admin', 'facilitator'])): ?>
                  <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-hub btn-sm">
                    <i class="fa-solid fa-eye me-1"></i>View Course
                  </a>
                <?php else: ?>
                  <a href="login.php" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>Login to Enroll
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12 text-center py-5">
        <div class="p-5 bg-light rounded-4">
          <i class="fa-solid fa-magnifying-glass fa-3x text-muted mb-3"></i>
          <h4 class="text-muted">No courses found</h4>
          <p class="text-muted">No course matches "<?php echo htmlspecialchars($search ?: $category, ENT_QUOTES, 'UTF-8'); ?>". Try searching with different keywords.</p>
          <a href="courses.php" class="btn btn-hub px-4"><i class="fa-solid fa-rotate-left me-1"></i>Reset Filters</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
