<?php
require_once 'config/db.php';

// Increment views if a specific post modal is accessed via GET
$viewPostId = filter_input(INPUT_GET, 'post', FILTER_VALIDATE_INT);
if ($viewPostId) {
    $conn->query("UPDATE news SET views_count = views_count + 1 WHERE id = $viewPostId");
}

// Search and Category Filters
$selectedCategory = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');

// Category definition & visual metadata
$categoryMeta = [
    'Opportunities'   => ['icon' => 'fa-lightbulb', 'badge' => 'bg-success', 'gradient' => 'linear-gradient(135deg, #0b6623, #15803d)'],
    'Congratulations' => ['icon' => 'fa-award', 'badge' => 'bg-warning text-dark', 'gradient' => 'linear-gradient(135deg, #d97706, #f59e0b)'],
    'Jobs'            => ['icon' => 'fa-briefcase', 'badge' => 'bg-info text-dark', 'gradient' => 'linear-gradient(135deg, #0284c7, #0ea5e9)'],
    'Announcements'   => ['icon' => 'fa-bullhorn', 'badge' => 'bg-primary', 'gradient' => 'linear-gradient(135deg, #1d4ed8, #3b82f6)'],
    'Events'          => ['icon' => 'fa-calendar-days', 'badge' => 'bg-secondary', 'gradient' => 'linear-gradient(135deg, #4b5563, #6b7280)'],
];

// Fetch category counts
$catCounts = [];
$cRes = $conn->query("SELECT category, COUNT(*) AS count FROM news WHERE status = 'published' GROUP BY category");
if ($cRes) {
    while ($row = $cRes->fetch_assoc()) {
        $catCounts[$row['category']] = (int)$row['count'];
    }
}
$totalPublished = array_sum($catCounts);

// Build query
$where = ["status = 'published'"];
$params = [];
$types = '';

if ($selectedCategory !== '') {
    $where[] = "category = ?";
    $params[] = $selectedCategory;
    $types .= 's';
}

if ($searchQuery !== '') {
    $where[] = "(title LIKE ? OR summary LIKE ? OR content LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql = "SELECT * FROM news WHERE " . implode(' AND ', $where) . " ORDER BY is_featured DESC, id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$newsResult = $stmt->get_result();
$newsList = [];
while ($row = $newsResult->fetch_assoc()) {
    $newsList[] = $row;
}
$stmt->close();

$isAdmin = isset($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'admin');
?>
<?php include 'includes/header.php'; ?>

<style>
  .news-hero {
    background: linear-gradient(135deg, #0b6623 0%, #084919 60%, #032d0f 100%);
    padding: 3.5rem 0;
    position: relative;
    overflow: hidden;
  }
  .news-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 480px;
    height: 480px;
    background: radial-gradient(circle, rgba(244,180,26,0.18) 0%, transparent 70%);
    border-radius: 50%;
  }
  .news-card {
    border: none;
    border-radius: 18px;
    overflow: hidden;
    transition: transform 0.3s cubic-bezier(.4,0,.2,1), box-shadow 0.3s cubic-bezier(.4,0,.2,1);
    background: #ffffff;
  }
  .news-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 36px rgba(0,0,0,0.12) !important;
  }
  .news-card-img {
    height: 200px;
    width: 100%;
    object-fit: cover;
  }
  .news-icon-header {
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    position: relative;
  }
  .category-pill {
    transition: all 0.25s ease;
    border-radius: 50rem;
    font-weight: 600;
  }
  .category-pill.active {
    background-color: #0b6623 !important;
    color: #ffffff !important;
    border-color: #0b6623 !important;
  }
  .modal-post-img {
    max-height: 380px;
    width: 100%;
    object-fit: cover;
    border-radius: 14px;
  }
  @media (max-width: 767.98px) {
    .news-hero {
      padding: 2.25rem 0;
    }
    .news-hero h1 {
      font-size: 1.85rem;
    }
    .news-card-img {
      height: 170px;
    }
  }
</style>

