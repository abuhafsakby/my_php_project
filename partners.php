<?php
require_once 'config/db.php';

$partnersResult = $conn->query("SELECT * FROM partners ORDER BY display_order ASC, id ASC");
$partnersList = [];
while ($row = $partnersResult->fetch_assoc()) {
    $partnersList[] = $row;
}
?>
<?php include 'includes/header.php'; ?>

<div class="bg-light py-4">
  <div class="container">
    <h1 class="fw-bold text-hub"><i class="fa-solid fa-handshake me-2"></i>Our Partners</h1>
    <p class="text-muted mb-0">Organisations and stakeholders supporting our mission</p>
  </div>
</div>

<div class="container my-5">
  <div class="text-center mb-5">
    <h2 class="fw-bold text-hub">Working Together for Impact</h2>
    <p class="text-muted">We are grateful to the organisations that empower us to deliver free digital education</p>
  </div>

  <?php if (!empty($partnersList)): ?>
    <div class="row g-4">
      <?php foreach ($partnersList as $p): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100 text-center p-4"
               style="border-radius: 16px; transition: transform 0.3s;"
               onmouseover="this.style.transform='translateY(-6px)'"
               onmouseout="this.style.transform=''">
            <?php if (!empty($p['logo_path']) && file_exists(__DIR__ . '/' . $p['logo_path'])): ?>
              <div class="mx-auto mb-3" style="width:80px;height:80px;">
                <img src="<?php echo htmlspecialchars($p['logo_path'], ENT_QUOTES, 'UTF-8'); ?>"
                     class="img-fluid rounded-circle border p-1 bg-white"
                     style="width:80px;height:80px;object-fit:contain;"
                     alt="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            <?php else: ?>
              <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                   style="width:80px;height:80px;background:<?php echo htmlspecialchars($p['icon_bg'] ?: '#0b6623', ENT_QUOTES, 'UTF-8'); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($p['icon'] ?: 'fa-handshake', ENT_QUOTES, 'UTF-8'); ?> fa-2x text-white"></i>
              </div>
            <?php endif; ?>
            <h5 class="fw-bold"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
            <span class="badge <?php echo htmlspecialchars($p['badge_color'] ?: 'bg-success', ENT_QUOTES, 'UTF-8'); ?> mb-3 py-2 px-3">
              <?php echo htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if (!empty($p['description'])): ?>
              <p class="text-muted small"><?php echo nl2br(htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
            <?php if (!empty($p['website_url'])): ?>
              <a href="<?php echo htmlspecialchars($p['website_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"
                 class="btn btn-sm btn-outline-secondary mt-2">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Visit Website
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center py-5">
      <i class="fa-solid fa-handshake fa-3x text-muted mb-3"></i>
      <p class="text-muted fs-5">Partner information will be published soon.</p>
    </div>
  <?php endif; ?>

  <!-- Call to Action -->
  <div class="card border-0 shadow-sm mt-5 text-center p-4 p-md-5" style="border-radius: 20px; background: linear-gradient(135deg, #0b6623, #084919);">
    <h3 class="text-white fw-bold mb-3">Become a Partner</h3>
    <p class="text-white-50 mb-4">Join us in our mission to empower communities through technology education. We welcome partnerships from organisations, NGOs, and businesses.</p>
    <div>
      <a href="contact.php" class="btn btn-warning btn-lg fw-bold px-4 px-md-5 w-100 w-sm-auto"><i class="fa-solid fa-envelope me-2"></i>Get In Touch</a>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
