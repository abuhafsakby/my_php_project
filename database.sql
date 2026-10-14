CREATE DATABASE IF NOT EXISTS kibiya_lms_db;
USE kibiya_lms_db;

-- Users: admins, facilitators, and students
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'facilitator', 'student') NOT NULL DEFAULT 'student',
    phone VARCHAR(20) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password reset tokens (for forgot_password.php / reset_password.php)
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_token (token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    content TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    passing_score INT DEFAULT 70,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option CHAR(1) NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('pending', 'enrolled', 'completed', 'rejected') DEFAULT 'pending',
    quiz_score INT DEFAULT NULL,
    certificate_code VARCHAR(100) DEFAULT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_course (student_id, course_id)
);

-- Create the initial admin through create_admin.php so a valid password hash is used.

-- Default 20 Kibiya Hub Courses
INSERT INTO courses (id, title, category, description) VALUES
(1, 'AI Automation Engineering', 'Artificial Intelligence', 'Master robotic process automation (RPA), autonomous AI agents, workflow automation with Python and LangChain, and integrating intelligent LLM systems into modern business processes.'),
(2, 'AI Animation/Content Creation', 'Creative & Media', 'Learn to generate state-of-the-art AI video animations, digital avatars, automated voiceovers, storytelling visuals, and synthetic media using modern generative AI tools and workflows.'),
(3, 'App Development', 'Software Development', 'Build cross-platform and native mobile applications for Android and iOS using modern frameworks, responsive UI architecture, state management, and cloud backend integration.'),
(4, 'Cloud Computing', 'Cloud & Infrastructure', 'Understand cloud architecture, deployment, virtual servers, serverless computing, storage solutions, and infrastructure management across AWS, Azure, and Google Cloud Platform.'),
(5, 'Cybersecurity', 'Cybersecurity', 'Learn defensive security, threat analysis, ethical hacking fundamentals, vulnerability assessments, security operations, and modern data protection compliance standards.'),
(6, 'Data Analysis', 'Data Science', 'Turn raw data into actionable insights using advanced Excel, SQL database querying, Python (Pandas & NumPy), and interactive business intelligence dashboards in Power BI and Tableau.'),
(7, 'Dev Ops', 'DevOps & Systems', 'Master continuous integration & continuous deployment (CI/CD), Docker containers, Kubernetes orchestration, Linux server automation, Git version control, and infrastructure as code.'),
(8, 'Digital Marketing', 'Marketing & Business', 'Discover search engine optimization (SEO), social media marketing, content strategy, email marketing funnels, and data-driven ad campaign management across Meta, TikTok, and Google.'),
(9, 'Graphics Design', 'Creative & Design', 'Create professional visual identities, branding packages, logos, marketing posters, and digital artwork using industry-standard tools including Adobe Photoshop, Illustrator, and Canva.'),
(10, 'Machine Learning', 'Artificial Intelligence', 'Dive deep into supervised and unsupervised learning algorithms, regression, classification, clustering, neural networks, Scikit-Learn, and model performance evaluation.'),
(11, 'Python Development', 'Software Development', 'Comprehensive Python programming from foundational syntax to object-oriented programming, data structures, file processing, web scraping, and building robust backend web APIs.'),
(12, 'UI/UX Design', 'Creative & Design', 'Design intuitive, accessible, and user-centered digital products through user research, wireframing, high-fidelity interactive prototyping, design systems, and Figma component libraries.'),
(13, 'Web Development', 'Software Development', 'Learn full-stack web development including HTML5, CSS3, modern JavaScript, responsive layout techniques, PHP, MySQL database integration, authentication, and secure deployment.'),
(14, 'Livestock Farming', 'Agriculture & Agribusiness', 'Practical and modern animal husbandry techniques covering cattle, sheep, goats, poultry, feed formulation, disease prevention, farm hygiene, and profitable agribusiness management.'),
(15, 'Computer Hardware & GSM Repairs and Maintenance', 'Technical & Engineering', 'Hands-on hardware diagnostics, computer assembly, component testing, soldering techniques, mobile phone screen/battery replacement, GSM flashing, and troubleshooting circuit boards.'),
(16, 'Leather Craftsmanship and Design', 'Vocational & Crafts', 'Traditional and contemporary leather crafting including tanning principles, pattern drafting, precision cutting, hand-stitching, edge beveling, and crafting high-end shoes, bags, and belts.'),
(17, 'Satellite & CCTV Installation and Maintenance', 'Technical & Engineering', 'Installation, alignment, and signal optimization of satellite dishes (DStv, FTA), plus IP/analog CCTV camera configuration, DVR/NVR setup, remote surveillance, and structured cabling.'),
(18, 'Networking and System Security', 'Cybersecurity & Networks', 'Design, configure, and protect enterprise computer networks. Covers OSI model, TCP/IP, IP addressing and subnetting, routers, switches, VLANs, firewalls, and active threat defense.'),
(19, 'Catering and Hospitality', 'Hospitality & Culinary', 'Professional culinary arts, food safety and hygiene, event menu planning, international and traditional cuisine preparation, pastry baking, dining etiquette, and hospitality management.'),
(20, 'Refrigerators and Air-Condition', 'Technical & Engineering', 'Principles of thermodynamic cooling cycles, refrigerant charging, compressor repair, electrical troubleshooting, preventive maintenance, and servicing residential and commercial HVAC units.')
ON DUPLICATE KEY UPDATE title=VALUES(title), category=VALUES(category), description=VALUES(description);

