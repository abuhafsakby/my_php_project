<?php
require_once 'config/db.php';
include 'includes/header.php';
?>

<div class="bg-light py-4">
  <div class="container">
    <h1 class="fw-bold text-hub"><i class="fa-solid fa-building-columns me-2"></i>About Kibiya Hub</h1>
    <p class="text-muted mb-0">Our mission, vision, and commitment to digital empowerment</p>
  </div>
</div>

<div class="container my-5">
  <div class="row g-5 align-items-center mb-5">
    <div class="col-lg-6">
      <span class="badge bg-warning text-dark px-3 py-2 mb-3 rounded-pill">Our Story</span>
      <h2 class="fw-bold text-hub">Empowering Communities Through Technology</h2>
      <p class="text-muted">The Kibiya Technology and Innovation Hub was established to bridge the digital divide in Kibiya Local Government Area, Kano State, Nigeria. Our hub serves as a centre for digital literacy, tech skills acquisition, and innovation for community members of all ages.</p>
      <p class="text-muted">We believe that technology is the key to unlocking economic opportunities, improving education, and fostering sustainable community development. Through free training programmes, mentorship, and a supportive learning environment, we empower individuals to become creators, innovators, and leaders in the digital economy.</p>
    </div>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm" style="border-radius: 20px; overflow: hidden;">
        <div class="p-5 text-center" style="background: linear-gradient(135deg, #0b6623, #084919);">
          <i class="fa-solid fa-lightbulb fa-5x text-warning mb-3"></i>
          <h4 class="text-white fw-bold">Innovation Starts Here</h4>
          <p class="text-white-50 mb-0">Kibiya LGA, Kano State, Nigeria</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Mission & Vision -->
  <div class="row g-4 mb-5">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4" style="border-radius: 16px; border-left: 4px solid #0b6623 !important;">
        <h4 class="fw-bold text-hub"><i class="fa-solid fa-bullseye me-2"></i>Our Mission</h4>
        <p class="text-muted mb-0">To provide free, accessible, and high-quality digital skills training to the people of Kibiya and surrounding communities, enabling them to participate meaningfully in the global digital economy.</p>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4" style="border-radius: 16px; border-left: 4px solid #f4b41a !important;">
        <h4 class="fw-bold" style="color: #f4b41a;"><i class="fa-solid fa-eye me-2"></i>Our Vision</h4>
        <p class="text-muted mb-0">To become the leading technology and innovation hub in northern Nigeria, producing digitally skilled graduates who drive economic growth, create jobs, and inspire the next generation of tech leaders.</p>
      </div>
    </div>
  </div>

  <!-- Core Values -->
  <div class="text-center mb-4">
    <h2 class="fw-bold text-hub">Our Core Values</h2>
  </div>
  <div class="row g-4">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="text-center p-4">
        <div class="rounded-circle bg-hub d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
          <i class="fa-solid fa-handshake fa-xl text-white"></i>
        </div>
        <h6 class="fw-bold">Community First</h6>
        <p class="small text-muted">Everything we do is for the benefit of our local community.</p>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="text-center p-4">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:#f4b41a;">
          <i class="fa-solid fa-universal-access fa-xl text-dark"></i>
        </div>
        <h6 class="fw-bold">Accessibility</h6>
        <p class="small text-muted">Free education for everyone regardless of background.</p>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="text-center p-4">
        <div class="rounded-circle bg-info d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
          <i class="fa-solid fa-rocket fa-xl text-white"></i>
        </div>
        <h6 class="fw-bold">Innovation</h6>
        <p class="small text-muted">Encouraging creative problem solving and new ideas.</p>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="text-center p-4">
        <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
          <i class="fa-solid fa-award fa-xl text-white"></i>
        </div>
        <h6 class="fw-bold">Excellence</h6>
        <p class="small text-muted">Delivering the highest quality training and resources.</p>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
