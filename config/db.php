<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "kibiya_lms_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8mb4");

    // Auto-migration: Ensure enrollments table status column supports 'pending'
    $colCheck = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'status'");
    if ($colCheck && $row = $colCheck->fetch_assoc()) {
        if (strpos($row['Type'], 'pending') === false || $row['Default'] !== 'pending') {
            $conn->query("ALTER TABLE enrollments MODIFY COLUMN status ENUM('pending', 'enrolled', 'completed', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    // Auto-migration: Ensure news table exists
    $conn->query("CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'Opportunities',
        summary VARCHAR(350) DEFAULT NULL,
        content MEDIUMTEXT NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        badge_color VARCHAR(50) DEFAULT 'bg-success',
        icon VARCHAR(50) DEFAULT 'fa-newspaper',
        author_name VARCHAR(120) DEFAULT 'Kibiya Hub Editorial',
        external_link VARCHAR(255) DEFAULT NULL,
        link_label VARCHAR(100) DEFAULT 'Learn More / Apply',
        deadline_date DATE DEFAULT NULL,
        status ENUM('published', 'draft') DEFAULT 'published',
        is_featured TINYINT(1) DEFAULT 0,
        views_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed initial posts if news table is currently empty
    $newsCheck = $conn->query("SELECT COUNT(*) AS c FROM news");
    if ($newsCheck && ((int)$newsCheck->fetch_assoc()['c']) === 0) {
        $seedPosts = [
            [
                'NITDA & Kibiya Hub 2026 Tech Innovation Fellowship – Apply Now',
                'Opportunities',
                'Fully funded 3-month digital innovation and startup accelerator cohort for youth in Kibiya LGA and Kano State with seed grants.',
                "We are delighted to announce applications for the 2026 Tech Innovation Fellowship in partnership with NITDA and Kano State Tech Development agencies.\n\nSelected fellows will receive hands-on training in AI tools, software architecture, direct mentorship from industry engineers, and eligibility for up to ₦1,500,000 in micro seed capital.\n\nKey Requirements:\n• Must be an active student or graduate of Kibiya Hub or a resident of Kano State.\n• Strong commitment to community problem-solving and software innovation.\n• Weekly 12-hour hybrid participation.\n\nApplications close soon. All shortlisted applicants will be contacted via email.",
                'bg-success',
                'fa-lightbulb',
                'Director of Partnerships',
                'contact.php',
                'Apply for Fellowship',
                date('Y-m-d', strtotime('+30 days')),
                'published',
                1
            ],
            [
                'Huge Congratulations to Cohort 4 Graduates on Earning Their Professional Certifications!',
                'Congratulations',
                'Celebrating over 85 students across Kibiya LGA who successfully completed curriculum modules and passed final assessments.',
                "The Management and Lead Facilitators of Kibiya Technology and Innovation Hub warmly congratulate our Cohort 4 students for completing their intensive courses in Full-Stack Web Development, Python Programming, Cybersecurity, Satellite/CCTV Installation, and Graphics Design!\n\nEvery student has demonstrated tremendous grit, creativity, and commitment. Your certificates are fully verified, cryptographically signed, and ready for deployment in your professional portfolios.\n\n'Education is the most powerful weapon which you can use to change the world.' We are immensely proud of you all and look forward to witnessing the tech startups, freelance careers, and community services you build!",
                'bg-warning text-dark',
                'fa-award',
                'Musa Abdullahi Kibiya, Executive Director',
                'verify_certificate.php',
                'Verify Credentials',
                null,
                'published',
                1
            ],
            [
                'Job Alert: ICT Facilitator & Junior Web Developer Needed at Kibiya Hub Tech Center',
                'Jobs',
                'Exciting full-time and contract openings for energetic tech instructors and web developers to lead student cohorts in Kibiya.',
                "Kibiya Technology & Innovation Hub is expanding our instructional and engineering team. We are currently recruiting passionate candidates for the following roles:\n\n1. Lead Web Development Instructor (PHP, JavaScript, MySQL, HTML5/CSS3)\n2. Junior Systems Administrator & IT Support Officer\n\nKey Responsibilities:\n• Deliver engaging practical curriculum to youth and community learners.\n• Maintain lab hardware, network infrastructure, and student lab equipment.\n• Collaborate with executive leadership on community hackathons and digital literacy drives.\n\nCompetitive monthly compensation, performance bonuses, and career advancement within our partner network.",
                'bg-info text-dark',
                'fa-briefcase',
                'Human Resources',
                'contact.php',
                'Submit Resume / CV',
                date('Y-m-d', strtotime('+21 days')),
                'published',
                0
            ],
            [
                'Announcement: New Free Weekend Coding Bootcamp for Secondary School Girls',
                'Announcements',
                'Promoting gender equity in tech with our sponsored Saturday coding clubs and robotics workshops across Kibiya schools.',
                "In line with our mission to make high-quality technology education accessible to everyone, Kibiya Hub announces the official kickoff of our Weekend Girls in Tech Initiative.\n\nThe bootcamp provides free transport subsidies, practical computer access, lunch, and female tech role-model mentorship every Saturday from 9:00 AM to 1:00 PM. Parents and school principals are encouraged to enroll their wards early as seats are limited to 50 students per stream.",
                'bg-primary',
                'fa-bullhorn',
                'Community Outreach Unit',
                'courses.php',
                'Explore Courses',
                date('Y-m-d', strtotime('+14 days')),
                'published',
                0
            ]
        ];

        $insStmt = $conn->prepare("INSERT INTO news (title, category, summary, content, badge_color, icon, author_name, external_link, link_label, deadline_date, status, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($seedPosts as $p) {
            $insStmt->bind_param("sssssssssssi", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11]);
            $insStmt->execute();
        }
        $insStmt->close();
    }

    // Auto-migration: Ensure header_slides table exists
    $conn->query("CREATE TABLE IF NOT EXISTS header_slides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slide_type VARCHAR(50) DEFAULT 'custom',
        badge_text VARCHAR(150) DEFAULT 'Kibiya Tech Hub',
        badge_icon VARCHAR(50) DEFAULT 'fa-rocket',
        badge_color VARCHAR(50) DEFAULT 'bg-warning text-dark',
        title VARCHAR(255) NOT NULL,
        title_highlight VARCHAR(150) DEFAULT NULL,
        subtitle TEXT NOT NULL,
        quote_author VARCHAR(150) DEFAULT NULL,
        quote_author_role VARCHAR(150) DEFAULT NULL,
        btn_primary_text VARCHAR(100) DEFAULT 'Browse Courses',
        btn_primary_link VARCHAR(255) DEFAULT 'courses.php',
        btn_primary_icon VARCHAR(50) DEFAULT 'fa-book-open',
        btn_secondary_text VARCHAR(100) DEFAULT NULL,
        btn_secondary_link VARCHAR(255) DEFAULT NULL,
        btn_secondary_icon VARCHAR(50) DEFAULT 'fa-user-plus',
        bg_gradient VARCHAR(150) DEFAULT 'linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)',
        card_type VARCHAR(50) DEFAULT 'graphic',
        image_path VARCHAR(255) DEFAULT NULL,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed initial header slides if table is currently empty
    $slidesCheck = $conn->query("SELECT COUNT(*) AS c FROM header_slides");
    if ($slidesCheck && ((int)$slidesCheck->fetch_assoc()['c']) === 0) {
        $seedSlides = [
            [
                'welcome',
                'Empowering Communities Through Technology',
                'fa-rocket',
                'bg-warning text-dark',
                'Learn, Grow & Innovate',
                'at Kibiya Hub',
                'Access free digital skills training, tech courses, mentorship, and earn recognized certificates — all from Kibiya Technology & Innovation Hub.',
                null,
                null,
                'Browse 20+ Courses',
                'courses.php',
                'fa-book-open',
                'Join Free Account',
                'register.php',
                'fa-user-plus',
                'linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)',
                'graphic',
                null,
                1,
                1
            ],
            [
                'ceo',
                'Executive Leadership & Vision',
                'fa-crown',
                'bg-warning text-dark',
                "Building Tomorrow's Leaders",
                'Through Digital Innovation',
                '"Our vision is to bridge the digital divide in Kibiya LGA and northern Nigeria by providing free, world-class technology education that sparks startups, creates jobs, and empowers our community."',
                'Musa Abdullahi Kibiya',
                'Executive Director',
                'Meet Hub Leadership',
                'management.php',
                'fa-sitemap',
                'Our Mission & Impact',
                'about.php',
                'fa-building-columns',
                'linear-gradient(135deg, #063914 0%, #094a1b 55%, #15803d 100%)',
                'ceo_profile',
                null,
                2,
                1
            ],
            [
                'news',
                'Featured Hub News & Opportunities',
                'fa-bullhorn',
                'bg-warning text-dark',
                'NITDA & Kibiya Hub 2026 Tech Fellowship',
                'Applications Open',
                'Fully funded 3-month digital innovation and startup accelerator cohort for youth in Kibiya LGA and Kano State with seed grant eligibility up to ₦1,500,000.',
                null,
                null,
                'Read Full Story & Apply',
                'news.php',
                'fa-book-open-reader',
                'All News & Careers',
                'news.php',
                'fa-newspaper',
                'linear-gradient(135deg, #092e15 0%, #0b5722 55%, #0284c7 100%)',
                'news_highlight',
                null,
                3,
                1
            ],
            [
                'event',
                'Upcoming Event & Community Workshop',
                'fa-calendar-days',
                'bg-info text-dark',
                'Weekend Girls in Tech & Robotics Bootcamp',
                '100% Free Admission',
                'Free transport subsidies, practical computer access, lunch, and female tech mentorship every Saturday at Kibiya Tech Hub Complex.',
                null,
                null,
                'Event Details & Register',
                'news.php?category=Events',
                'fa-calendar-check',
                'Contact Event Team',
                'contact.php',
                'fa-envelope',
                'linear-gradient(135deg, #0a2540 0%, #0e3d68 55%, #0b6623 100%)',
                'event_badge',
                null,
                4,
                1
            ]
        ];

        $insSlideStmt = $conn->prepare("INSERT INTO header_slides (slide_type, badge_text, badge_icon, badge_color, title, title_highlight, subtitle, quote_author, quote_author_role, btn_primary_text, btn_primary_link, btn_primary_icon, btn_secondary_text, btn_secondary_link, btn_secondary_icon, bg_gradient, card_type, image_path, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($seedSlides as $s) {
            $insSlideStmt->bind_param("ssssssssssssssssssii", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $s[10], $s[11], $s[12], $s[13], $s[14], $s[15], $s[16], $s[17], $s[18], $s[19]);
            $insSlideStmt->execute();
        }
        $insSlideStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed. Check config/db.php and ensure MySQL is running.");
} catch (Throwable $t) {
    // Suppress non-critical migration errors if table is not yet created
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session inactivity timeout configuration (in seconds)
// 5 minutes maximum inactivity timeout for all roles (admin, facilitator, and student)
if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', 300); // 5 minutes
}
if (!defined('ADMIN_FACILITATOR_IDLE_TIMEOUT')) {
    define('ADMIN_FACILITATOR_IDLE_TIMEOUT', 300); // 5 minutes
}
if (!defined('STUDENT_IDLE_TIMEOUT')) {
    define('STUDENT_IDLE_TIMEOUT', 300); // 5 minutes
}

if (isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['role'] ?? 'student';
    $idleTimeout = SESSION_IDLE_TIMEOUT; // 5 minutes for admin, facilitator, and student

    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $idleTimeout)) {
        // Destroy session data and cookies
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();

        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        if ($currentPage === 'keep_alive.php') {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'expired', 'message' => 'Session timed out due to inactivity']);
            exit;
        }

        if ($currentPage !== 'login.php' && $currentPage !== 'logout.php') {
            header("Location: login.php?timeout=1");
            exit;
        }
    } else {
        $_SESSION['last_activity'] = time();
    }
}
?>