-- Default Assessments for Courses
INSERT INTO quizzes (id, course_id, title, passing_score) VALUES
(1, 1, 'Final Mastery Assessment: AI Automation Engineering', 70),
(2, 2, 'Final Assessment: AI Animation & Content Creation', 70),
(3, 3, 'Final Assessment: App Development', 70),
(4, 4, 'Final Assessment: Cloud Computing', 70),
(5, 5, 'Final Assessment: Cybersecurity', 70),
(6, 6, 'Final Assessment: Data Analysis', 70),
(7, 7, 'Final Assessment: Dev Ops', 70),
(8, 8, 'Final Assessment: Digital Marketing', 70),
(9, 9, 'Final Assessment: Graphics Design', 70),
(10, 10, 'Final Assessment: Machine Learning', 70),
(11, 11, 'Final Assessment: Python Development', 70),
(12, 12, 'Final Assessment: UI/UX Design', 70),
(13, 13, 'Final Assessment: Web Development', 70),
(14, 14, 'Final Assessment: Livestock Farming', 70),
(15, 15, 'Final Assessment: Computer Hardware & GSM Repairs', 70),
(16, 16, 'Final Assessment: Leather Craftsmanship and Design', 70),
(17, 17, 'Final Assessment: Satellite & CCTV Installation', 70),
(18, 18, 'Final Assessment: Networking and System Security', 70),
(19, 19, 'Final Assessment: Catering and Hospitality', 70),
(20, 20, 'Final Assessment: Refrigerators and Air-Condition', 70)
ON DUPLICATE KEY UPDATE title=VALUES(title), passing_score=VALUES(passing_score);

-- Management Officers
CREATE TABLE IF NOT EXISTS management_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    role_title VARCHAR(150) NOT NULL,
    bio TEXT,
    badge_color VARCHAR(50) DEFAULT 'bg-success',
    image_path VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO management_officers (id, name, role_title, bio, badge_color, image_path, display_order) VALUES
(1, 'Musa Abdullahi Kibiya', 'Executive Director', 'Leading strategic vision, partnerships, and community empowerment initiatives across Kibiya LGA.', 'bg-success', NULL, 1),
(2, 'Fatima Ibrahim', 'Head of Training & Curriculum', 'Overseeing digital literacy programs, facilitator standards, module reviews, and certification validation.', 'bg-warning text-dark', NULL, 2),
(3, 'Ahmadu Bello', 'Technical Infrastructure Director', 'Managing hub connectivity, cloud infrastructure, hardware maintenance, and online LMS deployment.', 'bg-info text-dark', NULL, 3)
ON DUPLICATE KEY UPDATE name=VALUES(name), role_title=VALUES(role_title), bio=VALUES(bio);

