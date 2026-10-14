<footer class="bg-dark text-white pt-5 pb-3 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-12 col-md-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <?php if (!empty($logoSrc)): ?>
            <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Kibiya Hub Logo" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover; border: 2px solid rgba(255,255,255,0.7); background: #ffffff;">
          <?php else: ?>
            <i class="fa-solid fa-lightbulb text-warning me-1"></i>
          <?php endif; ?>
          <h5 class="text-warning fw-bold mb-0">Kibiya Tech Hub</h5>
        </div>
        <p class="small text-muted">Empowering local communities with digital skills, tech innovation, entrepreneurship, and sustainable education.</p>
      </div>
      <div class="col-12 col-sm-6 col-md-4">
        <h5>Quick Links</h5>
        <ul class="list-unstyled small">
          <li><a href="about.php" class="text-white-50 text-decoration-none">About Our Mission</a></li>
          <li><a href="courses.php" class="text-white-50 text-decoration-none">Explore All Courses</a></li>
          <li><a href="news.php" class="text-white-50 text-decoration-none">News &amp; Opportunities</a></li>
          <li><a href="verify_certificate.php" class="text-warning text-decoration-none"><i class="fa-solid fa-shield-halved me-1"></i>Verify Certificate</a></li>
          <li><a href="management.php" class="text-white-50 text-decoration-none">Executive Team</a></li>
          <li><a href="contact.php" class="text-white-50 text-decoration-none">Get In Touch</a></li>
        </ul>
      </div>
      <div class="col-12 col-sm-6 col-md-4">
        <h5>Contact Details</h5>
        <p class="small text-white-50 mb-1"><i class="fa-solid fa-location-dot me-2"></i>Kibiya LGA, Kano State, Nigeria</p>
        <p class="small text-white-50 mb-1"><i class="fa-solid fa-envelope me-2"></i>info@kibiyahub.org</p>
        <p class="small text-white-50"><i class="fa-solid fa-phone me-2"></i>+234 800 000 0000</p>
      </div>
    </div>
    <hr class="border-secondary mt-4">
    <div class="text-center small text-muted">
      &copy; <?php echo date('Y'); ?> Kibiya Technology and Innovation Hub. All Rights Reserved.
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['user_id'])): ?>
<?php
  $currentUserRole = $_SESSION['role'] ?? 'student';
  $idleLimitSeconds = defined('SESSION_IDLE_TIMEOUT') ? SESSION_IDLE_TIMEOUT : 300; // 5 minutes max for all roles
  $warningSeconds = 60; // 60-second warning countdown before auto logout
?>

