<?php
require_once 'config/db.php';

$officersResult = $conn->query("SELECT * FROM management_officers ORDER BY display_order ASC, id ASC");
$sections = [];
while ($row = $officersResult->fetch_assoc()) {
    $sec = $row['section'] ?? 'Team';
    $sections[$sec][] = $row;
}

// Section meta: icon, badge color, description
$sectionMeta = [
    'Executive Leadership' => [
        'icon'  => 'fa-crown',
        'color' => 'linear-gradient(135deg, #0b6623, #15803d)',
        'badge' => 'bg-success',
        'desc'  => 'Strategic vision and governance of Kibiya Technology & Innovation Hub',
    ],
    'Board of Directors' => [
        'icon'  => 'fa-landmark',
        'color' => 'linear-gradient(135deg, #1a237e, #3949ab)',
        'badge' => 'bg-primary',
        'desc'  => 'Providing governance oversight and strategic direction',
    ],
    'Key Program & Practice Lead' => [
        'icon'  => 'fa-briefcase',
        'color' => 'linear-gradient(135deg, #00695c, #00897b)',
        'badge' => 'bg-info text-dark',
        'desc'  => 'Driving programme delivery, practice leadership, and community impact',
    ],
    'Operations & Support' => [
        'icon'  => 'fa-gears',
        'color' => 'linear-gradient(135deg, #e65100, #f57c00)',
        'badge' => 'bg-warning text-dark',
        'desc'  => 'Ensuring smooth operations, compliance, and organisational support',
    ],
];
?>
<?php include 'includes/header.php'; ?>

