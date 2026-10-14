<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'facilitator') {
    header('Location: login.php');
    exit;
}

$message = '';
$facilitator_id = (int)$_SESSION['user_id'];

// 1. CREATE NEW COURSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $cat = trim($_POST['category'] ?? 'General');
    if ($title !== '' && $desc !== '') {
        $stmt = $conn->prepare("INSERT INTO courses (title, description, category, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $desc, $cat, $facilitator_id);
        $stmt->execute();
        $stmt->close();
        $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Course created successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please complete the course title and description.</div>";
    }
}

// 2. UPLOAD MODULE / MATERIAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_module'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $module_title = trim($_POST['module_title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $file_path = null;
    $uploadError = '';

    $ownerStmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND created_by = ?");
    $ownerStmt->bind_param("ii", $course_id, $facilitator_id);
    $ownerStmt->execute();
    $ownedCourse = $ownerStmt->get_result()->num_rows === 1;
    $ownerStmt->close();

    if (!$course_id || !$ownedCourse || $module_title === '') {
        $uploadError = 'Choose one of your assigned courses and enter a module title.';
    } elseif (!empty($_FILES['file']['name'])) {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK || $_FILES['file']['size'] > 25 * 1024 * 1024) {
            $uploadError = 'Upload failed or file exceeds the 25 MB limit.';
        } else {
            $allowed = ['pdf', 'docx', 'doc', 'zip', 'rar', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'mp4'];
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $uploadError = 'Allowed file formats: ' . implode(', ', $allowed);
            } else {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $uploadError = 'Could not create the upload directory.';
                } else {
                    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . '/' . $safeName)) {
                        $file_path = 'uploads/' . $safeName;
                    } else {
                        $uploadError = 'Could not save uploaded file.';
                    }
                }
            }
        }
    }

    if ($uploadError !== '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>" . htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8') . "</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO modules (course_id, title, file_path, content) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $course_id, $module_title, $file_path, $content);
        $stmt->execute();
        $stmt->close();
        $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Learning material module uploaded successfully!</div>";
    }
}

// 3. SET / CREATE ASSESSMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assessment'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $quiz_title = trim($_POST['title'] ?? '');
    $passing_score = filter_input(INPUT_POST, 'passing_score', FILTER_VALIDATE_INT) ?: 70;

    // Verify course ownership
    $ownerStmt = $conn->prepare("SELECT title FROM courses WHERE id = ? AND created_by = ?");
    $ownerStmt->bind_param("ii", $course_id, $facilitator_id);
    $ownerStmt->execute();
    $courseRow = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if (!$courseRow || $quiz_title === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Select one of your assigned courses and provide an assessment title.</div>";
    } else {
        // Check if an assessment already exists for this course
        $existingStmt = $conn->prepare("SELECT id FROM quizzes WHERE course_id = ?");
        $existingStmt->bind_param("i", $course_id);
        $existingStmt->execute();
        $existingQuiz = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

        if ($existingQuiz) {
            $quiz_id = (int)$existingQuiz['id'];
            $upStmt = $conn->prepare("UPDATE quizzes SET title = ?, passing_score = ? WHERE id = ?");
            $upStmt->bind_param("sii", $quiz_title, $passing_score, $quiz_id);
            $upStmt->execute();
            $upStmt->close();
        } else {
            $insStmt = $conn->prepare("INSERT INTO quizzes (course_id, title, passing_score) VALUES (?, ?, ?)");
            $insStmt->bind_param("isi", $course_id, $quiz_title, $passing_score);
            $insStmt->execute();
            $quiz_id = (int)$insStmt->insert_id;
            $insStmt->close();
        }

        // Optional first question
        $firstQ = trim($_POST['first_question'] ?? '');
        $optA = trim($_POST['option_a'] ?? '');
        $optB = trim($_POST['option_b'] ?? '');
        $optC = trim($_POST['option_c'] ?? '');
        $optD = trim($_POST['option_d'] ?? '');
        $correct = strtoupper(trim($_POST['correct_option'] ?? ''));

        if ($firstQ !== '' && $optA !== '' && $optB !== '' && in_array($correct, ['A', 'B', 'C', 'D'])) {
            $qStmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $qStmt->bind_param("issssss", $quiz_id, $firstQ, $optA, $optB, $optC, $optD, $correct);
            $qStmt->execute();
            $qStmt->close();
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Assessment created and first question saved for <strong>" . htmlspecialchars($courseRow['title'], ENT_QUOTES, 'UTF-8') . "</strong>!</div>";
        } else {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Assessment created for <strong>" . htmlspecialchars($courseRow['title'], ENT_QUOTES, 'UTF-8') . "</strong>. You can now add multiple-choice questions!</div>";
        }
    }
}

