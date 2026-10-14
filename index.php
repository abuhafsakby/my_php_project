<?php
require_once 'config/db.php';

$courses = $conn->query("SELECT c.*, u.full_name AS facilitator_name FROM courses c LEFT JOIN users u ON c.created_by = u.id ORDER BY c.created_at DESC LIMIT 6");
$total_students = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(*) AS count FROM courses")->fetch_assoc()['count'];
$total_facilitators = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role = 'facilitator'")->fetch_assoc()['count'];
$latestNews = $conn->query("SELECT * FROM news WHERE status = 'published' ORDER BY is_featured DESC, id DESC LIMIT 3");

// Fetch CEO / Executive Leader for Header Slider
$ceoQuery = $conn->query("SELECT * FROM management_officers WHERE role_title LIKE '%Director%' OR role_title LIKE '%CEO%' OR role_title LIKE '%Executive%' ORDER BY display_order ASC, id ASC LIMIT 1");
$ceoOfficer = ($ceoQuery && $ceoQuery->num_rows > 0) ? $ceoQuery->fetch_assoc() : null;
if (!$ceoOfficer) {
    $fRes = $conn->query("SELECT * FROM management_officers ORDER BY display_order ASC, id ASC LIMIT 1");
    if ($fRes && $fRes->num_rows > 0) $ceoOfficer = $fRes->fetch_assoc();
}

// Fetch Top News / Opportunity for Header Slider
$newsSlideQuery = $conn->query("SELECT * FROM news WHERE status = 'published' AND category IN ('Opportunities', 'Jobs', 'Congratulations') ORDER BY is_featured DESC, id DESC LIMIT 1");
$newsSlide = ($newsSlideQuery && $newsSlideQuery->num_rows > 0) ? $newsSlideQuery->fetch_assoc() : null;
if (!$newsSlide) {
    $fNews = $conn->query("SELECT * FROM news WHERE status = 'published' ORDER BY id DESC LIMIT 1");
    if ($fNews && $fNews->num_rows > 0) $newsSlide = $fNews->fetch_assoc();
}

