<?php
require_once 'config/db.php';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $message !== '') {
        // In production, send an email or store in DB. For now, show a success message.
        $success = 'Thank you for your message, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '! We will get back to you soon.';
    } else {
        $success = '';
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="bg-light py-4">
  <div class="container">
    <h1 class="fw-bold text-hub"><i class="fa-solid fa-envelope me-2"></i>Contact Us</h1>
    <p class="text-muted mb-0">Get in touch with Kibiya Technology &amp; Innovation Hub</p>
  </div>
</div>

<div class="container my-5">
  <div class="row g-5">
    <div class="col-lg-5">
      <h3 class="fw-bold text-hub mb-4">Reach Out To Us</h3>
      <div class="d-flex align-items-start mb-4">
        <div class="rounded-circle bg-hub d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
          <i class="fa-solid fa-location-dot text-white"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-1">Visit Us</h6>
          <p class="text-muted small mb-0">Kibiya LGA, Kano State, Nigeria</p>
        </div>
      </div>
      <div class="d-flex align-items-start mb-4">
        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;background:#f4b41a;">
          <i class="fa-solid fa-envelope text-dark"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-1">Email Us</h6>
          <p class="text-muted small mb-0">info@kibiyahub.org</p>
        </div>
      </div>
      <div class="d-flex align-items-start mb-4">
        <div class="rounded-circle bg-info d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
          <i class="fa-solid fa-phone text-white"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-1">Call Us</h6>
          <p class="text-muted small mb-0">+234 800 000 0000</p>
        </div>
      </div>
      <div class="d-flex align-items-start">
        <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
          <i class="fa-solid fa-clock text-white"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-1">Operating Hours</h6>
          <p class="text-muted small mb-0">Monday – Friday: 8:00 AM – 5:00 PM<br>Saturday: 9:00 AM – 2:00 PM</p>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
        <h4 class="fw-bold mb-3"><i class="fa-solid fa-paper-plane me-2 text-hub"></i>Send Us a Message</h4>
        <?php if ($success): ?>
          <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" novalidate>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Your Name</label>
              <input type="text" name="name" class="form-control" placeholder="Full name" required maxlength="100">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="you@example.com" required maxlength="100">
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label fw-semibold small">Subject</label>
            <input type="text" name="subject" class="form-control" placeholder="What is this about?" maxlength="150">
          </div>
          <div class="mt-3">
            <label class="form-label fw-semibold small">Message</label>
            <textarea name="message" class="form-control" rows="5" placeholder="Write your message here..." required></textarea>
          </div>
          <button type="submit" class="btn btn-hub w-100 mt-4 py-2 fw-bold">
            <i class="fa-solid fa-paper-plane me-2"></i>Send Message
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
