<?php
require_once 'config/db.php';

$galleryResult = $conn->query("SELECT * FROM gallery ORDER BY display_order ASC, id ASC");
$galleryItems = [];
while ($row = $galleryResult->fetch_assoc()) {
    $galleryItems[] = $row;
}
?>
<?php include 'includes/header.php'; ?>

<div class="bg-light py-4">
  <div class="container">
    <h1 class="fw-bold text-hub"><i class="fa-solid fa-images me-2"></i>Gallery</h1>
    <p class="text-muted mb-0">Moments from our hub activities, training sessions, and community events</p>
  </div>
</div>

<div class="container my-5">
  <?php if (!empty($galleryItems)): ?>
    <div class="row g-4">
      <?php foreach ($galleryItems as $item): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100"
               style="border-radius: 16px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s;"
               onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 12px 32px rgba(0,0,0,0.15)'"
               onmouseout="this.style.transform='';this.style.boxShadow=''">
            <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
              <div style="height:200px;overflow:hidden;">
                <img src="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                     class="w-100" style="height:200px;object-fit:cover;"
                     alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            <?php else: ?>
              <div class="text-center py-5"
                   style="background: <?php echo htmlspecialchars($item['gradient_bg'] ?: 'linear-gradient(135deg, #0b6623, #0d7a2b)', ENT_QUOTES, 'UTF-8'); ?>; min-height: 200px; display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid <?php echo htmlspecialchars($item['icon'] ?: 'fa-camera', ENT_QUOTES, 'UTF-8'); ?> fa-4x text-white opacity-75"></i>
              </div>
            <?php endif; ?>
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <?php if (!empty($item['category'])): ?>
                  <span class="badge bg-secondary-subtle text-dark border ms-2 flex-shrink-0">
                    <?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                <?php endif; ?>
              </div>
              <?php if (!empty($item['description'])): ?>
                <p class="text-muted small mb-0"><?php echo nl2br(htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8')); ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center py-5">
      <i class="fa-solid fa-images fa-3x text-muted mb-3"></i>
      <p class="text-muted fs-5">Gallery photos and event highlights will be published soon.</p>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
