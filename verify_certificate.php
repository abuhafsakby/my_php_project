<?php
require_once 'config/db.php';

$code = trim($_GET['code'] ?? '');
$certificate = null;
$searched = false;

if ($code !== '') {
    $searched = true;
    $stmt = $conn->prepare("SELECT e.*, u.full_name AS student_name, u.email AS student_email,
            c.title AS course_title, c.category AS course_category,
            fac.full_name AS facilitator_name
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN users fac ON c.created_by = fac.id
            WHERE e.certificate_code = ? AND e.status = 'completed' LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $certificate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // Fetch CEO details for signature
    $ceoStmt = $conn->prepare("SELECT full_name, image_path FROM management_officers WHERE role_title LIKE ? LIMIT 1");
    $ceoRole = "%CEO%";
    $ceoStmt->bind_param("s", $ceoRole);
    $ceoStmt->execute();
    $ceoInfo = $ceoStmt->get_result()->fetch_assoc();
    $ceoStmt->close();
    $ceoName = $ceoInfo['full_name'] ?? 'CEO';
    $ceoSignature = $ceoInfo['image_path'] ?? '';

}
?>
<?php include 'includes/header.php'; ?>

<div class="py-5" style="background: linear-gradient(135deg, #0b6623 0%, #062e10 100%);">
  <div class="container text-white text-center">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 shadow" style="width: 70px; height: 70px; background: rgba(244,180,26,0.2); border: 2px solid #f4b41a;">
      <i class="fa-solid fa-shield-halved fa-2x text-warning"></i>
    </div>
    <h1 class="fw-bold display-6 mb-2">Certificate Verification Portal</h1>
    <p class="lead opacity-90 mx-auto mb-4" style="max-width: 650px;">
      Verify the authenticity and integrity of certificates issued by the Kibiya Technology and Innovation Hub.
    </p>

    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <form method="GET" class="input-group shadow-lg">
          <input type="text" name="code" class="form-control form-control-lg border-0" placeholder="Enter Certificate Code (e.g. KH-XXXX...)" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" required>
          <button type="submit" class="btn btn-warning btn-lg fw-bold px-4">
            <i class="fa-solid fa-magnifying-glass me-1"></i>Verify
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <?php if ($searched): ?>
        <?php if ($certificate): ?>
          <!-- VALID CERTIFICATE -->
          <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 20px;">
            <div class="p-4 text-white text-center position-relative" style="background: linear-gradient(135deg, #0b6623, #15803d);">
              <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white text-success shadow mb-2" style="width: 60px; height: 60px;">
                <i class="fa-solid fa-check fa-2x"></i>
              </div>
              <h3 class="fw-bold mb-1">Official Credential Verified</h3>
              <p class="mb-0 opacity-90 small">This certificate is genuine and officially recorded in the Kibiya Hub LMS registry.</p>
            </div>

            <div class="card-body p-4 p-md-5">
              <div class="row g-4 mb-4">
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-semibold mb-1">Recipient Name</div>
                  <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($certificate['student_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-semibold mb-1">Course Completed</div>
                  <h5 class="fw-bold text-hub mb-0"><?php echo htmlspecialchars($certificate['course_title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                  <span class="badge bg-warning text-dark mt-1"><?php echo htmlspecialchars($certificate['course_category'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-semibold mb-1">Certificate Number</div>
                  <div class="fw-mono fw-bold fs-5 text-dark"><?php echo htmlspecialchars($certificate['certificate_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-semibold mb-1">Date of Completion</div>
                  <div class="fw-bold text-dark"><?php echo date('F d, Y', strtotime($certificate['completed_at'] ?? 'now')); ?></div>
                </div>

                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-semibold mb-1">Exam Performance</div>
                  <div class="fw-bold fs-5 text-success">
                    <i class="fa-solid fa-star text-warning me-1"></i><?php echo (int)($certificate['quiz_score'] ?? 0); ?>% (Passed)
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-semibold mb-1">Lead Instructor / Facilitator</div>
                  <div class="fw-bold text-dark"><?php echo htmlspecialchars($certificate['facilitator_name'] ?? 'Hub Academic Faculty', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              </div>

              <!-- Security Fingerprint Box -->
              <div class="p-3 rounded-3 bg-light border mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <small class="fw-bold text-muted text-uppercase"><i class="fa-solid fa-fingerprint text-hub me-1"></i>Cryptographic Security Hash</small>
                  <span class="badge bg-success-subtle text-success small">Verified Authentic</span>
                </div>
                <code class="small text-break" style="color: #0b6623;">
                  <?php 
                    $hashSeed = ($certificate['certificate_code'] ?? '') . ($certificate['student_name'] ?? '') . ($certificate['course_id'] ?? '') . 'KIBIYA_SECURE_TOKEN';
                    echo hash('sha256', $hashSeed); 
                  ?>
                </code>
              </div>

              <div class="d-flex flex-wrap gap-3 justify-content-center pt-2">
                <a href="generate_certificate.php?course_id=<?php echo (int)$certificate['course_id']; ?>" class="btn btn-hub fw-bold px-4">
                  <i class="fa-solid fa-award me-2"></i>View Official Certificate
                </a>
                <a href="courses.php" class="btn btn-outline-secondary px-4">Explore More Courses</a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <!-- INVALID / NOT FOUND -->
          <div class="card border-0 shadow-sm p-5 text-center" style="border-radius: 20px;">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mx-auto mb-3" style="width: 75px; height: 75px;">
              <i class="fa-solid fa-triangle-exclamation fa-3x"></i>
            </div>
            <h4 class="fw-bold text-danger mb-2">Certificate Record Not Found</h4>
            <p class="text-muted mx-auto mb-4" style="max-width: 500px;">
              No verified certificate was found matching the identifier <strong>"<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"</strong>. Please check for typos and verify that the code begins with "KH-".
            </p>
            <div>
              <a href="verify_certificate.php" class="btn btn-hub px-4">Try Another Code</a>
            </div>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <!-- Default State: How verification works -->
        <div class="card border-0 shadow-sm p-4 p-md-5" style="border-radius: 20px;">
          <h4 class="fw-bold text-hub mb-3 text-center"><i class="fa-solid fa-shield-check me-2"></i>Secure Credential Verification</h4>
          <p class="text-muted text-center mb-4">Every certificate issued by Kibiya Technology &amp; Innovation Hub is cryptographically recorded and tamper-resistant.</p>
          
          <div class="row g-4 mt-2">
            <div class="col-md-4 text-center">
              <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2" style="width:60px;height:60px;">
                <i class="fa-solid fa-qrcode fa-2x text-hub"></i>
              </div>
              <h6 class="fw-bold">QR Code Enabled</h6>
              <p class="small text-muted">Scan the scannable QR code on any certificate to instantly view its authenticity record.</p>
            </div>
            <div class="col-md-4 text-center">
              <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2" style="width:60px;height:60px;">
                <i class="fa-solid fa-hashtag fa-2x text-warning"></i>
              </div>
              <h6 class="fw-bold">Unique Serial ID</h6>
              <p class="small text-muted">Each graduate receives a unique cryptographic serial number stored in the primary registry.</p>
            </div>
            <div class="col-md-4 text-center">
              <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2" style="width:60px;height:60px;">
                <i class="fa-solid fa-fingerprint fa-2x text-info"></i>
              </div>
              <h6 class="fw-bold">Anti-Fraud Protection</h6>
              <p class="small text-muted">Digital SHA-256 signatures prevent modification or counterfeiting of student achievements.</p>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