// 4. ADD QUESTION TO ASSESSMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
    $question = trim($_POST['question'] ?? '');
    $optA = trim($_POST['option_a'] ?? '');
    $optB = trim($_POST['option_b'] ?? '');
    $optC = trim($_POST['option_c'] ?? '');
    $optD = trim($_POST['option_d'] ?? '');
    $correct = strtoupper(trim($_POST['correct_option'] ?? ''));

    // Verify facilitator owns the course this quiz belongs to
    $verifyStmt = $conn->prepare("SELECT q.id, c.title AS course_title FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE q.id = ? AND c.created_by = ?");
    $verifyStmt->bind_param("ii", $quiz_id, $facilitator_id);
    $verifyStmt->execute();
    $quizOwned = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();

    if (!$quizOwned || $question === '' || $optA === '' || $optB === '' || !in_array($correct, ['A', 'B', 'C', 'D'])) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please provide the question, choices A and B, and select the correct answer.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $quiz_id, $question, $optA, $optB, $optC, $optD, $correct);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Question added successfully to the assessment for <strong>" . htmlspecialchars($quizOwned['course_title'], ENT_QUOTES, 'UTF-8') . "</strong>!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to save question.</div>";
        }
        $stmt->close();
    }
}

// 5. DELETE QUESTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
    if ($question_id) {
        $chkStmt = $conn->prepare("SELECT qq.id FROM quiz_questions qq 
            JOIN quizzes q ON qq.quiz_id = q.id 
            JOIN courses c ON q.course_id = c.id 
            WHERE qq.id = ? AND c.created_by = ?");
        $chkStmt->bind_param("ii", $question_id, $facilitator_id);
        $chkStmt->execute();
        $isOk = $chkStmt->get_result()->num_rows === 1;
        $chkStmt->close();

        if ($isOk) {
            $delStmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
            $delStmt->bind_param("i", $question_id);
            $delStmt->execute();
            $delStmt->close();
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Question deleted successfully.</div>";
        }
    }
}

// 6. DELETE ASSESSMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assessment'])) {
    $quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
    if ($quiz_id) {
        $chkStmt = $conn->prepare("SELECT q.id FROM quizzes q 
            JOIN courses c ON q.course_id = c.id 
            WHERE q.id = ? AND c.created_by = ?");
        $chkStmt->bind_param("ii", $quiz_id, $facilitator_id);
        $chkStmt->execute();
        $isOk = $chkStmt->get_result()->num_rows === 1;
        $chkStmt->close();

        if ($isOk) {
            $delStmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
            $delStmt->bind_param("i", $quiz_id);
            $delStmt->execute();
            $delStmt->close();
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Assessment deleted successfully.</div>";
        }
    }
}

