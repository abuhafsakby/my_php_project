<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);

if (!$course_id) {
    header('Location: student_dashboard.php');
    exit;
}

// Verify enrollment
$enrollStmt = $conn->prepare("SELECT e.id, e.status FROM enrollments e WHERE e.student_id = ? AND e.course_id = ?");
$enrollStmt->bind_param("ii", $student_id, $course_id);
$enrollStmt->execute();
$enrollment = $enrollStmt->get_result()->fetch_assoc();
$enrollStmt->close();

if (!$enrollment) {
    header('Location: student_dashboard.php');
    exit;
}
if ($enrollment['status'] === 'pending') {
    $_SESSION['quiz_error'] = "You cannot take the final assessment yet. Your enrollment is still pending administrator approval.";
    header('Location: student_dashboard.php');
    exit;
}
if ($enrollment['status'] === 'rejected') {
    $_SESSION['quiz_error'] = "Your enrollment access for this course was declined by the administrator.";
    header('Location: student_dashboard.php');
    exit;
}
if ($enrollment['status'] === 'completed') {
    header('Location: student_dashboard.php');
    exit;
}

// Fetch quiz for this course
$quizStmt = $conn->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
$quizStmt->bind_param("i", $course_id);
$quizStmt->execute();
$quiz = $quizStmt->get_result()->fetch_assoc();
$quizStmt->close();

$message = '';
$quizResult = null;

if (!$quiz) {
    $message = "<div class='alert alert-warning'><i class='fa-solid fa-info-circle me-2'></i>No assessment is available for this course yet. Please check back later.</div>";
} else {
    // Fetch questions
    $qStmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
    $qStmt->bind_param("i", $quiz['id']);
    $qStmt->execute();
    $questions = $qStmt->get_result();
    $questionsList = [];
    while ($q = $questions->fetch_assoc()) {
        $questionsList[] = $q;
    }
    $qStmt->close();

    if (empty($questionsList)) {
        $message = "<div class='alert alert-warning'><i class='fa-solid fa-info-circle me-2'></i>This assessment has no questions yet. Please check back later.</div>";
    }

    // Handle submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz']) && !empty($questionsList)) {
        $correct = 0;
        $total = count($questionsList);

        foreach ($questionsList as $q) {
            $answer = strtoupper(trim($_POST['q_' . $q['id']] ?? ''));
            if ($answer === strtoupper($q['correct_option'])) {
                $correct++;
            }
        }

        $score = ($total > 0) ? round(($correct / $total) * 100) : 0;
        $passed = $score >= ($quiz['passing_score'] ?? 70);

        // Update enrollment
        if ($passed) {
            $certCode = 'KH-' . strtoupper(bin2hex(random_bytes(6)));
            $updateStmt = $conn->prepare("UPDATE enrollments SET status = 'completed', quiz_score = ?, certificate_code = ?, completed_at = NOW() WHERE student_id = ? AND course_id = ?");
            $updateStmt->bind_param("isii", $score, $certCode, $student_id, $course_id);
        } else {
            $updateStmt = $conn->prepare("UPDATE enrollments SET quiz_score = ? WHERE student_id = ? AND course_id = ?");
            $updateStmt->bind_param("iii", $score, $student_id, $course_id);
        }
        $updateStmt->execute();
        $updateStmt->close();

        $quizResult = [
            'score' => $score,
            'correct' => $correct,
            'total' => $total,
            'passed' => $passed,
            'passing_score' => $quiz['passing_score'] ?? 70
        ];
    }
}

// Get course title
$courseStmt = $conn->prepare("SELECT title FROM courses WHERE id = ?");
$courseStmt->bind_param("i", $course_id);
$courseStmt->execute();
$course = $courseStmt->get_result()->fetch_assoc();
$courseStmt->close();
?>
<?php include 'includes/header.php'; ?>

<div class="container my-5">
  <div class="mb-4">
    <a href="student_dashboard.php" class="text-hub text-decoration-none"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
  </div>

  <h2 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check me-2 text-hub"></i>Course Assessment</h2>
  <p class="text-muted mb-4"><?php echo htmlspecialchars($course['title'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></p>

  <?php echo $message; ?>

  <?php if ($quizResult): ?>
    <!-- Results -->
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm text-center p-4 p-md-5" style="border-radius: 20px;">
          <?php if ($quizResult['passed']): ?>
            <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;background:#0b6623;">
              <i class="fa-solid fa-check fa-2x text-white"></i>
            </div>
            <h3 class="fw-bold text-hub">Congratulations!</h3>
            <p class="text-muted">You passed the assessment</p>
          <?php else: ?>
            <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;background:#dc3545;">
              <i class="fa-solid fa-xmark fa-2x text-white"></i>
            </div>
            <h3 class="fw-bold text-danger">Not Quite There</h3>
            <p class="text-muted">You need <?php echo $quizResult['passing_score']; ?>% to pass</p>
          <?php endif; ?>
          <h1 class="display-3 fw-bold <?php echo $quizResult['passed'] ? 'text-hub' : 'text-danger'; ?>"><?php echo $quizResult['score']; ?>%</h1>
          <p class="text-muted"><?php echo $quizResult['correct']; ?> out of <?php echo $quizResult['total']; ?> correct</p>
          <div class="d-flex flex-column flex-sm-row gap-2 gap-sm-3 justify-content-center mt-3">
            <?php if ($quizResult['passed']): ?>
              <a href="generate_certificate.php?course_id=<?php echo $course_id; ?>" class="btn btn-warning fw-bold"><i class="fa-solid fa-award me-2"></i>Get Certificate</a>
            <?php else: ?>
              <a href="take_quiz.php?course_id=<?php echo $course_id; ?>" class="btn btn-hub"><i class="fa-solid fa-redo me-2"></i>Retry</a>
            <?php endif; ?>
            <a href="student_dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
          </div>
        </div>
      </div>
    </div>

  <?php elseif ($quiz && !empty($questionsList)): ?>
    <!-- Quiz Form -->
    <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
        <span class="badge bg-warning text-dark px-3 py-2">Passing Score: <?php echo (int)($quiz['passing_score'] ?? 70); ?>%</span>
      </div>
      <form method="POST">
        <?php foreach ($questionsList as $idx => $q): ?>
          <div class="mb-4 p-3 bg-light rounded" style="border-radius: 12px !important;">
            <h6 class="fw-bold mb-3">
              <span class="badge bg-hub me-2"><?php echo $idx + 1; ?></span>
              <?php echo htmlspecialchars($q['question'], ENT_QUOTES, 'UTF-8'); ?>
            </h6>
            <div class="ps-3">
              <?php foreach (['A', 'B', 'C', 'D'] as $opt):
                $optionKey = 'option_' . strtolower($opt);
                if (empty($q[$optionKey])) continue;
              ?>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="q_<?php echo $q['id']; ?>" id="q<?php echo $q['id']; ?>_<?php echo $opt; ?>" value="<?php echo $opt; ?>" required>
                  <label class="form-check-label" for="q<?php echo $q['id']; ?>_<?php echo $opt; ?>">
                    <strong><?php echo $opt; ?>.</strong> <?php echo htmlspecialchars($q[$optionKey], ENT_QUOTES, 'UTF-8'); ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <button type="submit" name="submit_quiz" class="btn btn-hub btn-lg w-100 fw-bold py-3">
          <i class="fa-solid fa-paper-plane me-2"></i>Submit Assessment
        </button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
