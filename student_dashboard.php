<?php
require_once 'config/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.php');
    exit;
}
$student_id = (int)$_SESSION['user_id'];

if (isset($_GET['enroll_id'])) {
    $course_id = filter_input(INPUT_GET, 'enroll_id', FILTER_VALIDATE_INT);
    if ($course_id) {
        $courseCheck = $conn->prepare("SELECT id, title FROM courses WHERE id = ?");
        $courseCheck->bind_param("i", $course_id);
        $courseCheck->execute();
        $courseRes = $courseCheck->get_result();
        if ($courseRow = $courseRes->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = IF(status = 'rejected', 'pending', status)");
            $stmt->bind_param("ii", $student_id, $course_id);
            if ($stmt->execute()) {
                $_SESSION['enroll_msg'] = "<div class='alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-4 p-3' role='alert'><div class='d-flex align-items-center gap-3'><i class='fa-solid fa-clock-rotate-left fa-2x text-warning'></i><div><h6 class='fw-bold mb-1 text-dark'>Enrollment Requested for " . htmlspecialchars($courseRow['title'], ENT_QUOTES, 'UTF-8') . "</h6><p class='mb-0 small text-secondary'>Your enrollment is currently <strong>Pending Administrator Approval</strong>. You will be able to access the learning modules and take assessments as soon as the admin approves your access.</p></div></div><button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
            $stmt->close();
        }
        $courseCheck->close();
    }
    header('Location: student_dashboard.php');
    exit;
}

$stmt = $conn->prepare("SELECT e.*, c.title AS course_title, c.description FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? ORDER BY e.enrolled_at DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollments = $stmt->get_result();
?>
<?php include 'includes/header.php'; ?>

<?php
  // Fetch current profile image from session
  $studentProfileImg = $_SESSION['profile_image'] ?? '';
  $studentProfileImgExists = ($studentProfileImg !== '' && file_exists(__DIR__ . '/' . $studentProfileImg));
  // Flash message from upload_profile_picture.php
  $profileMsg = '';
  if (!empty($_SESSION['profile_msg'])) {
      $profileMsg = $_SESSION['profile_msg'];
      unset($_SESSION['profile_msg']);
  }
  // Flash message from enrollment
  $enrollMsg = '';
  if (!empty($_SESSION['enroll_msg'])) {
      $enrollMsg = $_SESSION['enroll_msg'];
      unset($_SESSION['enroll_msg']);
  }
  // Flash message from quiz attempts
  $quizErrorMsg = '';
  if (!empty($_SESSION['quiz_error'])) {
      $quizErrorMsg = "<div class='alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 mb-3' role='alert'><i class='fa-solid fa-lock me-2 text-warning'></i>" . htmlspecialchars($_SESSION['quiz_error'], ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
      unset($_SESSION['quiz_error']);
  }
?>

<style>
  .profile-card { border-radius: 20px; overflow: hidden; }
  .profile-avatar-lg {
    width: 100px; height: 100px; object-fit: cover; border-radius: 50%;
    border: 4px solid #0b6623; box-shadow: 0 6px 20px rgba(11,102,35,0.25);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .profile-avatar-lg:hover { transform: scale(1.05); box-shadow: 0 8px 28px rgba(11,102,35,0.35); }
  .profile-initials {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, #0b6623, #15803d);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 2.2rem; font-weight: 700;
    border: 4px solid #fff; box-shadow: 0 6px 20px rgba(11,102,35,0.25);
  }
</style>

<div class="container my-5">
  <?php echo $profileMsg; ?>
  <?php echo $enrollMsg; ?>
  <?php echo $quizErrorMsg; ?>

  <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3 pb-3 border-bottom">
    <!-- Profile Picture + Welcome -->
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="text-center">
        <?php if ($studentProfileImgExists): ?>
          <img src="<?php echo htmlspecialchars($studentProfileImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="profile-avatar-lg">
        <?php else: ?>
          <?php
            $initials = '';
            $nameParts = explode(' ', $_SESSION['full_name'] ?? 'S');
            $initials .= strtoupper(substr($nameParts[0], 0, 1));
            if (count($nameParts) > 1) $initials .= strtoupper(substr(end($nameParts), 0, 1));
          ?>
          <div class="profile-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-graduation-cap me-2 text-hub"></i>Student Portal</h2>
        <p class="text-muted mb-1">Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student', ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</p>
        <!-- Compact profile picture upload -->
        <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2 mt-1">
          <label for="studentProfilePic" class="btn btn-outline-success btn-sm fw-semibold mb-0" style="cursor:pointer;">
            <i class="fa-solid fa-camera me-1"></i><?php echo $studentProfileImgExists ? 'Change Photo' : 'Upload Photo'; ?>
          </label>
          <input type="file" name="profile_picture" id="studentProfilePic" accept="image/*" class="d-none" onchange="this.form.submit();">
        </form>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 w-100 w-md-auto mt-2 mt-md-0">
      <a href="courses.php" class="btn btn-outline-success fw-semibold flex-grow-1 flex-md-grow-0"><i class="fa-solid fa-book-open me-1"></i>Browse Courses</a>
      <a href="settings.php" class="btn btn-outline-secondary fw-semibold flex-grow-1 flex-md-grow-0"><i class="fa-solid fa-gear me-1"></i>Settings</a>
      <a href="logout.php" class="btn btn-outline-danger fw-semibold d-inline-flex align-items-center justify-content-center flex-grow-1 flex-md-grow-0" title="Log out of student session">
        <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
      </a>
    </div>
  </div>
  <h4 class="fw-bold mb-3">My Enrolled Courses</h4>
  <div class="row g-4">
    <?php if ($enrollments->num_rows > 0): ?>
      <?php while ($e = $enrollments->fetch_assoc()): ?>
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100 p-4" style="border-radius: 16px;">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="fw-bold text-hub mb-0"><?php echo htmlspecialchars($e['course_title'], ENT_QUOTES, 'UTF-8'); ?></h5>
              <?php if ($e['status'] === 'pending'): ?>
                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending Admin Approval</span>
              <?php elseif ($e['status'] === 'enrolled'): ?>
                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Active</span>
              <?php elseif ($e['status'] === 'completed'): ?>
                <span class="badge bg-primary"><i class="fa-solid fa-award me-1"></i>Completed</span>
              <?php elseif ($e['status'] === 'rejected'): ?>
                <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Access Declined</span>
              <?php else: ?>
                <span class="badge bg-secondary"><?php echo ucfirst($e['status']); ?></span>
              <?php endif; ?>
            </div>
            <p class="text-muted small"><?php echo htmlspecialchars($e['description'], ENT_QUOTES, 'UTF-8'); ?></p>

            <?php if ($e['status'] === 'pending'): ?>
              <div class="p-3 bg-light rounded-3 border-start border-warning border-4 mb-3">
                <div class="d-flex align-items-center gap-2 text-warning mb-1">
                  <i class="fa-solid fa-hourglass-half"></i>
                  <strong class="text-dark small">Waiting for Admin to give access</strong>
                </div>
                <small class="text-muted d-block">You cannot start this course yet. Once the administrator approves your enrollment, you will be able to study modules and take the assessment.</small>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-auto flex-wrap gap-2">
                <a href="course_view.php?id=<?php echo (int)$e['course_id']; ?>" class="btn btn-outline-secondary btn-sm">
                  <i class="fa-solid fa-circle-info me-1"></i>Course Syllabus
                </a>
                <button class="btn btn-secondary btn-sm" disabled title="Administrator approval required to access learning materials">
                  <i class="fa-solid fa-lock me-1"></i>Course Locked
                </button>
              </div>

            <?php elseif ($e['status'] === 'rejected'): ?>
              <div class="p-3 bg-light rounded-3 border-start border-danger border-4 mb-3">
                <small class="text-danger d-block"><i class="fa-solid fa-circle-xmark me-1"></i>Your access request was declined by the administrator.</small>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-auto flex-wrap gap-2">
                <a href="course_view.php?id=<?php echo (int)$e['course_id']; ?>" class="btn btn-outline-secondary btn-sm">
                  <i class="fa-solid fa-circle-info me-1"></i>Course Info
                </a>
                <a href="student_dashboard.php?enroll_id=<?php echo (int)$e['course_id']; ?>" class="btn btn-outline-warning btn-sm">
                  <i class="fa-solid fa-rotate-right me-1"></i>Request Again
                </a>
              </div>

            <?php else: ?>
              <div class="d-flex justify-content-between align-items-center mt-auto flex-wrap gap-2 pt-2 border-top">
                <div>
                  <a href="course_view.php?id=<?php echo (int)$e['course_id']; ?>" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-book-open me-1"></i>Study Modules
                  </a>
                </div>
                <?php if ($e['status'] === 'completed'): ?>
                  <a href="generate_certificate.php?course_id=<?php echo (int)$e['course_id']; ?>" class="btn btn-warning btn-sm fw-semibold">
                    <i class="fa-solid fa-award me-1"></i>Get Certificate
                  </a>
                <?php else: ?>
                  <a href="take_quiz.php?course_id=<?php echo (int)$e['course_id']; ?>" class="btn btn-hub btn-sm fw-semibold">
                    <i class="fa-solid fa-pen-to-square me-1"></i>Take Final Assessment
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12"><div class="alert alert-info">You have not enrolled in any courses yet. <a href="courses.php">Browse Available Courses</a></div></div>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