// Fetch Upcoming Event / Workshop / Announcement for Header Slider
$eventSlideQuery = $conn->query("SELECT * FROM news WHERE status = 'published' AND (category IN ('Events', 'Announcements') OR deadline_date IS NOT NULL) ORDER BY is_featured DESC, id DESC LIMIT 1");
$eventSlide = ($eventSlideQuery && $eventSlideQuery->num_rows > 0) ? $eventSlideQuery->fetch_assoc() : null;
// Fetch Dynamic Header Slides
$headerSlidesQuery = $conn->query("SELECT * FROM header_slides WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
$activeSlides = [];
if ($headerSlidesQuery) {
    while ($sl = $headerSlidesQuery->fetch_assoc()) {
        $activeSlides[] = $sl;
    }
}
?>
<?php include 'includes/header.php'; ?>

<style>
  /* ── Header Slider / Hero Carousel Styles ── */
  .hero-slider-section {
    position: relative;
    overflow: hidden;
  }
  .hero-slide {
    min-height: 520px;
    display: flex;
    align-items: center;
    padding: 3.5rem 0;
    position: relative;
  }
  .hero-slide::before {
    content: '';
    position: absolute;
    top: -25%;
    right: -10%;
    width: 520px;
    height: 520px;
    background: radial-gradient(circle, rgba(244,180,26,0.16) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
  }
  .glass-card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 24px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
  }
  .ceo-slider-avatar {
    width: 170px;
    height: 170px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #f4b41a;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
  }
  .ceo-slider-initials {
    width: 170px;
    height: 170px;
    border-radius: 50%;
    border: 4px solid #f4b41a;
    background: linear-gradient(135deg, #0b6623, #15803d);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    font-weight: 800;
    color: #ffffff;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
  }
  .hero-carousel-control {
    width: 48px;
    height: 48px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 50%;
    opacity: 0.85;
    transition: all 0.25s ease;
    margin: 0 12px;
  }
  .hero-carousel-control:hover {
    background: rgba(255, 255, 255, 0.3);
    opacity: 1;
    transform: translateY(-50%) scale(1.08);
  }
  .carousel-indicators [data-bs-target] {
    width: 28px;
    height: 6px;
    border-radius: 4px;
    border: none;
    background-color: rgba(255, 255, 255, 0.45);
    margin: 0 4px;
    transition: all 0.3s ease;
  }
  .carousel-indicators .active {
    width: 44px;
    background-color: #f4b41a;
  }
  @media (max-width: 991.98px) {
    .hero-slide {
      min-height: auto;
      padding: 3rem 0 4rem 0;
    }
    .hero-carousel-control {
      display: none;
    }
    .ceo-slider-avatar, .ceo-slider-initials {
      width: 120px;
      height: 120px;
      font-size: 2.75rem;
    }
  }
  @media (max-width: 767.98px) {
    .hero-slide h1 {
      font-size: 1.85rem !important;
    }
  }
</style>

<!-- Home Header Slider (10-second interval, dynamic CMS managed) -->
<section class="hero-slider-section">
  <div id="homeHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="10000" data-bs-pause="hover" data-bs-touch="true">
    
    <?php if (!empty($activeSlides)): ?>
      <!-- Carousel Indicators -->
      <div class="carousel-indicators mb-3">
        <?php foreach ($activeSlides as $idx => $s): ?>
          <button type="button" data-bs-target="#homeHeroCarousel" data-bs-slide-to="<?php echo $idx; ?>" class="<?php echo $idx === 0 ? 'active' : ''; ?>" <?php echo $idx === 0 ? 'aria-current="true"' : ''; ?> aria-label="Slide <?php echo $idx + 1; ?>: <?php echo htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'); ?>"></button>
        <?php endforeach; ?>
      </div>

      <!-- Carousel Inner -->
      <div class="carousel-inner">
        <?php foreach ($activeSlides as $idx => $slide): ?>
          <?php
            $bgGradient = !empty($slide['bg_gradient']) ? $slide['bg_gradient'] : 'linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)';
            $badgeIcon = !empty($slide['badge_icon']) ? $slide['badge_icon'] : 'fa-rocket';
            $badgeText = !empty($slide['badge_text']) ? $slide['badge_text'] : 'Kibiya Tech Hub';
            $badgeColor = !empty($slide['badge_color']) ? $slide['badge_color'] : 'bg-warning text-dark';
            $cardType = $slide['card_type'] ?? 'graphic';
            $slideImg = (!empty($slide['image_path']) && file_exists(__DIR__ . '/' . $slide['image_path'])) ? $slide['image_path'] : '';
          ?>
          <div class="carousel-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-bs-interval="10000">
            <div class="hero-slide" style="background: <?php echo htmlspecialchars($bgGradient, ENT_QUOTES, 'UTF-8'); ?>;">
              <div class="container position-relative" style="z-index: 2;">
                <div class="row align-items-center">
                  
                  <!-- Left Text & Actions -->
                  <div class="col-lg-7 text-white py-3 py-lg-4">
                    <span class="badge <?php echo htmlspecialchars($badgeColor, ENT_QUOTES, 'UTF-8'); ?> px-3 py-2 mb-3 rounded-pill fw-semibold">
                      <i class="fa-solid <?php echo htmlspecialchars($badgeIcon, ENT_QUOTES, 'UTF-8'); ?> me-1"></i> <?php echo htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <h1 class="display-4 fw-bold mb-3" style="line-height: 1.2;">
                      <?php echo htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8'); ?><br>
                      <?php if (!empty($slide['title_highlight'])): ?>
                        <span style="color: #f4b41a;"><?php echo htmlspecialchars($slide['title_highlight'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </h1>

                    <?php if (!empty($slide['quote_author'])): ?>
                      <div class="p-3 bg-white bg-opacity-10 rounded-4 border-start border-4 border-warning mb-4" style="max-width: 560px;">
                        <p class="fst-italic text-white mb-1" style="font-size: 1.05rem; line-height: 1.6;">
                          &ldquo;<?php echo htmlspecialchars($slide['subtitle'], ENT_QUOTES, 'UTF-8'); ?>&rdquo;
                        </p>
                        <small class="text-warning fw-bold d-block mt-2">
                          — <?php echo htmlspecialchars($slide['quote_author'], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($slide['quote_author_role'])): ?>, <span class="text-white-50"><?php echo htmlspecialchars($slide['quote_author_role'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        </small>
                      </div>
                    <?php else: ?>
                      <p class="lead mb-4 opacity-90" style="max-width: 540px;">
                        <?php echo htmlspecialchars($slide['subtitle'], ENT_QUOTES, 'UTF-8'); ?>
                      </p>
                    <?php endif; ?>

                    <div class="d-flex flex-column flex-sm-row gap-3">
                      <?php if (!empty($slide['btn_primary_text'])): ?>
                        <a href="<?php echo htmlspecialchars($slide['btn_primary_link'] ?: 'courses.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning btn-lg fw-bold px-4 shadow w-100 w-sm-auto text-center">
                          <i class="fa-solid <?php echo htmlspecialchars($slide['btn_primary_icon'] ?: 'fa-arrow-right', ENT_QUOTES, 'UTF-8'); ?> me-2"></i><?php echo htmlspecialchars($slide['btn_primary_text'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php endif; ?>
                      <?php if (!empty($slide['btn_secondary_text'])): ?>
                        <a href="<?php echo htmlspecialchars($slide['btn_secondary_link'] ?: 'register.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light btn-lg px-4 w-100 w-sm-auto text-center">
                          <i class="fa-solid <?php echo htmlspecialchars($slide['btn_secondary_icon'] ?: 'fa-user-plus', ENT_QUOTES, 'UTF-8'); ?> me-2"></i><?php echo htmlspecialchars($slide['btn_secondary_text'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Right Visual Card -->
                  <div class="col-lg-5 text-center py-3">
                    <div class="glass-card p-4 p-md-5 text-white">
                      
                      <?php if ($cardType === 'ceo_profile'): ?>
                        <?php
                          $ceoName = !empty($slide['quote_author']) ? $slide['quote_author'] : ($ceoOfficer['name'] ?? 'Musa Abdullahi Kibiya');
                          $ceoRole = !empty($slide['quote_author_role']) ? $slide['quote_author_role'] : ($ceoOfficer['role_title'] ?? 'Executive Director');
                          $ceoImg = $slideImg ?: ((!empty($ceoOfficer['image_path']) && file_exists(__DIR__ . '/' . $ceoOfficer['image_path'])) ? $ceoOfficer['image_path'] : '');
                          $ceoBio = !empty($ceoOfficer['bio']) ? $ceoOfficer['bio'] : 'Leading strategic innovation and youth development for Kibiya LGA.';
                        ?>
                        <div class="mb-3 position-relative d-inline-block">
                          <?php if ($ceoImg): ?>
                            <img src="<?php echo htmlspecialchars($ceoImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ceoName, ENT_QUOTES, 'UTF-8'); ?>" class="ceo-slider-avatar">
                          <?php else: ?>
                            <div class="ceo-slider-initials">
                              <?php echo strtoupper(substr($ceoName, 0, 1)); ?>
                            </div>
                          <?php endif; ?>
                          <span class="position-absolute bottom-0 end-0 bg-warning text-dark rounded-circle p-2 shadow" title="Executive Leader">
                            <i class="fa-solid fa-crown fa-lg"></i>
                          </span>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($ceoName, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <span class="badge bg-success px-3 py-2 rounded-pill mb-3"><?php echo htmlspecialchars($ceoRole, ENT_QUOTES, 'UTF-8'); ?></span>
                        <p class="text-white-50 small mb-0" style="line-height: 1.5;"><?php echo htmlspecialchars(mb_strimwidth($ceoBio, 0, 130, '...'), ENT_QUOTES, 'UTF-8'); ?></p>

                      <?php elseif ($cardType === 'news_highlight'): ?>
                        <?php
                          $nImg = $slideImg ?: ((!empty($newsSlide['image_path']) && file_exists(__DIR__ . '/' . $newsSlide['image_path'])) ? $newsSlide['image_path'] : '');
                          $nTitle = !empty($newsSlide['title']) ? $newsSlide['title'] : 'Verified Hub Opportunity';
                          $nDate = !empty($newsSlide['created_at']) ? date('M d, Y', strtotime($newsSlide['created_at'])) : date('M d, Y');
                        ?>
                        <?php if ($nImg): ?>
                          <img src="<?php echo htmlspecialchars($nImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nTitle, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-4 shadow mb-3" style="max-height: 200px; width: 100%; object-fit: cover;">
                        <?php else: ?>
                          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 90px; height: 90px; background: rgba(2,132,199,0.25); border: 2px solid #38bdf8;">
                            <i class="fa-solid fa-lightbulb fa-3x text-warning"></i>
                          </div>
                        <?php endif; ?>
                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($nTitle, ENT_QUOTES, 'UTF-8'); ?></h5>
                        <div class="small text-white-50 mb-3">
                          <span><i class="fa-regular fa-calendar me-1"></i><?php echo $nDate; ?></span> &bull; 
                          <span><i class="fa-solid fa-shield-halved me-1"></i>Official Notice</span>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">
                          <i class="fa-solid fa-circle-check me-1"></i>Verified Hub Opportunity
                        </span>

                      <?php elseif ($cardType === 'event_badge'): ?>
                        <?php
                          $eImg = $slideImg ?: ((!empty($eventSlide['image_path']) && file_exists(__DIR__ . '/' . $eventSlide['image_path'])) ? $eventSlide['image_path'] : '');
                          $eTitle = !empty($eventSlide['title']) ? $eventSlide['title'] : 'Hands-on Weekend Bootcamps & Workshops';
                          $eDate = !empty($eventSlide['deadline_date']) ? date('F d, Y', strtotime($eventSlide['deadline_date'])) : 'Upcoming Community Sessions';
                        ?>
                        <?php if ($eImg): ?>
                          <img src="<?php echo htmlspecialchars($eImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($eTitle, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-4 shadow mb-3" style="max-height: 200px; width: 100%; object-fit: cover;">
                        <?php else: ?>
                          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 90px; height: 90px; background: rgba(244,180,26,0.2); border: 2px solid #f4b41a;">
                            <i class="fa-solid fa-calendar-days fa-3x text-warning"></i>
                          </div>
                        <?php endif; ?>
                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($eTitle, ENT_QUOTES, 'UTF-8'); ?></h5>
                        <div class="p-2 rounded-3 bg-dark bg-opacity-40 mb-3 small text-warning fw-semibold">
                          <i class="fa-solid fa-calendar-day me-1"></i><?php echo $eDate; ?>
                        </div>
                        <span class="badge bg-success px-3 py-2 rounded-pill">
                          <i class="fa-solid fa-universal-access me-1"></i>100% Free Sponsored
                        </span>

                      <?php elseif ($cardType === 'custom_image' || !empty($slideImg)): ?>
                        <?php if ($slideImg): ?>
                          <img src="<?php echo htmlspecialchars($slideImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded-4 shadow mb-3" style="max-height: 240px; width: 100%; object-fit: cover;">
                        <?php else: ?>
                          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 90px; height: 90px; background: rgba(244,180,26,0.18); border: 2px solid #f4b41a;">
                            <i class="fa-solid fa-layer-group fa-3x text-warning"></i>
                          </div>
                        <?php endif; ?>
                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-semibold">
                          <i class="fa-solid fa-star me-1"></i>Kibiya Hub Feature
                        </span>

                      <?php else: ?>
                        <!-- Standard Graphic Card -->
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 90px; height: 90px; background: rgba(244,180,26,0.18); border: 2px solid #f4b41a;">
                          <i class="fa-solid fa-laptop-code fa-3x text-warning"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Free Digital Skills For All</h4>
                        <p class="text-white-50 small mb-4">Empowering students, women, and innovators across Kibiya Local Government Area with future-ready skills.</p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                          <span class="badge bg-white bg-opacity-15 text-white py-2 px-3 border border-white border-opacity-25 rounded-pill"><i class="fa-solid fa-robot me-1 text-warning"></i>AI &amp; Data</span>
                          <span class="badge bg-white bg-opacity-15 text-white py-2 px-3 border border-white border-opacity-25 rounded-pill"><i class="fa-solid fa-code me-1 text-warning"></i>Web &amp; Python</span>
                          <span class="badge bg-white bg-opacity-15 text-white py-2 px-3 border border-white border-opacity-25 rounded-pill"><i class="fa-solid fa-shield-halved me-1 text-warning"></i>Cybersecurity</span>
                        </div>
                      <?php endif; ?>

                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Carousel Previous / Next Navigation Arrows -->
      <button class="carousel-control-prev hero-carousel-control" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="prev" aria-label="Previous Slide">
        <i class="fa-solid fa-chevron-left text-white fs-5"></i>
      </button>
      <button class="carousel-control-next hero-carousel-control" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="next" aria-label="Next Slide">
        <i class="fa-solid fa-chevron-right text-white fs-5"></i>
      </button>

    <?php else: ?>
      <!-- Safe Fallback Slide if database has no active slides -->
      <div class="carousel-inner">
        <div class="carousel-item active" data-bs-interval="10000">
          <div class="hero-slide" style="background: linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%);">
            <div class="container position-relative" style="z-index: 2;">
              <div class="row align-items-center">
                <div class="col-lg-7 text-white py-4">
                  <span class="badge bg-warning text-dark px-3 py-2 mb-3 rounded-pill fw-semibold">
                    <i class="fa-solid fa-rocket me-1"></i> Empowering Communities Through Technology
                  </span>
                  <h1 class="display-4 fw-bold mb-3">Learn, Grow &amp; Innovate <span style="color: #f4b41a;">at Kibiya Hub</span></h1>
                  <p class="lead mb-4 opacity-90">Access free digital skills training, tech courses, mentorship, and earn recognized certificates from Kibiya Technology &amp; Innovation Hub.</p>
                  <a href="courses.php" class="btn btn-warning btn-lg fw-bold px-4 shadow">Browse Courses</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var carouselEl = document.querySelector('#homeHeroCarousel');
  if (carouselEl && window.bootstrap) {
    var carousel = new bootstrap.Carousel(carouselEl, {
      interval: 10000,
      ride: 'carousel',
      pause: false,
      wrap: true
    });
  }
});
</script>

<!-- Stats Bar -->
<section class="bg-white shadow-sm">
  <div class="container py-4">
    <div class="row text-center g-4">
      <div class="col-md-4">
        <div class="d-flex align-items-center justify-content-center gap-3">
          <div class="rounded-circle bg-hub d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
            <i class="fa-solid fa-users text-white fa-lg"></i>
          </div>
          <div class="text-start">
            <h3 class="fw-bold mb-0 text-hub"><?php echo (int)$total_students; ?>+</h3>
            <small class="text-muted">Students Enrolled</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="d-flex align-items-center justify-content-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:#f4b41a;">
            <i class="fa-solid fa-book fa-lg text-dark"></i>
          </div>
          <div class="text-start">
            <h3 class="fw-bold mb-0" style="color:#f4b41a;"><?php echo (int)$total_courses; ?>+</h3>
            <small class="text-muted">Available Courses</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="d-flex align-items-center justify-content-center gap-3">
          <div class="rounded-circle bg-info d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
            <i class="fa-solid fa-chalkboard-user text-white fa-lg"></i>
          </div>
          <div class="text-start">
            <h3 class="fw-bold mb-0 text-info"><?php echo (int)$total_facilitators; ?>+</h3>
            <small class="text-muted">Expert Facilitators</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Courses -->
<section class="bg-light py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold text-hub"><i class="fa-solid fa-star me-2 text-warning"></i>Featured Courses</h2>
      <p class="text-muted">Start your learning journey with our most popular programs</p>
    </div>
    <div class="row g-4">
      <?php if ($courses->num_rows > 0): ?>
        <?php 
        function getHomeCourseTheme($title) {
            $t = strtolower($title);
            if (str_contains($t, 'automation') || str_contains($t, 'agent')) return ['icon' => 'fa-solid fa-robot', 'gradient' => 'linear-gradient(135deg, #059669, #10b981)'];
            if (str_contains($t, 'animation') || str_contains($t, 'content')) return ['icon' => 'fa-solid fa-wand-magic-sparkles', 'gradient' => 'linear-gradient(135deg, #6d28d9, #8b5cf6)'];
            if (str_contains($t, 'app') || str_contains($t, 'mobile')) return ['icon' => 'fa-solid fa-mobile-screen-button', 'gradient' => 'linear-gradient(135deg, #1d4ed8, #3b82f6)'];
            if (str_contains($t, 'cloud')) return ['icon' => 'fa-solid fa-cloud', 'gradient' => 'linear-gradient(135deg, #0369a1, #0284c7)'];
            if (str_contains($t, 'cybersecurity') || str_contains($t, 'security')) return ['icon' => 'fa-solid fa-shield-halved', 'gradient' => 'linear-gradient(135deg, #b91c1c, #ef4444)'];
            if (str_contains($t, 'data')) return ['icon' => 'fa-solid fa-chart-pie', 'gradient' => 'linear-gradient(135deg, #d97706, #f59e0b)'];
            if (str_contains($t, 'dev ops') || str_contains($t, 'devops')) return ['icon' => 'fa-solid fa-gears', 'gradient' => 'linear-gradient(135deg, #4338ca, #6366f1)'];
            if (str_contains($t, 'marketing')) return ['icon' => 'fa-solid fa-bullhorn', 'gradient' => 'linear-gradient(135deg, #be185d, #ec4899)'];
            if (str_contains($t, 'graphic')) return ['icon' => 'fa-solid fa-palette', 'gradient' => 'linear-gradient(135deg, #7e22ce, #a855f7)'];
            if (str_contains($t, 'machine learning')) return ['icon' => 'fa-solid fa-brain', 'gradient' => 'linear-gradient(135deg, #0891b2, #06b6d4)'];
            if (str_contains($t, 'python')) return ['icon' => 'fa-brands fa-python', 'gradient' => 'linear-gradient(135deg, #0284c7, #38bdf8)'];
            if (str_contains($t, 'ui/ux') || str_contains($t, 'ux')) return ['icon' => 'fa-solid fa-bezier-curve', 'gradient' => 'linear-gradient(135deg, #e11d48, #f43f5e)'];
            if (str_contains($t, 'web')) return ['icon' => 'fa-solid fa-code', 'gradient' => 'linear-gradient(135deg, #0b6623, #15803d)'];
            if (str_contains($t, 'livestock') || str_contains($t, 'farming')) return ['icon' => 'fa-solid fa-cow', 'gradient' => 'linear-gradient(135deg, #4d7c0f, #65a30d)'];
            if (str_contains($t, 'hardware') || str_contains($t, 'gsm')) return ['icon' => 'fa-solid fa-microchip', 'gradient' => 'linear-gradient(135deg, #b45309, #f59e0b)'];
            if (str_contains($t, 'leather')) return ['icon' => 'fa-solid fa-scissors', 'gradient' => 'linear-gradient(135deg, #7c2d12, #c2410c)'];
            if (str_contains($t, 'satellite') || str_contains($t, 'cctv')) return ['icon' => 'fa-solid fa-satellite-dish', 'gradient' => 'linear-gradient(135deg, #0369a1, #0ea5e9)'];
            if (str_contains($t, 'networking')) return ['icon' => 'fa-solid fa-network-wired', 'gradient' => 'linear-gradient(135deg, #1e40af, #2563eb)'];
            if (str_contains($t, 'catering')) return ['icon' => 'fa-solid fa-utensils', 'gradient' => 'linear-gradient(135deg, #c2410c, #ea580c)'];
            if (str_contains($t, 'refrigerator') || str_contains($t, 'air-condition')) return ['icon' => 'fa-solid fa-snowflake', 'gradient' => 'linear-gradient(135deg, #0369a1, #38bdf8)'];
            return ['icon' => 'fa-solid fa-book-open', 'gradient' => 'linear-gradient(135deg, #0b6623, #0d7a2b)'];
        }
        ?>
        <?php while ($c = $courses->fetch_assoc()): ?>
          <?php $thm = getHomeCourseTheme($c['title']); ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column" style="border-radius: 16px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 12px 32px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
              <div class="p-4 text-center" style="background: <?php echo $thm['gradient']; ?>; min-height: 100px; display:flex; align-items:center; justify-content:center;">
                <i class="<?php echo $thm['icon']; ?> fa-3x text-white opacity-75"></i>
              </div>
              <div class="card-body p-4 d-flex flex-column flex-grow-1">
                <span class="badge bg-warning text-dark mb-2 align-self-start"><?php echo htmlspecialchars($c['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                <h5 class="fw-bold mb-2">
                  <a href="course_view.php?id=<?php echo (int)$c['id']; ?>" class="text-dark text-decoration-none">
                    <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                </h5>
                <p class="text-muted small flex-grow-1"><?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 110, '...'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                  <a href="course_view.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-circle-info me-1"></i>Details
                  </a>
                  <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student'): ?>
                    <a href="student_dashboard.php?enroll_id=<?php echo (int)$c['id']; ?>" class="btn btn-hub btn-sm fw-semibold">Enroll Now</a>
                  <?php elseif (isset($_SESSION['user_id'])): ?>
                    <a href="course_view.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-hub btn-sm">View Course</a>
                  <?php else: ?>
                    <a href="login.php" class="btn btn-outline-success btn-sm">Login to Enroll</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="col-12 text-center">
          <div class="py-5">
            <i class="fa-solid fa-book-open fa-3x text-muted mb-3"></i>
            <p class="text-muted">No courses available yet. Check back soon!</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <div class="text-center mt-4">
      <a href="courses.php" class="btn btn-hub btn-lg px-4 px-md-5 w-100 w-sm-auto"><i class="fa-solid fa-arrow-right me-2"></i>View All Courses</a>
    </div>
  </div>
</section>

<!-- Latest News & Opportunities Section -->
<?php if ($latestNews && $latestNews->num_rows > 0): ?>
<section class="py-5 bg-white border-top border-bottom">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-semibold mb-2">
          <i class="fa-solid fa-bullhorn me-1"></i> Hub Updates
        </span>
        <h2 class="fw-bold text-hub mb-1">Latest Opportunities &amp; Announcements</h2>
        <p class="text-muted mb-0">Explore incubation grants, graduate honors, and job openings.</p>
      </div>
      <div>
        <a href="news.php" class="btn btn-outline-success fw-semibold">
          View All Updates <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
      </div>
    </div>

    <div class="row g-4">
      <?php while ($nw = $latestNews->fetch_assoc()): ?>
        <?php
          $cat = $nw['category'] ?? 'Opportunities';
          $hasImg = (!empty($nw['image_path']) && file_exists(__DIR__ . '/' . $nw['image_path']));
          $badgeCol = !empty($nw['badge_color']) ? $nw['badge_color'] : 'bg-success';
          $ico = !empty($nw['icon']) ? $nw['icon'] : 'fa-newspaper';
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100 d-flex flex-column" style="border-radius: 16px; overflow: hidden; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-6px)'" onmouseout="this.style.transform=''">
            <?php if ($hasImg): ?>
              <img src="<?php echo htmlspecialchars($nw['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nw['title'], ENT_QUOTES, 'UTF-8'); ?>" style="height: 170px; width: 100%; object-fit: cover;">
            <?php else: ?>
              <div class="p-4 text-center text-white bg-hub" style="min-height: 110px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0b6623, #15803d);">
                <i class="fa-solid <?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?> fa-3x opacity-75"></i>
              </div>
            <?php endif; ?>
            <div class="card-body p-4 d-flex flex-column flex-grow-1">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge <?php echo htmlspecialchars($badgeCol, ENT_QUOTES, 'UTF-8'); ?> px-2 py-1 small">
                  <i class="fa-solid <?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($nw['created_at'])); ?></small>
              </div>
              <h5 class="fw-bold mb-2" style="line-height: 1.35;">
                <a href="news.php?post=<?php echo (int)$nw['id']; ?>" class="text-dark text-decoration-none">
                  <?php echo htmlspecialchars($nw['title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </h5>
              <p class="text-muted small flex-grow-1 mb-3">
                <?php
                  $snip = !empty($nw['summary']) ? $nw['summary'] : mb_strimwidth(strip_tags($nw['content']), 0, 110, '...');
                  echo htmlspecialchars($snip, ENT_QUOTES, 'UTF-8');
                ?>
              </p>
              <div class="pt-2 border-top mt-auto d-flex justify-content-between align-items-center">
                <a href="news.php?post=<?php echo (int)$nw['id']; ?>" class="btn btn-outline-success btn-sm fw-semibold">
                  Read Full Details
                </a>
                <?php if (!empty($nw['deadline_date'])): ?>
                  <small class="text-warning-emphasis fw-bold">
                    <i class="fa-solid fa-hourglass-half me-1"></i><?php echo date('M d', strtotime($nw['deadline_date'])); ?>
                  </small>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Why Choose Section -->
<section class="py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold text-hub">Why Choose Kibiya Hub?</h2>
      <p class="text-muted">Everything you need for digital empowerment under one roof</p>
    </div>
    <div class="row g-4">
      <div class="col-12 col-md-4">
        <div class="text-center p-4">
          <div class="rounded-circle bg-hub d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px;">
            <i class="fa-solid fa-graduation-cap fa-2x text-white"></i>
          </div>
          <h5 class="fw-bold">Free Education</h5>
          <p class="text-muted small">All our programs are completely free for community members in Kibiya LGA and beyond.</p>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-center p-4">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px;background:#f4b41a;">
            <i class="fa-solid fa-certificate fa-2x text-dark"></i>
          </div>
          <h5 class="fw-bold">Earn Certificates</h5>
          <p class="text-muted small">Complete courses and assessments to earn recognised digital certificates.</p>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-center p-4">
          <div class="rounded-circle bg-info d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px;">
            <i class="fa-solid fa-people-group fa-2x text-white"></i>
          </div>
          <h5 class="fw-bold">Expert Facilitators</h5>
          <p class="text-muted small">Learn from experienced trainers dedicated to your growth and success.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