-- Partners
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Partner',
    description TEXT,
    badge_color VARCHAR(50) DEFAULT 'bg-success',
    logo_path VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'fa-handshake',
    icon_bg VARCHAR(50) DEFAULT '#0b6623',
    website_url VARCHAR(255) DEFAULT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO partners (id, name, category, description, badge_color, logo_path, icon, icon_bg, website_url, display_order) VALUES
(1, 'Kano State Government', 'Government Partner', 'Supporting technology education and infrastructure development across Kano State local government areas.', 'bg-success', NULL, 'fa-landmark', '#0b6623', 'https://kanostate.gov.ng', 1),
(2, 'NITDA', 'Tech Development', 'The National Information Technology Development Agency supporting digital literacy and innovation hubs nationwide.', 'bg-warning text-dark', NULL, 'fa-globe', '#f4b41a', 'https://nitda.gov.ng', 2),
(3, 'Community Leaders', 'Local Support', 'Traditional and community leaders in Kibiya LGA championing youth empowerment and digital education initiatives.', 'bg-info text-white', NULL, 'fa-users', '#0284c7', '', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), description=VALUES(description);

-- Gallery
CREATE TABLE IF NOT EXISTS gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT 'Activities',
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'fa-images',
    gradient_bg VARCHAR(100) DEFAULT 'linear-gradient(135deg, #0b6623, #0d7a2b)',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO gallery (id, title, category, description, icon, gradient_bg, display_order) VALUES
(1, 'Web Development Training', 'Training', 'Students learning HTML, CSS, and JavaScript in our computer lab.', 'fa-laptop-code', 'linear-gradient(135deg, #0b6623, #0d7a2b)', 1),
(2, 'Robotics Workshop', 'Workshop', 'Hands-on robotics and IoT sessions for young innovators.', 'fa-robot', 'linear-gradient(135deg, #1a237e, #283593)', 2),
(3, 'Certificate Ceremony', 'Events', 'Celebrating graduates of our digital skills programme.', 'fa-graduation-cap', 'linear-gradient(135deg, #e65100, #f57c00)', 3),
(4, 'Facilitator Training', 'Training', 'Training of trainers workshop for our facilitators.', 'fa-chalkboard-user', 'linear-gradient(135deg, #4a148c, #6a1b9a)', 4),
(5, 'Community Outreach', 'Outreach', 'Engaging the local community in digital literacy awareness.', 'fa-people-group', 'linear-gradient(135deg, #01579b, #0288d1)', 5),
(6, 'Innovation Day', 'Events', 'Students presenting their final projects and digital solutions.', 'fa-lightbulb', 'linear-gradient(135deg, #bf360c, #e64a19)', 6),
(7, 'Computer Lab Session', 'Lab', 'Our fully equipped computer lab for practical learning.', 'fa-desktop', 'linear-gradient(135deg, #004d40, #00695c)', 7),
(8, 'Group Study Session', 'Learning', 'Collaborative learning and peer-to-peer knowledge sharing.', 'fa-users', 'linear-gradient(135deg, #311b92, #4527a0)', 8),
(9, 'Hackathon Winners', 'Competition', 'Team celebrating their win at the Kibiya Hub hackathon event.', 'fa-trophy', 'linear-gradient(135deg, #827717, #9e9d24)', 9)
ON DUPLICATE KEY UPDATE title=VALUES(title), category=VALUES(category), description=VALUES(description);

-- News, Opportunities, Job Openings & Announcements
CREATE TABLE IF NOT EXISTS news (
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
);

-- Dynamic Home Header Slider
CREATE TABLE IF NOT EXISTS header_slides (
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
);