<!-- Hero Section -->
<div class="news-hero text-white mb-4">
  <div class="container position-relative" style="z-index: 2;">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-semibold mb-2">
          <i class="fa-solid fa-bullhorn me-1"></i> Hub Updates &amp; Opportunities Desk
        </span>
        <h1 class="display-5 fw-bold mb-2">News, Opportunities &amp; Careers</h1>
        <p class="lead opacity-90 mb-0">Stay informed on incubation cohorts, scholarship opportunities, student graduations, and open job roles across Kibiya Tech Hub.</p>
      </div>
      <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
        <?php if ($isAdmin): ?>
          <a href="admin_dashboard.php#news-pane" class="btn btn-warning fw-bold shadow-sm">
            <i class="fa-solid fa-gear me-1"></i>Manage News &amp; Posts
          </a>
        <?php else: ?>
          <div class="d-inline-flex align-items-center gap-3 bg-white bg-opacity-10 px-3 py-2 rounded-4 border border-white border-opacity-25">
            <i class="fa-solid fa-newspaper fa-2x text-warning"></i>
            <div class="text-start">
              <div class="fw-bold fs-5 text-white"><?php echo (int)$totalPublished; ?></div>
              <small class="text-white-50">Active Updates</small>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="container my-4">

  <!-- Search & Category Filter Section -->
  <div class="card border-0 shadow-sm p-3 p-md-4 mb-4" style="border-radius: 18px;">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-12 col-md-8 col-lg-9">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0 text-muted">
            <i class="fa-solid fa-magnifying-glass"></i>
          </span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search opportunities, graduations, jobs, scholarships..." value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
          <?php if ($selectedCategory !== ''): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>">
          <?php endif; ?>
        </div>
      </div>
      <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
        <button type="submit" class="btn btn-hub flex-grow-1 fw-semibold">
          <i class="fa-solid fa-filter me-1"></i>Search
        </button>
        <?php if ($searchQuery !== '' || $selectedCategory !== ''): ?>
          <a href="news.php" class="btn btn-outline-secondary" title="Clear Filters">
            <i class="fa-solid fa-rotate-left"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Category Pills -->
    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top align-items-center">
      <small class="text-muted fw-semibold me-1"><i class="fa-solid fa-tags me-1"></i>Channels:</small>
      
      <a href="news.php<?php echo $searchQuery !== '' ? '?search=' . urlencode($searchQuery) : ''; ?>" 
         class="btn btn-sm <?php echo $selectedCategory === '' ? 'btn-hub active' : 'btn-outline-secondary'; ?> category-pill px-3 py-1">
        All Updates <span class="badge <?php echo $selectedCategory === '' ? 'bg-white text-dark' : 'bg-secondary text-white'; ?> ms-1"><?php echo (int)$totalPublished; ?></span>
      </a>

      <?php foreach ($categoryMeta as $catName => $meta): ?>
        <?php $count = $catCounts[$catName] ?? 0; ?>
        <a href="news.php?category=<?php echo urlencode($catName); ?><?php echo $searchQuery !== '' ? '&search=' . urlencode($searchQuery) : ''; ?>" 
           class="btn btn-sm <?php echo $selectedCategory === $catName ? 'btn-hub active' : 'btn-outline-secondary'; ?> category-pill px-3 py-1">
          <i class="fa-solid <?php echo $meta['icon']; ?> me-1"></i><?php echo $catName; ?>
          <span class="badge <?php echo $selectedCategory === $catName ? 'bg-white text-dark' : 'bg-light text-dark border'; ?> ms-1"><?php echo $count; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($newsList)): ?>
    <div class="row g-4">
      <?php foreach ($newsList as $item): ?>
        <?php
          $cat = $item['category'] ?? 'Opportunities';
          $meta = $categoryMeta[$cat] ?? [
              'icon' => 'fa-newspaper',
              'badge' => 'bg-success',
              'gradient' => 'linear-gradient(135deg, #0b6623, #15803d)'
          ];
          $badgeColor = !empty($item['badge_color']) ? $item['badge_color'] : $meta['badge'];
          $icon = !empty($item['icon']) ? $item['icon'] : $meta['icon'];
          $hasImage = (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path']));
          $isFeatured = (bool)$item['is_featured'];
          $hasDeadline = !empty($item['deadline_date']);
          $isExpired = false;
          if ($hasDeadline) {
              $isExpired = (strtotime($item['deadline_date']) < strtotime('today'));
          }
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card news-card shadow-sm h-100 d-flex flex-column position-relative">
            
            <?php if ($isFeatured): ?>
              <span class="position-absolute top-0 start-0 m-3 badge bg-danger shadow-sm fw-bold px-3 py-2 rounded-pill" style="z-index: 3;">
                <i class="fa-solid fa-star me-1 text-warning"></i>Featured
              </span>
            <?php endif; ?>

            <?php if ($hasImage): ?>
              <img src="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" class="news-card-img">
            <?php else: ?>
              <div class="news-icon-header" style="background: <?php echo $meta['gradient']; ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> fa-3x opacity-85"></i>
              </div>
            <?php endif; ?>

            <div class="card-body p-4 d-flex flex-column flex-grow-1">
              
              <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                <span class="badge <?php echo htmlspecialchars($badgeColor, ENT_QUOTES, 'UTF-8'); ?> px-2 py-1">
                  <i class="fa-solid <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <small class="text-muted">
                  <i class="fa-regular fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                </small>
              </div>

              <h5 class="fw-bold text-dark mb-2" style="line-height: 1.35;">
                <a href="#" class="text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#newsModal<?php echo (int)$item['id']; ?>">
                  <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </h5>

              <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.5;">
                <?php 
                  $displaySummary = !empty($item['summary']) ? $item['summary'] : mb_strimwidth(strip_tags($item['content']), 0, 130, '...');
                  echo htmlspecialchars($displaySummary, ENT_QUOTES, 'UTF-8');
                ?>
              </p>

              <?php if ($hasDeadline): ?>
                <div class="p-2 rounded-3 mb-3 small d-flex align-items-center justify-content-between <?php echo $isExpired ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-dark'; ?>">
                  <span><i class="fa-solid fa-clock me-1"></i>Deadline:</span>
                  <strong><?php echo date('M d, Y', strtotime($item['deadline_date'])); ?> <?php if ($isExpired) echo '(Closed)'; ?></strong>
                </div>
              <?php endif; ?>

              <div class="d-flex align-items-center justify-content-between pt-2 border-top mt-auto">
                <button type="button" class="btn btn-outline-success btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#newsModal<?php echo (int)$item['id']; ?>">
                  <i class="fa-solid fa-book-open-reader me-1"></i>Read Details
                </button>

                <?php if (!empty($item['external_link'])): ?>
                  <a href="<?php echo htmlspecialchars($item['external_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-hub btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?php echo htmlspecialchars(!empty($item['link_label']) ? $item['link_label'] : 'Apply / Link', ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                <?php endif; ?>
              </div>

            </div>
          </div>
        </div>

        <!-- Detail Modal for Post -->
        <div class="modal fade" id="newsModal<?php echo (int)$item['id']; ?>" tabindex="-1" aria-labelledby="newsModalLabel<?php echo (int)$item['id']; ?>" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
              
              <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-start">
                <div>
                  <span class="badge <?php echo htmlspecialchars($badgeColor, ENT_QUOTES, 'UTF-8'); ?> px-3 py-2 mb-2">
                    <i class="fa-solid <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                  <?php if ($isFeatured): ?>
                    <span class="badge bg-danger px-3 py-2 mb-2 ms-1">
                      <i class="fa-solid fa-star me-1 text-warning"></i>Featured
                    </span>
                  <?php endif; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body px-4 pt-2 pb-4">
                <h3 class="fw-bold text-hub mb-3" id="newsModalLabel<?php echo (int)$item['id']; ?>">
                  <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                </h3>

                <div class="d-flex flex-wrap gap-3 align-items-center text-muted small mb-3 pb-3 border-bottom">
                  <span><i class="fa-solid fa-user-pen me-1 text-hub"></i>Published by: <strong><?php echo htmlspecialchars($item['author_name'] ?: 'Kibiya Hub Editorial', ENT_QUOTES, 'UTF-8'); ?></strong></span>
                  <span><i class="fa-regular fa-calendar me-1 text-hub"></i>Date: <?php echo date('F d, Y', strtotime($item['created_at'])); ?></span>
                  <?php if ($hasDeadline): ?>
                    <span class="<?php echo $isExpired ? 'text-danger fw-bold' : 'text-warning-emphasis fw-bold'; ?>">
                      <i class="fa-solid fa-hourglass-half me-1"></i>Application Deadline: <?php echo date('F d, Y', strtotime($item['deadline_date'])); ?>
                    </span>
                  <?php endif; ?>
                  <span><i class="fa-regular fa-eye me-1"></i><?php echo (int)$item['views_count']; ?> views</span>
                </div>

                <?php if ($hasImage): ?>
                  <div class="mb-4 text-center">
                    <img src="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" class="modal-post-img shadow-sm">
                  </div>
                <?php endif; ?>

                <?php if (!empty($item['summary'])): ?>
                  <div class="p-3 bg-light rounded-3 border-start border-4 border-success mb-3">
                    <p class="fw-semibold text-secondary mb-0"><?php echo nl2br(htmlspecialchars($item['summary'], ENT_QUOTES, 'UTF-8')); ?></p>
                  </div>
                <?php endif; ?>

                <div class="text-secondary" style="line-height: 1.75; font-size: 1.02rem; white-space: pre-line;">
                  <?php echo htmlspecialchars($item['content'], ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <?php if (!empty($item['external_link'])): ?>
                  <div class="mt-4 p-4 rounded-4 text-center" style="background: linear-gradient(135deg, #f8fdf9, #e8f5e9); border: 1px solid #c8e6c9;">
                    <h5 class="fw-bold text-hub mb-2">Ready to Take the Next Step?</h5>
                    <p class="text-muted small mb-3">Follow the link below to apply, register, or access the source resource.</p>
                    <a href="<?php echo htmlspecialchars($item['external_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-hub btn-lg fw-bold px-4 shadow-sm">
                      <i class="fa-solid fa-arrow-up-right-from-square me-2"></i><?php echo htmlspecialchars(!empty($item['link_label']) ? $item['link_label'] : 'Proceed to Link', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </div>
                <?php endif; ?>

              </div>

              <div class="modal-footer border-0 bg-light px-4 py-3 d-flex justify-content-between">
                <?php if ($isAdmin): ?>
                  <a href="admin_dashboard.php#news-pane" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit in Admin Panel
                  </a>
                <?php else: ?>
                  <span class="small text-muted"><i class="fa-solid fa-shield-halved me-1 text-hub"></i>Official Hub Announcement</span>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm fw-semibold" data-bs-dismiss="modal">Close</button>
              </div>

            </div>
          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm text-center py-5 px-3 my-4" style="border-radius: 18px;">
      <div class="my-4">
        <i class="fa-solid fa-newspaper fa-4x text-muted opacity-50 mb-3"></i>
        <h4 class="fw-bold text-dark">No News Found</h4>
        <p class="text-muted" style="max-width: 480px; margin: 0 auto;">There are currently no updates matching your search or category filter. Check back soon or try clearing your filters.</p>
        <div class="mt-4">
          <a href="news.php" class="btn btn-hub px-4 fw-semibold">
            <i class="fa-solid fa-rotate-left me-1"></i>View All News
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Call to Action Banner -->
  <div class="card border-0 shadow-sm mt-5 p-4 p-md-5 text-center text-white" style="border-radius: 20px; background: linear-gradient(135deg, #0b6623, #084919);">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-10 rounded-circle mb-3" style="width: 60px; height: 60px;">
          <i class="fa-solid fa-handshake-angle fa-2x text-warning"></i>
        </div>
        <h3 class="fw-bold mb-2">Have an Opportunity or Job Opening to Share?</h3>
        <p class="text-white-50 mb-4">Partner with Kibiya Hub to post internships, grant programmes, or employment opportunities for our community and skilled digital graduates.</p>
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
          <a href="contact.php" class="btn btn-warning btn-lg fw-bold px-4 shadow">
            <i class="fa-solid fa-paper-plane me-2"></i>Contact Editorial Team
          </a>
          <a href="about.php" class="btn btn-outline-light btn-lg px-4">
            <i class="fa-solid fa-circle-info me-2"></i>About Our Mission
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>