<style>
  .mgmt-hero {
    background: linear-gradient(135deg, #0b6623 0%, #084919 60%, #032d0f 100%);
    padding: 4rem 0;
    position: relative;
    overflow: hidden;
  }
  .mgmt-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
  }
  .mgmt-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 350px;
    height: 350px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
  }
  .section-divider {
    border-radius: 20px;
    padding: 2.5rem 2rem;
    margin-bottom: 2rem;
    color: #fff;
    position: relative;
    overflow: hidden;
  }
  .section-divider::after {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 180px; height: 100%;
    background: rgba(255,255,255,0.06);
    clip-path: polygon(30% 0, 100% 0, 100% 100%, 0% 100%);
  }
  .officer-card {
    border: none;
    border-radius: 16px;
    transition: transform 0.35s cubic-bezier(.4,0,.2,1), box-shadow 0.35s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
  }
  .officer-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 40px rgba(0,0,0,0.14) !important;
  }
  .officer-avatar {
    width: 170px;
    height: 170px;
    max-width: 100%;
    font-size: 4rem;
    background: linear-gradient(135deg, #0b6623, #15803d);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    border: 5px solid #fff;
    box-shadow: 0 8px 25px rgba(11,102,35,0.25);
    margin: 0 auto;
    transition: transform 0.35s ease, box-shadow 0.35s ease;
  }
  .officer-card:hover .officer-avatar {
    transform: scale(1.04);
    box-shadow: 0 12px 30px rgba(11,102,35,0.32);
  }
  .officer-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.35s ease;
  }
  .officer-card:hover .officer-avatar img {
    transform: scale(1.05);
  }
  .ceo-card {
    border-radius: 20px;
    background: linear-gradient(135deg, #f8fdf9, #e8f5e9);
    border-left: 5px solid #0b6623;
  }
  .ceo-avatar {
    width: 220px;
    height: 220px;
    max-width: 100%;
    font-size: 5rem;
    background: linear-gradient(135deg, #0b6623, #1b9e3e);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    border: 6px solid #fff;
    box-shadow: 0 10px 32px rgba(11,102,35,0.3);
    margin: 0 auto;
    transition: transform 0.35s ease, box-shadow 0.35s ease;
  }
  .ceo-card:hover .ceo-avatar {
    transform: scale(1.03);
    box-shadow: 0 14px 38px rgba(11,102,35,0.38);
  }
  .ceo-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.35s ease;
  }
  .ceo-card:hover .ceo-avatar img {
    transform: scale(1.04);
  }
  .org-chart-line {
    width: 3px;
    height: 40px;
    background: linear-gradient(to bottom, #0b6623, transparent);
    margin: 0 auto;
  }
  @media (max-width: 767.98px) {
    .mgmt-hero { padding: 2.5rem 0; }
    .mgmt-hero h1 { font-size: 1.85rem; }
    .section-divider { padding: 1.5rem 1rem; border-radius: 14px; }
    .section-divider h3 { font-size: 1.25rem; }
    .ceo-avatar { width: 160px; height: 160px; font-size: 3.5rem; }
    .officer-avatar { width: 130px; height: 130px; font-size: 3rem; }
  }
</style>

<!-- Hero Banner -->
<div class="mgmt-hero">
  <div class="container position-relative" style="z-index:2;">
    <div class="text-center">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
           style="width:70px;height:70px;background:rgba(255,255,255,0.12);backdrop-filter:blur(8px);">
        <i class="fa-solid fa-sitemap fa-2x text-white"></i>
      </div>
      <h1 class="display-5 fw-bold text-white mb-2">Hub Management &amp; Leadership</h1>
      <p class="text-white-50 fs-5 mb-0">Meet the team driving technology innovation, skills acquisition, and digital transformation at Kibiya Hub</p>
      <div class="d-flex justify-content-center gap-4 mt-4">
        <div class="text-center">
          <div class="text-white fw-bold fs-3"><?php echo array_sum(array_map('count', $sections)); ?></div>
          <div class="text-white-50 small">Team Members</div>
        </div>
        <div style="width:1px;background:rgba(255,255,255,0.2);"></div>
        <div class="text-center">
          <div class="text-white fw-bold fs-3"><?php echo count($sections); ?></div>
          <div class="text-white-50 small">Departments</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container my-5">

<?php foreach ($sections as $sectionName => $officers): ?>
  <?php
    $meta = $sectionMeta[$sectionName] ?? [
        'icon' => 'fa-users',
        'color' => 'linear-gradient(135deg, #37474f, #546e7a)',
        'badge' => 'bg-secondary',
        'desc' => '',
    ];
  ?>

  <!-- Section Heading -->
  <div class="section-divider shadow-sm mb-4" style="background: <?php echo $meta['color']; ?>;">
    <div class="d-flex align-items-center gap-3 position-relative flex-wrap flex-sm-nowrap" style="z-index:2;">
      <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
           style="width:55px;height:55px;background:rgba(255,255,255,0.15);backdrop-filter:blur(6px);">
        <i class="fa-solid <?php echo $meta['icon']; ?> fa-xl"></i>
      </div>
      <div>
        <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8'); ?></h3>
        <?php if (!empty($meta['desc'])): ?>
          <p class="mb-0 small" style="opacity:.75;"><?php echo htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>
      <div class="ms-sm-auto">
        <span class="badge bg-white text-dark fw-bold px-3 py-2"><?php echo count($officers); ?> <?php echo count($officers) === 1 ? 'Member' : 'Members'; ?></span>
      </div>
    </div>
  </div>

  <div class="org-chart-line"></div>

  <?php if ($sectionName === 'Executive Leadership' && count($officers) === 1): ?>
    <!-- CEO gets a featured large card -->
    <?php $ceo = $officers[0]; ?>
    <div class="row justify-content-center mb-5">
      <div class="col-md-8 col-lg-6">
        <div class="card ceo-card shadow p-4 text-center">
          <div class="d-flex justify-content-center mb-3">
            <?php if (!empty($ceo['image_path']) && file_exists(__DIR__ . '/' . $ceo['image_path'])): ?>
              <div class="ceo-avatar rounded-circle overflow-hidden">
                <img src="<?php echo htmlspecialchars($ceo['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ceo['name'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            <?php else: ?>
              <div class="ceo-avatar rounded-circle d-flex align-items-center justify-content-center text-white">
                <?php echo strtoupper(substr($ceo['name'], 0, 1)); ?>
              </div>
            <?php endif; ?>
          </div>
          <h3 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($ceo['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
          <span class="badge bg-success fs-6 py-2 px-4 mx-auto mb-3">
            <i class="fa-solid fa-crown me-1"></i><?php echo htmlspecialchars($ceo['role_title'], ENT_QUOTES, 'UTF-8'); ?>
          </span>
          <?php if (!empty($ceo['bio'])): ?>
            <p class="text-muted"><?php echo nl2br(htmlspecialchars($ceo['bio'], ENT_QUOTES, 'UTF-8')); ?></p>
          <?php endif; ?>
          <?php if (!empty($ceo['email']) || !empty($ceo['phone'])): ?>
            <div class="d-flex justify-content-center gap-2 mt-2 flex-wrap">
              <?php if (!empty($ceo['email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($ceo['email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-envelope me-1"></i>Email</a>
              <?php endif; ?>
              <?php if (!empty($ceo['phone'])): ?>
                <a href="tel:<?php echo htmlspecialchars($ceo['phone'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-phone me-1"></i>Call</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="org-chart-line"></div>

  <?php else: ?>
    <!-- Grid of officer cards -->
    <div class="row g-4 mb-5">
      <?php foreach ($officers as $officer): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
          <div class="card officer-card shadow-sm h-100 text-center">
            <div class="card-body p-4 d-flex flex-column align-items-center">
              <?php if (!empty($officer['image_path']) && file_exists(__DIR__ . '/' . $officer['image_path'])): ?>
                <div class="officer-avatar rounded-circle overflow-hidden mb-3">
                  <img src="<?php echo htmlspecialchars($officer['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                       alt="<?php echo htmlspecialchars($officer['name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              <?php else: ?>
                <div class="officer-avatar rounded-circle mb-3">
                  <?php echo strtoupper(substr($officer['name'], 0, 1)); ?>
                </div>
              <?php endif; ?>

              <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($officer['name'], ENT_QUOTES, 'UTF-8'); ?></h6>
              <span class="badge <?php echo htmlspecialchars($officer['badge_color'] ?: 'bg-secondary', ENT_QUOTES, 'UTF-8'); ?> mb-2 py-1 px-3 small">
                <?php echo htmlspecialchars($officer['role_title'], ENT_QUOTES, 'UTF-8'); ?>
              </span>

              <?php if (!empty($officer['bio'])): ?>
                <p class="text-muted small mt-2 mb-0" style="line-height:1.5;"><?php echo nl2br(htmlspecialchars($officer['bio'], ENT_QUOTES, 'UTF-8')); ?></p>
              <?php endif; ?>

              <?php if (!empty($officer['email']) || !empty($officer['phone'])): ?>
                <div class="d-flex justify-content-center gap-2 mt-auto pt-3 flex-wrap">
                  <?php if (!empty($officer['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($officer['email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-envelope"></i></a>
                  <?php endif; ?>
                  <?php if (!empty($officer['phone'])): ?>
                    <a href="tel:<?php echo htmlspecialchars($officer['phone'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-phone"></i></a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php endforeach; ?>

</div>

<?php include 'includes/footer.php'; ?>