// Fetch Courses assigned to this facilitator
$coursesStmt = $conn->prepare("SELECT c.*, 
    (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS module_count,
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count,
    (SELECT q.id FROM quizzes q WHERE q.course_id = c.id LIMIT 1) AS quiz_id,
    (SELECT q.title FROM quizzes q WHERE q.course_id = c.id LIMIT 1) AS quiz_title,
    (SELECT q.passing_score FROM quizzes q WHERE q.course_id = c.id LIMIT 1) AS quiz_passing_score,
    (SELECT COUNT(*) FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.id WHERE q.course_id = c.id) AS question_count
    FROM courses c WHERE c.created_by = ? ORDER BY c.id ASC");
$coursesStmt->bind_param("i", $facilitator_id);
$coursesStmt->execute();
$myCourses = $coursesStmt->get_result();

$myCoursesList = [];
$totalMyModules = 0;
$totalMyStudents = 0;
$totalMyQuizzes = 0;
$totalMyQuestions = 0;

while ($row = $myCourses->fetch_assoc()) {
    $myCoursesList[] = $row;
    $totalMyModules += (int)$row['module_count'];
    $totalMyStudents += (int)$row['student_count'];
    if ($row['quiz_id']) {
        $totalMyQuizzes++;
        $totalMyQuestions += (int)$row['question_count'];
    }
}
$coursesStmt->close();
?>
<?php include 'includes/header.php'; ?>

<?php
  // Fetch current profile image from session
  $facProfileImg = $_SESSION['profile_image'] ?? '';
  $facProfileImgExists = ($facProfileImg !== '' && file_exists(__DIR__ . '/' . $facProfileImg));
  // Flash message from upload_profile_picture.php
  $profileMsg = '';
  if (!empty($_SESSION['profile_msg'])) {
      $profileMsg = $_SESSION['profile_msg'];
      unset($_SESSION['profile_msg']);
  }
?>

<style>
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

  <!-- Top Header -->
  <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <!-- Profile Picture + Welcome -->
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="text-center">
        <?php if ($facProfileImgExists): ?>
          <img src="<?php echo htmlspecialchars($facProfileImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="profile-avatar-lg">
        <?php else: ?>
          <?php
            $initials = '';
            $nameParts = explode(' ', $_SESSION['full_name'] ?? 'F');
            $initials .= strtoupper(substr($nameParts[0], 0, 1));
            if (count($nameParts) > 1) $initials .= strtoupper(substr(end($nameParts), 0, 1));
          ?>
          <div class="profile-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-chalkboard-user me-2 text-hub"></i>Facilitator Portal</h2>
        <p class="text-muted mb-1">Manage courses, upload learning materials, set student assessments, and review results.</p>
        <!-- Compact profile picture upload -->
        <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2 mt-1">
          <label for="facProfilePic" class="btn btn-outline-success btn-sm fw-semibold mb-0" style="cursor:pointer;">
            <i class="fa-solid fa-camera me-1"></i><?php echo $facProfileImgExists ? 'Change Photo' : 'Upload Photo'; ?>
          </label>
          <input type="file" name="profile_picture" id="facProfilePic" accept="image/*" class="d-none" onchange="this.form.submit();">
        </form>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 w-100 w-md-auto mt-2 mt-md-0">
      <button class="btn btn-warning fw-bold shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
        <i class="fa-solid fa-clipboard-check me-1"></i>Set Assessment
      </button>
      <button class="btn btn-hub fw-bold shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
        <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Material
      </button>
      <button class="btn btn-outline-success fw-bold shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#createCourseModal">
        <i class="fa-solid fa-plus-circle me-1"></i>New Course
      </button>
      <a href="logout.php" class="btn btn-outline-danger fw-bold shadow-sm d-inline-flex align-items-center justify-content-center flex-grow-1 flex-md-grow-0" title="Log out of facilitator session">
        <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
      </a>
    </div>
  </div>

  <?php echo $message; ?>

  <!-- Stats Grid -->
  <div class="row g-3 mb-5">
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-white" style="background: linear-gradient(135deg, #0b6623, #15803d); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo count($myCoursesList); ?></h2>
            <small class="opacity-75">My Assigned Courses</small>
          </div>
          <i class="fa-solid fa-book-open fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-dark" style="background: linear-gradient(135deg, #f4b41a, #fcd34d); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$totalMyQuizzes; ?></h2>
            <small class="text-dark-50">Active Assessments (<?php echo (int)$totalMyQuestions; ?> Qs)</small>
          </div>
          <i class="fa-solid fa-clipboard-check fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-white" style="background: linear-gradient(135deg, #0284c7, #38bdf8); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$totalMyModules; ?></h2>
            <small class="opacity-75">Uploaded Modules</small>
          </div>
          <i class="fa-solid fa-layer-group fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-white" style="background: linear-gradient(135deg, #6366f1, #818cf8); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$totalMyStudents; ?></h2>
            <small class="opacity-75">Enrolled Students</small>
          </div>
          <i class="fa-solid fa-users fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <ul class="nav nav-pills mb-4 gap-2 bg-light p-2 rounded-4 border" id="facTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active rounded-pill fw-bold" id="courses-tab" data-bs-toggle="pill" data-bs-target="#courses-pane" type="button" role="tab">
        <i class="fa-solid fa-book-bookmark me-2"></i>My Assigned Courses (<?php echo count($myCoursesList); ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="assessments-tab" data-bs-toggle="pill" data-bs-target="#assessments-pane" type="button" role="tab">
        <i class="fa-solid fa-clipboard-check me-2"></i>Course Assessments &amp; Quizzes (<?php echo (int)$totalMyQuizzes; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="quickactions-tab" data-bs-toggle="pill" data-bs-target="#quickactions-pane" type="button" role="tab">
        <i class="fa-solid fa-plus-circle me-2"></i>Create Course &amp; Upload Tools
      </button>
    </li>
  </ul>

  <!-- Tab Content -->
  <div class="tab-content" id="facTabsContent">

    <!-- PANE 1: MY COURSES & SUMMARY -->
    <div class="tab-pane fade show active" id="courses-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0">My Teaching Curriculum</h5>
            <small class="text-muted">Courses assigned to you for module authoring and student testing</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
              <i class="fa-solid fa-clipboard-check me-1"></i>Set Assessment
            </button>
            <button class="btn btn-hub btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
              <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Material
            </button>
          </div>
        </div>

        <?php if (!empty($myCoursesList)): ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 45px;">#</th>
                  <th>Course Title</th>
                  <th>Category</th>
                  <th>Curriculum Modules</th>
                  <th>Assessment Status</th>
                  <th>Students</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($myCoursesList as $c): ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border">#<?php echo (int)$c['id']; ?></span></td>
                    <td>
                      <div class="fw-bold">
                        <a href="course_view.php?id=<?php echo (int)$c['id']; ?>" class="text-dark text-decoration-none" title="Preview on course page">
                          <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      </div>
                      <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 75, '...'), ENT_QUOTES, 'UTF-8'); ?></small>
                    </td>
                    <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($c['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td>
                      <span class="badge bg-hub text-white">
                        <i class="fa-solid fa-layer-group me-1"></i><?php echo (int)$c['module_count']; ?> Modules
                      </span>
                    </td>
                    <td>
                      <?php if ($c['quiz_id']): ?>
                        <div>
                          <span class="badge bg-success mb-1">
                            <i class="fa-solid fa-check-circle me-1"></i>Ready (<?php echo (int)$c['question_count']; ?> Qs)
                          </span>
                          <div class="small text-muted" style="font-size:0.75rem;">Passing: <?php echo (int)$c['quiz_passing_score']; ?>%</div>
                        </div>
                      <?php else: ?>
                        <div>
                          <span class="badge bg-danger mb-1">
                            <i class="fa-solid fa-circle-exclamation me-1"></i>No Assessment
                          </span>
                          <button class="btn btn-outline-warning btn-sm py-0 px-2 d-block" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#createAssessmentModal" onclick="setAssessmentCourse(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')">
                            + Set Assessment
                          </button>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-light text-secondary border">
                        <i class="fa-solid fa-user-graduate me-1"></i><?php echo (int)$c['student_count']; ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <?php if ($c['quiz_id']): ?>
                          <button class="btn btn-outline-success" title="Add Question to Assessment" data-bs-toggle="modal" data-bs-target="#addQuestionModal<?php echo (int)$c['quiz_id']; ?>">
                            <i class="fa-solid fa-plus-circle"></i>
                          </button>
                          <button class="btn btn-outline-primary" title="View / Manage Assessment Questions" data-bs-toggle="modal" data-bs-target="#viewQuestionsModal<?php echo (int)$c['quiz_id']; ?>">
                            <i class="fa-solid fa-list-check"></i>
                          </button>
                        <?php else: ?>
                          <button class="btn btn-outline-warning" title="Set Assessment" data-bs-toggle="modal" data-bs-target="#createAssessmentModal" onclick="setAssessmentCourse(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')">
                            <i class="fa-solid fa-clipboard-check"></i>
                          </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary" title="Upload Material to Course" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal" onclick="setUploadCourse(<?php echo (int)$c['id']; ?>)">
                          <i class="fa-solid fa-upload"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info py-5 text-center mb-0">
            <i class="fa-solid fa-chalkboard-user fa-3x mb-3 text-info"></i>
            <h5>No Courses Assigned to You Yet</h5>
            <p class="text-muted mb-3">When the Hub Administrator assigns courses to you, they will immediately appear here for curriculum and assessment management.</p>
            <button class="btn btn-hub fw-bold" data-bs-toggle="modal" data-bs-target="#createCourseModal">
              <i class="fa-solid fa-plus-circle me-1"></i>Create a New Course
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PANE 2: ASSESSMENTS & QUIZZES MANAGEMENT -->
    <div class="tab-pane fade" id="assessments-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-check me-2 text-hub"></i>Course Assessment Manager</h5>
            <small class="text-muted">Create assessments, configure passing scores, and author multiple-choice exam questions</small>
          </div>
          <button class="btn btn-warning btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
            <i class="fa-solid fa-plus-circle me-1"></i>Set New Assessment
          </button>
        </div>

        <?php 
          // Fetch quizzes for courses owned by facilitator
          $qListStmt = $conn->prepare("SELECT q.*, c.title AS course_title, c.category AS course_category,
              (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
              FROM quizzes q 
              JOIN courses c ON q.course_id = c.id 
              WHERE c.created_by = ? 
              ORDER BY q.id ASC");
          $qListStmt->bind_param("i", $facilitator_id);
          $qListStmt->execute();
          $quizzesResult = $qListStmt->get_result();
        ?>

        <?php if ($quizzesResult->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Course</th>
                  <th>Assessment Title</th>
                  <th>Passing Standard</th>
                  <th>Total Questions</th>
                  <th>Live Student Test</th>
                  <th class="text-end" style="min-width: 180px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($qz = $quizzesResult->fetch_assoc()): ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border">#<?php echo (int)$qz['id']; ?></span></td>
                    <td>
                      <strong class="text-hub"><?php echo htmlspecialchars($qz['course_title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="small text-muted"><?php echo htmlspecialchars($qz['course_category'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars($qz['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <small class="text-muted">Created <?php echo date('M d, Y', strtotime($qz['created_at'])); ?></small>
                    </td>
                    <td>
                      <span class="badge bg-warning text-dark px-3 py-1">
                        <?php echo (int)$qz['passing_score']; ?>% to Pass
                      </span>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewQuestionsModal<?php echo (int)$qz['id']; ?>">
                        <i class="fa-solid fa-list-check me-1"></i><?php echo (int)$qz['question_count']; ?> Questions
                      </button>
                    </td>
                    <td>
                      <a href="take_quiz.php?course_id=<?php echo (int)$qz['course_id']; ?>" class="btn btn-sm btn-outline-success">
                        <i class="fa-solid fa-play me-1"></i>Preview Quiz
                      </a>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-success" title="Add Question" data-bs-toggle="modal" data-bs-target="#addQuestionModal<?php echo (int)$qz['id']; ?>">
                          <i class="fa-solid fa-plus-circle"></i>
                        </button>
                        <button class="btn btn-outline-primary" title="View / Manage Questions" data-bs-toggle="modal" data-bs-target="#viewQuestionsModal<?php echo (int)$qz['id']; ?>">
                          <i class="fa-solid fa-eye"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this assessment and all its questions?');" style="display:inline;">
                          <input type="hidden" name="quiz_id" value="<?php echo (int)$qz['id']; ?>">
                          <button type="submit" name="delete_assessment" class="btn btn-outline-danger" title="Delete Assessment">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL: ADD QUESTION TO THIS QUIZ -->
                  <div class="modal fade" id="addQuestionModal<?php echo (int)$qz['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <form method="POST">
                          <input type="hidden" name="quiz_id" value="<?php echo (int)$qz['id']; ?>">
                          <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fw-bold">
                              <i class="fa-solid fa-plus-circle me-2"></i>Add Question to: <?php echo htmlspecialchars($qz['course_title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="mb-3">
                              <label class="form-label fw-bold">Question Prompt <span class="text-danger">*</span></label>
                              <textarea name="question" class="form-control" rows="3" placeholder="Enter the question text..." required></textarea>
                            </div>

                            <div class="row g-3">
                              <div class="col-md-6">
                                <label class="form-label fw-semibold">Option A <span class="text-danger">*</span></label>
                                <input type="text" name="option_a" class="form-control" placeholder="Choice A" required>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-semibold">Option B <span class="text-danger">*</span></label>
                                <input type="text" name="option_b" class="form-control" placeholder="Choice B" required>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-semibold">Option C</label>
                                <input type="text" name="option_c" class="form-control" placeholder="Choice C">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-semibold">Option D</label>
                                <input type="text" name="option_d" class="form-control" placeholder="Choice D">
                              </div>
                            </div>

                            <div class="mt-4 p-3 bg-light rounded border">
                              <label class="form-label fw-bold text-dark mb-2">Select Correct Answer Option <span class="text-danger">*</span></label>
                              <div class="d-flex gap-4">
                                <div class="form-check">
                                  <input class="form-check-input" type="radio" name="correct_option" value="A" id="corrA_<?php echo (int)$qz['id']; ?>" required checked>
                                  <label class="form-check-label fw-bold" for="corrA_<?php echo (int)$qz['id']; ?>">Option A</label>
                                </div>
                                <div class="form-check">
                                  <input class="form-check-input" type="radio" name="correct_option" value="B" id="corrB_<?php echo (int)$qz['id']; ?>">
                                  <label class="form-check-label fw-bold" for="corrB_<?php echo (int)$qz['id']; ?>">Option B</label>
                                </div>
                                <div class="form-check">
                                  <input class="form-check-input" type="radio" name="correct_option" value="C" id="corrC_<?php echo (int)$qz['id']; ?>">
                                  <label class="form-check-label fw-bold" for="corrC_<?php echo (int)$qz['id']; ?>">Option C</label>
                                </div>
                                <div class="form-check">
                                  <input class="form-check-input" type="radio" name="correct_option" value="D" id="corrD_<?php echo (int)$qz['id']; ?>">
                                  <label class="form-check-label fw-bold" for="corrD_<?php echo (int)$qz['id']; ?>">Option D</label>
                                </div>
                              </div>
                            </div>
                          </div>
                          <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_question" class="btn btn-success fw-bold px-4">
                              <i class="fa-solid fa-save me-1"></i>Save Question
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- MODAL: VIEW / MANAGE QUESTIONS -->
                  <div class="modal fade" id="viewQuestionsModal<?php echo (int)$qz['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                          <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-list-check me-2 text-hub"></i>Questions for <?php echo htmlspecialchars($qz['course_title'], ENT_QUOTES, 'UTF-8'); ?>
                          </h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                          <?php 
                            $questionsQuery = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
                            $questionsQuery->bind_param("i", $qz['id']);
                            $questionsQuery->execute();
                            $qList = $questionsQuery->get_result();
                          ?>

                          <?php if ($qList->num_rows > 0): ?>
                            <div class="list-group">
                              <?php $qNum = 1; while ($quest = $qList->fetch_assoc()): ?>
                                <div class="list-group-item p-3 mb-2 border rounded shadow-sm">
                                  <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="fw-bold mb-0">
                                      <span class="badge bg-hub text-white me-2">Q<?php echo $qNum; ?></span>
                                      <?php echo htmlspecialchars($quest['question'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h6>
                                    <form method="POST" onsubmit="return confirm('Delete this question?');">
                                      <input type="hidden" name="question_id" value="<?php echo (int)$quest['id']; ?>">
                                      <button type="submit" name="delete_question" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Question">
                                        <i class="fa-solid fa-trash"></i>
                                      </button>
                                    </form>
                                  </div>

                                  <div class="row g-2 small mt-2">
                                    <div class="col-md-6">
                                      <div class="p-2 rounded <?php echo ($quest['correct_option'] === 'A') ? 'bg-success text-white fw-bold' : 'bg-light border'; ?>">
                                        A: <?php echo htmlspecialchars($quest['option_a'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($quest['correct_option'] === 'A'): ?><i class="fa-solid fa-check ms-1"></i><?php endif; ?>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="p-2 rounded <?php echo ($quest['correct_option'] === 'B') ? 'bg-success text-white fw-bold' : 'bg-light border'; ?>">
                                        B: <?php echo htmlspecialchars($quest['option_b'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($quest['correct_option'] === 'B'): ?><i class="fa-solid fa-check ms-1"></i><?php endif; ?>
                                      </div>
                                    </div>
                                    <?php if (!empty($quest['option_c'])): ?>
                                      <div class="col-md-6">
                                        <div class="p-2 rounded <?php echo ($quest['correct_option'] === 'C') ? 'bg-success text-white fw-bold' : 'bg-light border'; ?>">
                                          C: <?php echo htmlspecialchars($quest['option_c'], ENT_QUOTES, 'UTF-8'); ?>
                                          <?php if ($quest['correct_option'] === 'C'): ?><i class="fa-solid fa-check ms-1"></i><?php endif; ?>
                                        </div>
                                      </div>
                                    <?php endif; ?>
                                    <?php if (!empty($quest['option_d'])): ?>
                                      <div class="col-md-6">
                                        <div class="p-2 rounded <?php echo ($quest['correct_option'] === 'D') ? 'bg-success text-white fw-bold' : 'bg-light border'; ?>">
                                          D: <?php echo htmlspecialchars($quest['option_d'], ENT_QUOTES, 'UTF-8'); ?>
                                          <?php if ($quest['correct_option'] === 'D'): ?><i class="fa-solid fa-check ms-1"></i><?php endif; ?>
                                        </div>
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                </div>
                              <?php $qNum++; endwhile; ?>
                            </div>
                          <?php else: ?>
                            <div class="alert alert-warning text-center py-4">
                              <i class="fa-solid fa-clipboard-question fa-2x mb-2 text-warning"></i>
                              <p class="mb-2">This assessment has no questions yet. Students will not be able to test until questions are added.</p>
                              <button class="btn btn-success btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addQuestionModal<?php echo (int)$qz['id']; ?>">
                                <i class="fa-solid fa-plus-circle me-1"></i>Add First Question
                              </button>
                            </div>
                          <?php endif; ?>
                          <?php $questionsQuery->close(); ?>
                        </div>
                        <div class="modal-footer bg-light justify-content-between">
                          <button class="btn btn-success btn-sm fw-semibold" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addQuestionModal<?php echo (int)$qz['id']; ?>">
                            <i class="fa-solid fa-plus me-1"></i>Add More Questions
                          </button>
                          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>
                  </div>

                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info text-center py-5 mb-0">
            <i class="fa-solid fa-clipboard-check fa-3x mb-3 text-info"></i>
            <h5>No Assessments Created Yet</h5>
            <p class="text-muted mb-3">Set an assessment for your assigned courses so students can take their final exam and earn certificates.</p>
            <button class="btn btn-warning fw-bold px-4" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
              <i class="fa-solid fa-plus-circle me-1"></i>Set Your First Assessment
            </button>
          </div>
        <?php endif; ?>
        <?php $qListStmt->close(); ?>
      </div>
    </div>

    <!-- PANE 3: CREATE COURSE & UPLOAD TOOLS -->
    <div class="tab-pane fade" id="quickactions-pane" role="tabpanel">
      <div class="row g-4">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 16px;">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-plus-circle me-2 text-hub"></i>Create New Course</h5>
            <form method="POST">
              <div class="mb-3">
                <label class="form-label fw-semibold">Course Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Mobile Robotics Fundamentals" required maxlength="150">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Category</label>
                <input type="text" name="category" class="form-control" placeholder="e.g. Technical &amp; Engineering" maxlength="50">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Course Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Syllabus overview and goals..." required></textarea>
              </div>
              <button type="submit" name="create_course" class="btn btn-hub w-100 fw-bold py-2">
                <i class="fa-solid fa-save me-1"></i>Publish Course
              </button>
            </form>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 16px;">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-cloud-arrow-up me-2 text-warning"></i>Upload Course Module</h5>
            <form method="POST" enctype="multipart/form-data">
              <div class="mb-3">
                <label class="form-label fw-semibold">Select Course</label>
                <select name="course_id" id="quickUploadCourseSelect" class="form-select" required>
                  <option value="">-- Choose Course --</option>
                  <?php foreach ($myCoursesList as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Module Title</label>
                <input type="text" name="module_title" class="form-control" placeholder="e.g. Module 1: Foundational Practical Guide" required maxlength="150">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Module Description / Text Content</label>
                <textarea name="content" class="form-control" rows="2" placeholder="Lesson outline or study notes..."></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Attach Material (PDF, DOCX, ZIP, TXT, PPTX; max 25 MB)</label>
                <input type="file" name="file" class="form-control" accept=".pdf,.docx,.doc,.zip,.rar,.txt,.ppt,.pptx,.xls,.xlsx,.mp4">
              </div>
              <button type="submit" name="upload_module" class="btn btn-warning w-100 fw-bold py-2">
                <i class="fa-solid fa-upload me-1"></i>Upload Learning Material
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ================= MODAL: SET / CREATE ASSESSMENT ================= -->
<div class="modal fade" id="createAssessmentModal" tabindex="-1" aria-labelledby="createAssessmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold" id="createAssessmentModalLabel">
            <i class="fa-solid fa-clipboard-check me-2"></i>Set Course Assessment
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-bold">Target Course <span class="text-danger">*</span></label>
              <select name="course_id" id="modalAssessmentCourseSelect" class="form-select" required onchange="updateDefaultAssessmentTitle(this)">
                <option value="">-- Choose Course to Assess --</option>
                <?php foreach ($myCoursesList as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>" data-title="<?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    #<?php echo (int)$c['id']; ?> - <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($c['quiz_id']): ?> (Existing: <?php echo (int)$c['question_count']; ?> Qs)<?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Passing Score (%) <span class="text-danger">*</span></label>
              <input type="number" name="passing_score" class="form-control" value="70" min="40" max="100" required>
              <div class="form-text small">Default is 70% to earn certificate.</div>
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Assessment Title <span class="text-danger">*</span></label>
              <input type="text" name="title" id="modalAssessmentTitleInput" class="form-control" placeholder="e.g. Final Mastery Assessment" required maxlength="150">
            </div>

            <!-- Optional First Question -->
            <div class="col-12">
              <div class="border rounded p-3 bg-light mt-2">
                <h6 class="fw-bold mb-3 text-hub"><i class="fa-solid fa-question-circle me-1"></i>Add Initial Question (Optional)</h6>
                <div class="mb-3">
                  <label class="form-label small fw-semibold">Question Prompt</label>
                  <textarea name="first_question" class="form-control form-control-sm" rows="2" placeholder="e.g. What is the fundamental concept of this course?"></textarea>
                </div>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label small">Option A</label>
                    <input type="text" name="option_a" class="form-control form-control-sm" placeholder="Choice A">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Option B</label>
                    <input type="text" name="option_b" class="form-control form-control-sm" placeholder="Choice B">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Option C</label>
                    <input type="text" name="option_c" class="form-control form-control-sm" placeholder="Choice C">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Option D</label>
                    <input type="text" name="option_d" class="form-control form-control-sm" placeholder="Choice D">
                  </div>
                  <div class="col-12 mt-2">
                    <label class="form-label small fw-bold">Correct Option:</label>
                    <div class="d-flex gap-3">
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="correct_option" value="A" id="modal_f_a" checked>
                        <label class="form-check-label small" for="modal_f_a">A</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="correct_option" value="B" id="modal_f_b">
                        <label class="form-check-label small" for="modal_f_b">B</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="correct_option" value="C" id="modal_f_c">
                        <label class="form-check-label small" for="modal_f_c">C</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="correct_option" value="D" id="modal_f_d">
                        <label class="form-check-label small" for="modal_f_d">D</label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="create_assessment" class="btn btn-warning fw-bold px-4">
            <i class="fa-solid fa-check me-1"></i>Save Assessment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: UPLOAD MATERIAL ================= -->
<div class="modal fade" id="uploadMaterialModal" tabindex="-1" aria-labelledby="uploadMaterialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold" id="uploadMaterialModalLabel">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Learning Material / Module
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-bold">Select Target Course <span class="text-danger">*</span></label>
              <select name="course_id" id="uploadModalCourseSelect" class="form-select" required>
                <option value="">-- Choose Course --</option>
                <?php foreach ($myCoursesList as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>">
                    #<?php echo (int)$c['id']; ?> - <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Module Title <span class="text-danger">*</span></label>
              <input type="text" name="module_title" class="form-control" placeholder="e.g. Module 2: Practical Lab Work" required maxlength="150">
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Module Notes / Text Content</label>
              <textarea name="content" class="form-control" rows="3" placeholder="Lesson outline, topics covered, reading guide..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Attach Resource File</label>
              <input type="file" name="file" class="form-control" accept=".pdf,.docx,.doc,.zip,.rar,.txt,.ppt,.pptx,.xls,.xlsx,.mp4">
              <div class="form-text small text-muted">Supports PDF, Word, PowerPoint, Excel, ZIP, TXT, MP4 (Max 25 MB).</div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="upload_module" class="btn btn-warning fw-bold px-4">
            <i class="fa-solid fa-upload me-1"></i>Upload Material
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: CREATE COURSE ================= -->
<div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST">
        <div class="modal-header bg-hub text-white">
          <h5 class="modal-title fw-bold" id="createCourseModalLabel">
            <i class="fa-solid fa-plus-circle me-2"></i>Create New Course
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Course Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Advanced Robotics" required maxlength="150">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Category</label>
            <input type="text" name="category" class="form-control" placeholder="e.g. Technical &amp; Engineering" maxlength="50">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Course Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control" rows="3" placeholder="Syllabus overview and goals..." required></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="create_course" class="btn btn-hub fw-bold px-4">Publish Course</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setAssessmentCourse(courseId, courseTitle) {
  const select = document.getElementById('modalAssessmentCourseSelect');
  const titleInput = document.getElementById('modalAssessmentTitleInput');
  if (select) {
    select.value = courseId;
  }
  if (titleInput && courseTitle) {
    titleInput.value = 'Final Assessment: ' + courseTitle;
  }
}

function updateDefaultAssessmentTitle(select) {
  const selectedOpt = select.options[select.selectedIndex];
  const title = selectedOpt.getAttribute('data-title');
  const titleInput = document.getElementById('modalAssessmentTitleInput');
  if (titleInput && title) {
    titleInput.value = 'Final Assessment: ' + title;
  }
}

function setUploadCourse(courseId) {
  const select = document.getElementById('uploadModalCourseSelect');
  if (select) {
    select.value = courseId;
  }
}
</script>

<?php include 'includes/footer.php'; ?>