<!-- Inactivity Warning Modal -->
<div class="modal fade" id="inactivityWarningModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="inactivityModalLabel" aria-hidden="true" style="z-index: 10800;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
      <div class="modal-header bg-warning text-dark border-0 py-3">
        <h5 class="modal-title fw-bold" id="inactivityModalLabel">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>Inactivity Security Notice
        </h5>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="mb-3">
          <span class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-25 rounded-circle" style="width: 76px; height: 76px;">
            <i class="fa-solid fa-clock-rotate-left fa-2x text-warning"></i>
          </span>
        </div>
        <h5 class="fw-bold mb-2">Are you still working, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?>?</h5>
        <p class="text-muted small mb-3">
          You have been inactive for some time. For security reasons, your <strong><?php echo ucfirst(htmlspecialchars($currentUserRole, ENT_QUOTES, 'UTF-8')); ?></strong> session will automatically log out in:
        </p>
        <div class="display-5 fw-bold text-danger mb-2 font-monospace" id="inactivityCountdownText">
          60s
        </div>
        <div class="progress mb-3" style="height: 8px; border-radius: 4px; background-color: #e9ecef;">
          <div id="inactivityProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 100%; transition: width 0.4s linear;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <p class="text-secondary small mb-0">
          Click <strong>Keep Working</strong> to maintain your session, or you will be automatically redirected to the login page.
        </p>
      </div>
      <div class="modal-footer bg-light border-0 d-flex justify-content-center gap-2 py-3 flex-column flex-sm-row">
        <button type="button" class="btn btn-hub fw-bold px-4 py-2 w-100 w-sm-auto" id="btnKeepActive">
          <i class="fa-solid fa-rotate-right me-1"></i> Keep Working (Stay Logged In)
        </button>
        <a href="logout.php?timeout=1" class="btn btn-outline-secondary fw-semibold px-3 py-2 w-100 w-sm-auto">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Log Out Now
        </a>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const IDLE_LIMIT_MS = <?php echo (int)($idleLimitSeconds * 1000); ?>;
  const WARNING_MS = <?php echo (int)($warningSeconds * 1000); ?>;
  const WARNING_START_MS = Math.max(0, IDLE_LIMIT_MS - WARNING_MS);

  let lastActivity = Date.now();
  let warningModalInstance = null;
  let isModalOpen = false;
  let isLoggedOut = false;
  let lastServerPing = Date.now();

  const modalEl = document.getElementById('inactivityWarningModal');
  const countdownEl = document.getElementById('inactivityCountdownText');
  const progressEl = document.getElementById('inactivityProgressBar');
  const keepActiveBtn = document.getElementById('btnKeepActive');

  function getModal() {
    if (!warningModalInstance && window.bootstrap && modalEl) {
      warningModalInstance = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: false
      });
    }
    return warningModalInstance;
  }

  function pingServer() {
    lastServerPing = Date.now();
    fetch('keep_alive.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function(res) {
        if (res.status === 401) {
          isLoggedOut = true;
          window.location.href = 'logout.php?timeout=1';
        }
      })
      .catch(function() {});
  }

  function resetActivity(forcePing) {
    if (isLoggedOut) return;
    lastActivity = Date.now();

    if (isModalOpen) {
      const modal = getModal();
      if (modal) {
        modal.hide();
      }
      isModalOpen = false;
      pingServer();
      return;
    }

    if (forcePing || (Date.now() - lastServerPing > 60000)) {
      pingServer();
    }
  }

  if (keepActiveBtn) {
    keepActiveBtn.addEventListener('click', function(e) {
      e.preventDefault();
      resetActivity(true);
    });
  }

  // Activity events
  const activityEvents = ['mousedown', 'keydown', 'keypress', 'scroll', 'touchstart', 'click'];
  activityEvents.forEach(function(evt) {
    window.addEventListener(evt, function() {
      if (!isModalOpen) {
        resetActivity(false);
      }
    }, { passive: true });
  });

  // Throttled mouse move
  let mouseThrottle = 0;
  window.addEventListener('mousemove', function() {
    const now = Date.now();
    if (!isModalOpen && now - mouseThrottle > 3000) {
      mouseThrottle = now;
      resetActivity(false);
    }
  }, { passive: true });

  // Real-time interval checker
  setInterval(function() {
    if (isLoggedOut) return;

    const elapsed = Date.now() - lastActivity;

    if (elapsed >= IDLE_LIMIT_MS) {
      isLoggedOut = true;
      window.location.href = 'logout.php?timeout=1';
      return;
    }

    if (elapsed >= WARNING_START_MS) {
      const remainingMs = IDLE_LIMIT_MS - elapsed;
      const remainingSec = Math.max(1, Math.ceil(remainingMs / 1000));

      if (!isModalOpen) {
        isModalOpen = true;
        const modal = getModal();
        if (modal) {
          modal.show();
        }
      }

      if (countdownEl) {
        countdownEl.textContent = remainingSec + 's';
      }
      if (progressEl) {
        const pct = Math.max(0, Math.min(100, (remainingMs / WARNING_MS) * 100));
        progressEl.style.width = pct + '%';
      }
    } else if (isModalOpen) {
      isModalOpen = false;
      const modal = getModal();
      if (modal) {
        modal.hide();
      }
    }
  }, 500);

  // Resume / Wake check
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && !isLoggedOut) {
      const elapsed = Date.now() - lastActivity;
      if (elapsed >= IDLE_LIMIT_MS) {
        isLoggedOut = true;
        window.location.href = 'logout.php?timeout=1';
      }
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
