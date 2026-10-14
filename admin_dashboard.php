<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminId = (int)$_SESSION['user_id'];
$message = '';

// Helper to handle file uploads
function handleAdminFileUpload($fileInputName, &$errorMsg) {
    if (empty($_FILES[$fileInputName]['name'])) {
        return null;
    }
    if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Upload error (code: ' . $_FILES[$fileInputName]['error'] . ')';
        return false;
    }
    if ($_FILES[$fileInputName]['size'] > 25 * 1024 * 1024) {
        $errorMsg = 'Uploaded file exceeds the 25 MB size limit.';
        return false;
    }
    $allowed = ['pdf', 'docx', 'doc', 'zip', 'rar', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'mp4'];
    $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $errorMsg = 'Allowed file types: ' . implode(', ', $allowed);
        return false;
    }
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $errorMsg = 'Could not create uploads directory.';
        return false;
    }
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $safeName;
    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetPath)) {
        return 'uploads/' . $safeName;
    }
    $errorMsg = 'Could not save file to disk.';
    return false;
}

// Helper to handle image uploads for Officers, Partners, and Gallery
function handleAdminImageUpload($fileInputName, &$errorMsg) {
    if (empty($_FILES[$fileInputName]['name'])) {
        return null;
    }
    if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Upload error (code: ' . $_FILES[$fileInputName]['error'] . ')';
        return false;
    }
    if ($_FILES[$fileInputName]['size'] > 10 * 1024 * 1024) {
        $errorMsg = 'Image file exceeds the 10 MB limit.';
        return false;
    }
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $errorMsg = 'Allowed image types: JPG, PNG, WEBP, GIF, SVG.';
        return false;
    }
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $errorMsg = 'Could not create uploads directory.';
        return false;
    }
    $safeName = 'img_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $safeName;
    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetPath)) {
        return 'uploads/' . $safeName;
    }
    $errorMsg = 'Could not save image to disk.';
    return false;
}

// 1. ADD NEW COURSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $title = trim($_POST['title'] ?? '');
    $categorySelect = trim($_POST['category'] ?? '');
    $categoryCustom = trim($_POST['custom_category'] ?? '');
    $category = ($categorySelect === 'custom' && $categoryCustom !== '') ? $categoryCustom : ($categorySelect ?: 'General');
    $description = trim($_POST['description'] ?? '');
    $facilitator_id = filter_input(INPUT_POST, 'facilitator_id', FILTER_VALIDATE_INT) ?: $adminId;

    if ($title === '' || $description === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Course title and description are required.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (title, description, category, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $category, $facilitator_id);
        if ($stmt->execute()) {
            $newCourseId = (int)$stmt->insert_id;
            
            // Check if initial module/material is uploaded
            $moduleTitle = trim($_POST['initial_module_title'] ?? '');
            $moduleContent = trim($_POST['initial_module_content'] ?? '');
            $uploadErr = '';
            $uploadedFilePath = handleAdminFileUpload('initial_module_file', $uploadErr);

            if ($uploadedFilePath === false) {
                $message = "<div class='alert alert-warning'><i class='fa-solid fa-exclamation-circle me-2'></i>Course created, but learning material failed to upload: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
            } elseif ($moduleTitle !== '' || $uploadedFilePath !== null || $moduleContent !== '') {
                $modTitle = $moduleTitle !== '' ? $moduleTitle : 'Module 1: Course Overview & Resources';
                $modStmt = $conn->prepare("INSERT INTO modules (course_id, title, file_path, content) VALUES (?, ?, ?, ?)");
                $modStmt->bind_param("isss", $newCourseId, $modTitle, $uploadedFilePath, $moduleContent);
                $modStmt->execute();
                $modStmt->close();
                $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>New course <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> and initial learning material added successfully!</div>";
            } else {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>Course <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> created successfully!</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not create course. Please try again.</div>";
        }
        $stmt->close();
    }
}

// 2. ASSIGN FACILITATOR TO A COURSE (Single)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_facilitator'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $facilitator_id = filter_input(INPUT_POST, 'facilitator_id', FILTER_VALIDATE_INT);

    if (!$course_id || !$facilitator_id) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please select both a course and a facilitator.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE courses SET created_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $facilitator_id, $course_id);
        if ($stmt->execute()) {
            $infoStmt = $conn->prepare("SELECT c.title, u.full_name FROM courses c JOIN users u ON u.id = ? WHERE c.id = ?");
            $infoStmt->bind_param("ii", $facilitator_id, $course_id);
            $infoStmt->execute();
            $info = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();

            $cTitle = $info['title'] ?? 'Course';
            $fName = $info['full_name'] ?? 'Facilitator';
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Course <strong>" . htmlspecialchars($cTitle, ENT_QUOTES, 'UTF-8') . "</strong> has been successfully assigned to <strong>" . htmlspecialchars($fName, ENT_QUOTES, 'UTF-8') . "</strong>!</div>";
        } else {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to assign facilitator.</div>";
        }
        $stmt->close();
    }
}

// 3. BATCH ASSIGN COURSES TO A FACILITATOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_batch_courses'])) {
    $facilitator_id = filter_input(INPUT_POST, 'facilitator_id', FILTER_VALIDATE_INT);
    $selectedCourses = $_POST['course_ids'] ?? [];

    if (!$facilitator_id || empty($selectedCourses) || !is_array($selectedCourses)) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please choose a facilitator and select at least one course.</div>";
    } else {
        $fStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
        $fStmt->bind_param("i", $facilitator_id);
        $fStmt->execute();
        $facName = $fStmt->get_result()->fetch_assoc()['full_name'] ?? 'Facilitator';
        $fStmt->close();

        $count = 0;
        $upStmt = $conn->prepare("UPDATE courses SET created_by = ? WHERE id = ?");
        foreach ($selectedCourses as $cid) {
            $cidInt = (int)$cid;
            if ($cidInt > 0) {
                $upStmt->bind_param("ii", $facilitator_id, $cidInt);
                $upStmt->execute();
                $count++;
            }
        }
        $upStmt->close();
        $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Successfully assigned <strong>$count course(s)</strong> to <strong>" . htmlspecialchars($facName, ENT_QUOTES, 'UTF-8') . "</strong>!</div>";
    }
}

// 4. UPLOAD COURSE MATERIAL / MODULE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_module'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $module_title = trim($_POST['module_title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$course_id || $module_title === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please select a course and enter a module title.</div>";
    } else {
        $uploadErr = '';
        $filePath = handleAdminFileUpload('file', $uploadErr);
        if ($filePath === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO modules (course_id, title, file_path, content) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $course_id, $module_title, $filePath, $content);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>Learning material module uploaded successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Database error: could not save module.</div>";
            }
            $stmt->close();
        }
    }
}

// 5. EDIT COURSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $description = trim($_POST['description'] ?? '');
    $facilitator_id = filter_input(INPUT_POST, 'facilitator_id', FILTER_VALIDATE_INT) ?: $adminId;

    if (!$course_id || $title === '' || $description === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>All course fields are required.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE courses SET title = ?, category = ?, description = ?, created_by = ? WHERE id = ?");
        $stmt->bind_param("sssii", $title, $category, $description, $facilitator_id, $course_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>Course updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to update course.</div>";
        }
        $stmt->close();
    }
}

// 6. DELETE COURSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    if ($course_id) {
        $mFiles = $conn->prepare("SELECT file_path FROM modules WHERE course_id = ? AND file_path IS NOT NULL");
        $mFiles->bind_param("i", $course_id);
        $mFiles->execute();
        $res = $mFiles->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists(__DIR__ . '/' . $row['file_path'])) {
                @unlink(__DIR__ . '/' . $row['file_path']);
            }
        }
        $mFiles->close();

        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>Course and associated materials removed.</div>";
        }
        $stmt->close();
    }
}

// 7. DELETE MODULE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_module'])) {
    $module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
    if ($module_id) {
        $mFile = $conn->prepare("SELECT file_path FROM modules WHERE id = ?");
        $mFile->bind_param("i", $module_id);
        $mFile->execute();
        $row = $mFile->get_result()->fetch_assoc();
        $mFile->close();

        if ($row && !empty($row['file_path']) && file_exists(__DIR__ . '/' . $row['file_path'])) {
            @unlink(__DIR__ . '/' . $row['file_path']);
        }

        $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->bind_param("i", $module_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>Course module deleted successfully.</div>";
        }
        $stmt->close();
    }
}

// 8. ADD FACILITATOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_facilitator'])) {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plainPassword = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($plainPassword) < 8) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Enter a valid name, email, and password (at least 8 characters).</div>";
    } else {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'facilitator')");
        $stmt->bind_param("sss", $name, $email, $hashedPassword);
        try {
            $stmt->execute();
            $message = "<div class='alert alert-success'><i class='fa-solid fa-check-circle me-2'></i>Facilitator registered successfully!</div>";
        } catch (mysqli_sql_exception $e) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not register facilitator. Email may already exist.</div>";
        }
        $stmt->close();
    }
}

// 9. ADD MANAGEMENT OFFICER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_officer'])) {
    $name = trim($_POST['name'] ?? '');
    $role_title = trim($_POST['role_title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $badge_color = trim($_POST['badge_color'] ?? 'bg-success');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);

    if ($name === '' || $role_title === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Officer name and role title are required.</div>";
    } else {
        $uploadErr = '';
        $imagePath = handleAdminImageUpload('officer_image', $uploadErr);
        if ($imagePath === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Image upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO management_officers (name, role_title, bio, badge_color, image_path, phone, email, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $name, $role_title, $bio, $badge_color, $imagePath, $phone, $email, $display_order);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Management Officer <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to add management officer.</div>";
            }
            $stmt->close();
        }
    }
}

// 10. UPDATE MANAGEMENT OFFICER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_officer'])) {
    $officer_id = filter_input(INPUT_POST, 'officer_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $role_title = trim($_POST['role_title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $badge_color = trim($_POST['badge_color'] ?? 'bg-success');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);

    if (!$officer_id || $name === '' || $role_title === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Officer name and role title are required.</div>";
    } else {
        $uploadErr = '';
        $newImage = handleAdminImageUpload('officer_image', $uploadErr);
        if ($newImage === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Image upload error: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            if ($newImage !== null) {
                $oldStmt = $conn->prepare("SELECT image_path FROM management_officers WHERE id = ?");
                $oldStmt->bind_param("i", $officer_id);
                $oldStmt->execute();
                $oldRow = $oldStmt->get_result()->fetch_assoc();
                $oldStmt->close();
                if ($oldRow && !empty($oldRow['image_path']) && file_exists(__DIR__ . '/' . $oldRow['image_path'])) {
                    @unlink(__DIR__ . '/' . $oldRow['image_path']);
                }

                $stmt = $conn->prepare("UPDATE management_officers SET name = ?, role_title = ?, bio = ?, badge_color = ?, image_path = ?, phone = ?, email = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("sssssssii", $name, $role_title, $bio, $badge_color, $newImage, $phone, $email, $display_order, $officer_id);
            } else {
                $stmt = $conn->prepare("UPDATE management_officers SET name = ?, role_title = ?, bio = ?, badge_color = ?, phone = ?, email = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("ssssssii", $name, $role_title, $bio, $badge_color, $phone, $email, $display_order, $officer_id);
            }

            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Management Officer <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not update officer.</div>";
            }
            $stmt->close();
        }
    }
}

// 11. DELETE MANAGEMENT OFFICER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_officer'])) {
    $officer_id = filter_input(INPUT_POST, 'officer_id', FILTER_VALIDATE_INT);
    if ($officer_id) {
        $stmt = $conn->prepare("SELECT image_path FROM management_officers WHERE id = ?");
        $stmt->bind_param("i", $officer_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['image_path']) && file_exists(__DIR__ . '/' . $row['image_path'])) {
            @unlink(__DIR__ . '/' . $row['image_path']);
        }

        $delStmt = $conn->prepare("DELETE FROM management_officers WHERE id = ?");
        $delStmt->bind_param("i", $officer_id);
        if ($delStmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Management officer removed successfully.</div>";
        }
        $delStmt->close();
    }
}

// 12. ADD PARTNER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_partner'])) {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Partner');
    $description = trim($_POST['description'] ?? '');
    $badge_color = trim($_POST['badge_color'] ?? 'bg-success');
    $icon = trim($_POST['icon'] ?? 'fa-handshake');
    $icon_bg = trim($_POST['icon_bg'] ?? '#0b6623');
    $website_url = trim($_POST['website_url'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);

    if ($name === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Partner name is required.</div>";
    } else {
        $uploadErr = '';
        $logoPath = handleAdminImageUpload('partner_logo', $uploadErr);
        if ($logoPath === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Logo upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO partners (name, category, description, badge_color, logo_path, icon, icon_bg, website_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssi", $name, $category, $description, $badge_color, $logoPath, $icon, $icon_bg, $website_url, $display_order);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Partner <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to add partner.</div>";
            }
            $stmt->close();
        }
    }
}

// 13. UPDATE PARTNER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_partner'])) {
    $partner_id = filter_input(INPUT_POST, 'partner_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Partner');
    $description = trim($_POST['description'] ?? '');
    $badge_color = trim($_POST['badge_color'] ?? 'bg-success');
    $icon = trim($_POST['icon'] ?? 'fa-handshake');
    $icon_bg = trim($_POST['icon_bg'] ?? '#0b6623');
    $website_url = trim($_POST['website_url'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);

    if (!$partner_id || $name === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Partner name is required.</div>";
    } else {
        $uploadErr = '';
        $newLogo = handleAdminImageUpload('partner_logo', $uploadErr);
        if ($newLogo === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Logo upload error: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            if ($newLogo !== null) {
                $oldStmt = $conn->prepare("SELECT logo_path FROM partners WHERE id = ?");
                $oldStmt->bind_param("i", $partner_id);
                $oldStmt->execute();
                $oldRow = $oldStmt->get_result()->fetch_assoc();
                $oldStmt->close();
                if ($oldRow && !empty($oldRow['logo_path']) && file_exists(__DIR__ . '/' . $oldRow['logo_path'])) {
                    @unlink(__DIR__ . '/' . $oldRow['logo_path']);
                }

                $stmt = $conn->prepare("UPDATE partners SET name = ?, category = ?, description = ?, badge_color = ?, logo_path = ?, icon = ?, icon_bg = ?, website_url = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("ssssssssii", $name, $category, $description, $badge_color, $newLogo, $icon, $icon_bg, $website_url, $display_order, $partner_id);
            } else {
                $stmt = $conn->prepare("UPDATE partners SET name = ?, category = ?, description = ?, badge_color = ?, icon = ?, icon_bg = ?, website_url = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("sssssssii", $name, $category, $description, $badge_color, $icon, $icon_bg, $website_url, $display_order, $partner_id);
            }

            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Partner <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not update partner.</div>";
            }
            $stmt->close();
        }
    }
}

// 14. DELETE PARTNER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    $partner_id = filter_input(INPUT_POST, 'partner_id', FILTER_VALIDATE_INT);
    if ($partner_id) {
        $stmt = $conn->prepare("SELECT logo_path FROM partners WHERE id = ?");
        $stmt->bind_param("i", $partner_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['logo_path']) && file_exists(__DIR__ . '/' . $row['logo_path'])) {
            @unlink(__DIR__ . '/' . $row['logo_path']);
        }

        $delStmt = $conn->prepare("DELETE FROM partners WHERE id = ?");
        $delStmt->bind_param("i", $partner_id);
        if ($delStmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Partner removed successfully.</div>";
        }
        $delStmt->close();
    }
}

// 15. ADD GALLERY ITEM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gallery'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Activities');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-camera');
    $gradient_bg = trim($_POST['gradient_bg'] ?? 'linear-gradient(135deg, #0b6623, #0d7a2b)');
    $display_order = (int)($_POST['display_order'] ?? 0);

    if ($title === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Gallery item title is required.</div>";
    } else {
        $uploadErr = '';
        $imagePath = handleAdminImageUpload('gallery_image', $uploadErr);
        if ($imagePath === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Gallery image upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO gallery (title, category, description, image_path, icon, gradient_bg, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $title, $category, $description, $imagePath, $icon, $gradient_bg, $display_order);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Gallery item <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to add gallery item.</div>";
            }
            $stmt->close();
        }
    }
}

// 16. UPDATE GALLERY ITEM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gallery'])) {
    $gallery_id = filter_input(INPUT_POST, 'gallery_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Activities');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-camera');
    $gradient_bg = trim($_POST['gradient_bg'] ?? 'linear-gradient(135deg, #0b6623, #0d7a2b)');
    $display_order = (int)($_POST['display_order'] ?? 0);

    if (!$gallery_id || $title === '') {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Gallery title is required.</div>";
    } else {
        $uploadErr = '';
        $newImage = handleAdminImageUpload('gallery_image', $uploadErr);
        if ($newImage === false) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Gallery image upload error: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "</div>";
        } else {
            if ($newImage !== null) {
                $oldStmt = $conn->prepare("SELECT image_path FROM gallery WHERE id = ?");
                $oldStmt->bind_param("i", $gallery_id);
                $oldStmt->execute();
                $oldRow = $oldStmt->get_result()->fetch_assoc();
                $oldStmt->close();
                if ($oldRow && !empty($oldRow['image_path']) && file_exists(__DIR__ . '/' . $oldRow['image_path'])) {
                    @unlink(__DIR__ . '/' . $oldRow['image_path']);
                }

                $stmt = $conn->prepare("UPDATE gallery SET title = ?, category = ?, description = ?, image_path = ?, icon = ?, gradient_bg = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("ssssssii", $title, $category, $description, $newImage, $icon, $gradient_bg, $display_order, $gallery_id);
            } else {
                $stmt = $conn->prepare("UPDATE gallery SET title = ?, category = ?, description = ?, icon = ?, gradient_bg = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $title, $category, $description, $icon, $gradient_bg, $display_order, $gallery_id);
            }

            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Gallery item <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not update gallery item.</div>";
            }
            $stmt->close();
        }
    }
}

// 17. DELETE GALLERY ITEM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gallery'])) {
    $gallery_id = filter_input(INPUT_POST, 'gallery_id', FILTER_VALIDATE_INT);
    if ($gallery_id) {
        $stmt = $conn->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['image_path']) && file_exists(__DIR__ . '/' . $row['image_path'])) {
            @unlink(__DIR__ . '/' . $row['image_path']);
        }

        $delStmt = $conn->prepare("DELETE FROM gallery WHERE id = ?");
        $delStmt->bind_param("i", $gallery_id);
        if ($delStmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Gallery item removed successfully.</div>";
        }
        $delStmt->close();
    }
}

// 17b. ADD NEWS / OPPORTUNITY POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_news'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Opportunities');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author_name = trim($_POST['author_name'] ?? 'Kibiya Hub Editorial');
    $external_link = trim($_POST['external_link'] ?? '');
    $link_label = trim($_POST['link_label'] ?? 'Learn More / Apply');
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $status = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $badge_color = trim($_POST['badge_color'] ?? 'bg-success');
    $icon = trim($_POST['icon'] ?? 'fa-newspaper');

    if ($title === '' || $content === '') {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Title and full content are required to publish an update.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $uploadErr = '';
        $imagePath = handleAdminImageUpload('news_image', $uploadErr);
        if ($imagePath === false) {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Image upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO news (title, category, summary, content, image_path, badge_color, icon, author_name, external_link, link_label, deadline_date, status, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssi", $title, $category, $summary, $content, $imagePath, $badge_color, $icon, $author_name, $external_link, $link_label, $deadline_date, $status, $is_featured);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-circle-check me-2'></i>News &amp; Opportunity post <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> published successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Database error: could not save news post.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
            $stmt->close();
        }
    }
}

// 17c. UPDATE NEWS / OPPORTUNITY POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_news'])) {
    $news_id = filter_input(INPUT_POST, 'news_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Opportunities');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author_name = trim($_POST['author_name'] ?? 'Kibiya Hub Editorial');
    $external_link = trim($_POST['external_link'] ?? '');
    $link_label = trim($_POST['link_label'] ?? 'Learn More / Apply');
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $status = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $badge_color = trim($_POST['badge_color'] ?? 'bg-success');
    $icon = trim($_POST['icon'] ?? 'fa-newspaper');

    if (!$news_id || $title === '' || $content === '') {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Title and full content are required.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $uploadErr = '';
        $newImage = handleAdminImageUpload('news_image', $uploadErr);
        if ($newImage === false) {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Image upload error: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            if ($newImage !== null) {
                $oldStmt = $conn->prepare("SELECT image_path FROM news WHERE id = ?");
                $oldStmt->bind_param("i", $news_id);
                $oldStmt->execute();
                $oldRow = $oldStmt->get_result()->fetch_assoc();
                $oldStmt->close();
                if ($oldRow && !empty($oldRow['image_path']) && file_exists(__DIR__ . '/' . $oldRow['image_path'])) {
                    @unlink(__DIR__ . '/' . $oldRow['image_path']);
                }

                $stmt = $conn->prepare("UPDATE news SET title = ?, category = ?, summary = ?, content = ?, image_path = ?, badge_color = ?, icon = ?, author_name = ?, external_link = ?, link_label = ?, deadline_date = ?, status = ?, is_featured = ? WHERE id = ?");
                $stmt->bind_param("ssssssssssssii", $title, $category, $summary, $content, $newImage, $badge_color, $icon, $author_name, $external_link, $link_label, $deadline_date, $status, $is_featured, $news_id);
            } else {
                $stmt = $conn->prepare("UPDATE news SET title = ?, category = ?, summary = ?, content = ?, badge_color = ?, icon = ?, author_name = ?, external_link = ?, link_label = ?, deadline_date = ?, status = ?, is_featured = ? WHERE id = ?");
                $stmt->bind_param("sssssssssssii", $title, $category, $summary, $content, $badge_color, $icon, $author_name, $external_link, $link_label, $deadline_date, $status, $is_featured, $news_id);
            }

            if ($stmt->execute()) {
                $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-circle-check me-2'></i>News post <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not update news post.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
            $stmt->close();
        }
    }
}

// 17d. DELETE NEWS POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_news'])) {
    $news_id = filter_input(INPUT_POST, 'news_id', FILTER_VALIDATE_INT);
    if ($news_id) {
        $stmt = $conn->prepare("SELECT image_path FROM news WHERE id = ?");
        $stmt->bind_param("i", $news_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['image_path']) && file_exists(__DIR__ . '/' . $row['image_path'])) {
            @unlink(__DIR__ . '/' . $row['image_path']);
        }

        $delStmt = $conn->prepare("DELETE FROM news WHERE id = ?");
        $delStmt->bind_param("i", $news_id);
        if ($delStmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-circle-check me-2'></i>News post removed from system.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $delStmt->close();
    }
}

// 17e. TOGGLE NEWS PUBLISH STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_news_status'])) {
    $news_id = filter_input(INPUT_POST, 'news_id', FILTER_VALIDATE_INT);
    if ($news_id) {
        $conn->query("UPDATE news SET status = IF(status = 'published', 'draft', 'published') WHERE id = $news_id");
        $message = "<div class='alert alert-info alert-dismissible fade show shadow-sm'><i class='fa-solid fa-arrows-rotate me-2'></i>Publication status updated.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// 18. ASSIGN EXISTING HUB MEMBER AS ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_existing_admin'])) {
    $target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$target_user_id) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please select a valid hub member to promote.</div>";
    } else {
        $uStmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
        $uStmt->bind_param("i", $target_user_id);
        $uStmt->execute();
        $targetUser = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();

        if (!$targetUser) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Selected user not found.</div>";
        } elseif ($targetUser['role'] === 'admin') {
            $message = "<div class='alert alert-info'><i class='fa-solid fa-circle-info me-2'></i>This user is already an Administrator.</div>";
        } else {
            $upStmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $upStmt->bind_param("i", $target_user_id);
            if ($upStmt->execute()) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i><strong>" . htmlspecialchars($targetUser['full_name'], ENT_QUOTES, 'UTF-8') . "</strong> (" . htmlspecialchars($targetUser['email'], ENT_QUOTES, 'UTF-8') . ") has been successfully assigned as a <strong>System Administrator</strong>!</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to update user role.</div>";
            }
            $upStmt->close();
        }
    }
}

// 19. CREATE NEW HUB ADMINISTRATOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_new_admin'])) {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $plainPassword = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($plainPassword) < 8) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Please provide a valid name, email address, and a password with at least 8 characters.</div>";
    } else {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);
        try {
            $stmt->execute();
            $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>New System Administrator <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> created successfully!</div>";
        } catch (mysqli_sql_exception $e) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not add administrator. The email address <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong> is already registered.</div>";
        }
        $stmt->close();
    }
}

// 20. CHANGE ADMIN ROLE / DEMOTE ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_role'])) {
    $admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
    $new_role = trim($_POST['new_role'] ?? '');

    if (!in_array($new_role, ['facilitator', 'student'], true)) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Invalid role selection.</div>";
    } elseif ($admin_id === (int)($_SESSION['user_id'] ?? 0)) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Security Action Denied: You cannot revoke your own administrator account while logged in.</div>";
    } else {
        $countAdmins = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] ?? 0);
        if ($countAdmins <= 1) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Action Denied: You cannot demote the last remaining System Administrator.</div>";
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role = 'admin'");
            $stmt->bind_param("si", $new_role, $admin_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "<div class='alert alert-warning'><i class='fa-solid fa-circle-check me-2'></i>Administrator permissions revoked. User role changed to <strong>" . ucfirst($new_role) . "</strong>.</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to update user role.</div>";
            }
            $stmt->close();
        }
    }
}

// 21. DELETE ADMIN ACCOUNT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
    if ($admin_id === (int)($_SESSION['user_id'] ?? 0)) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Security Action Denied: You cannot delete your own account while logged in.</div>";
    } else {
        $countAdmins = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] ?? 0);
        if ($countAdmins <= 1) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Action Denied: You cannot delete the only remaining System Administrator.</div>";
        } else {
            $delStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
            $delStmt->bind_param("i", $admin_id);
            if ($delStmt->execute() && $delStmt->affected_rows > 0) {
                $message = "<div class='alert alert-success'><i class='fa-solid fa-circle-check me-2'></i>Administrator account removed from the hub.</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to delete administrator account.</div>";
            }
            $delStmt->close();
        }
    }
}

// 22. ENROLLMENT APPROVAL ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_enrollment'])) {
    $enrollment_id = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    if ($enrollment_id) {
        $stmt = $conn->prepare("UPDATE enrollments SET status = 'enrolled' WHERE id = ?");
        $stmt->bind_param("i", $enrollment_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-circle-check me-2'></i>Course access granted to student! The student can now start learning modules and take the assessment.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to approve enrollment access.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_enrollment'])) {
    $enrollment_id = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    if ($enrollment_id) {
        $stmt = $conn->prepare("UPDATE enrollments SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $enrollment_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-warning alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-ban me-2'></i>Enrollment access declined / revoked for this student.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to decline enrollment.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_enrollment'])) {
    $enrollment_id = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    if ($enrollment_id) {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmt->bind_param("i", $enrollment_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-info alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-trash me-2'></i>Enrollment record removed.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to delete enrollment.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_all_pending'])) {
    if ($conn->query("UPDATE enrollments SET status = 'enrolled' WHERE status = 'pending'")) {
        $count = $conn->affected_rows;
        $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-circle-check me-2'></i>All <strong>$count</strong> pending student enrollment(s) approved and granted course access!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// 23. ADD NEW HEADER SLIDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slide'])) {
    $slide_type = trim($_POST['slide_type'] ?? 'custom');
    $badge_text = trim($_POST['badge_text'] ?? 'Kibiya Tech Hub');
    $badge_icon = trim($_POST['badge_icon'] ?? 'fa-rocket');
    $badge_color = trim($_POST['badge_color'] ?? 'bg-warning text-dark');
    $title = trim($_POST['title'] ?? '');
    $title_highlight = trim($_POST['title_highlight'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $quote_author = trim($_POST['quote_author'] ?? '');
    $quote_author_role = trim($_POST['quote_author_role'] ?? '');
    $btn_primary_text = trim($_POST['btn_primary_text'] ?? 'Browse Courses');
    $btn_primary_link = trim($_POST['btn_primary_link'] ?? 'courses.php');
    $btn_primary_icon = trim($_POST['btn_primary_icon'] ?? 'fa-book-open');
    $btn_secondary_text = trim($_POST['btn_secondary_text'] ?? '');
    $btn_secondary_link = trim($_POST['btn_secondary_link'] ?? '');
    $btn_secondary_icon = trim($_POST['btn_secondary_icon'] ?? 'fa-user-plus');
    $bg_gradient = trim($_POST['bg_gradient'] ?? 'linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)');
    $card_type = trim($_POST['card_type'] ?? 'graphic');
    $display_order = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT) ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '' || $subtitle === '') {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Slide Title and Subtitle / Message are required.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $uploadErr = '';
        $imagePath = handleAdminImageUpload('slide_image', $uploadErr);
        if ($imagePath === false) {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Image upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO header_slides (slide_type, badge_text, badge_icon, badge_color, title, title_highlight, subtitle, quote_author, quote_author_role, btn_primary_text, btn_primary_link, btn_primary_icon, btn_secondary_text, btn_secondary_link, btn_secondary_icon, bg_gradient, card_type, image_path, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssssssssii", $slide_type, $badge_text, $badge_icon, $badge_color, $title, $title_highlight, $subtitle, $quote_author, $quote_author_role, $btn_primary_text, $btn_primary_link, $btn_primary_icon, $btn_secondary_text, $btn_secondary_link, $btn_secondary_icon, $bg_gradient, $card_type, $imagePath, $display_order, $is_active);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-circle-check me-2'></i>New header slide <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> added to homepage slider successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not add slide: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
            $stmt->close();
        }
    }
}

// 24. UPDATE HEADER SLIDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_slide'])) {
    $slide_id = filter_input(INPUT_POST, 'slide_id', FILTER_VALIDATE_INT);
    $slide_type = trim($_POST['slide_type'] ?? 'custom');
    $badge_text = trim($_POST['badge_text'] ?? 'Kibiya Tech Hub');
    $badge_icon = trim($_POST['badge_icon'] ?? 'fa-rocket');
    $badge_color = trim($_POST['badge_color'] ?? 'bg-warning text-dark');
    $title = trim($_POST['title'] ?? '');
    $title_highlight = trim($_POST['title_highlight'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $quote_author = trim($_POST['quote_author'] ?? '');
    $quote_author_role = trim($_POST['quote_author_role'] ?? '');
    $btn_primary_text = trim($_POST['btn_primary_text'] ?? '');
    $btn_primary_link = trim($_POST['btn_primary_link'] ?? '');
    $btn_primary_icon = trim($_POST['btn_primary_icon'] ?? 'fa-arrow-right');
    $btn_secondary_text = trim($_POST['btn_secondary_text'] ?? '');
    $btn_secondary_link = trim($_POST['btn_secondary_link'] ?? '');
    $btn_secondary_icon = trim($_POST['btn_secondary_icon'] ?? 'fa-user-plus');
    $bg_gradient = trim($_POST['bg_gradient'] ?? 'linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)');
    $card_type = trim($_POST['card_type'] ?? 'graphic');
    $display_order = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT) ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $remove_image = isset($_POST['remove_image']);

    if (!$slide_id || $title === '' || $subtitle === '') {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Slide ID, Title, and Subtitle are required.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $uploadErr = '';
        $newImage = handleAdminImageUpload('slide_image', $uploadErr);

        if ($newImage === false) {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Image upload failed: " . htmlspecialchars($uploadErr, ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            // Fetch old image path
            $oldImgStmt = $conn->prepare("SELECT image_path FROM header_slides WHERE id = ?");
            $oldImgStmt->bind_param("i", $slide_id);
            $oldImgStmt->execute();
            $oldRow = $oldImgStmt->get_result()->fetch_assoc();
            $oldImgStmt->close();

            if ($newImage !== null) {
                if ($oldRow && !empty($oldRow['image_path']) && file_exists(__DIR__ . '/' . $oldRow['image_path'])) {
                    @unlink(__DIR__ . '/' . $oldRow['image_path']);
                }
                $stmt = $conn->prepare("UPDATE header_slides SET slide_type = ?, badge_text = ?, badge_icon = ?, badge_color = ?, title = ?, title_highlight = ?, subtitle = ?, quote_author = ?, quote_author_role = ?, btn_primary_text = ?, btn_primary_link = ?, btn_primary_icon = ?, btn_secondary_text = ?, btn_secondary_link = ?, btn_secondary_icon = ?, bg_gradient = ?, card_type = ?, image_path = ?, display_order = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("ssssssssssssssssssiii", $slide_type, $badge_text, $badge_icon, $badge_color, $title, $title_highlight, $subtitle, $quote_author, $quote_author_role, $btn_primary_text, $btn_primary_link, $btn_primary_icon, $btn_secondary_text, $btn_secondary_link, $btn_secondary_icon, $bg_gradient, $card_type, $newImage, $display_order, $is_active, $slide_id);
            } elseif ($remove_image) {
                if ($oldRow && !empty($oldRow['image_path']) && file_exists(__DIR__ . '/' . $oldRow['image_path'])) {
                    @unlink(__DIR__ . '/' . $oldRow['image_path']);
                }
                $nullImg = null;
                $stmt = $conn->prepare("UPDATE header_slides SET slide_type = ?, badge_text = ?, badge_icon = ?, badge_color = ?, title = ?, title_highlight = ?, subtitle = ?, quote_author = ?, quote_author_role = ?, btn_primary_text = ?, btn_primary_link = ?, btn_primary_icon = ?, btn_secondary_text = ?, btn_secondary_link = ?, btn_secondary_icon = ?, bg_gradient = ?, card_type = ?, image_path = ?, display_order = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("ssssssssssssssssssiii", $slide_type, $badge_text, $badge_icon, $badge_color, $title, $title_highlight, $subtitle, $quote_author, $quote_author_role, $btn_primary_text, $btn_primary_link, $btn_primary_icon, $btn_secondary_text, $btn_secondary_link, $btn_secondary_icon, $bg_gradient, $card_type, $nullImg, $display_order, $is_active, $slide_id);
            } else {
                $stmt = $conn->prepare("UPDATE header_slides SET slide_type = ?, badge_text = ?, badge_icon = ?, badge_color = ?, title = ?, title_highlight = ?, subtitle = ?, quote_author = ?, quote_author_role = ?, btn_primary_text = ?, btn_primary_link = ?, btn_primary_icon = ?, btn_secondary_text = ?, btn_secondary_link = ?, btn_secondary_icon = ?, bg_gradient = ?, card_type = ?, display_order = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("sssssssssssssssssiii", $slide_type, $badge_text, $badge_icon, $badge_color, $title, $title_highlight, $subtitle, $quote_author, $quote_author_role, $btn_primary_text, $btn_primary_link, $btn_primary_icon, $btn_secondary_text, $btn_secondary_link, $btn_secondary_icon, $bg_gradient, $card_type, $display_order, $is_active, $slide_id);
            }

            if ($stmt->execute()) {
                $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-circle-check me-2'></i>Header slide <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-triangle-exclamation me-2'></i>Could not update slide: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
            $stmt->close();
        }
    }
}

// 25. DELETE HEADER SLIDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_slide'])) {
    $slide_id = filter_input(INPUT_POST, 'slide_id', FILTER_VALIDATE_INT);
    if ($slide_id) {
        $stmt = $conn->prepare("SELECT image_path, title FROM header_slides WHERE id = ?");
        $stmt->bind_param("i", $slide_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['image_path']) && file_exists(__DIR__ . '/' . $row['image_path'])) {
            @unlink(__DIR__ . '/' . $row['image_path']);
        }

        $delStmt = $conn->prepare("DELETE FROM header_slides WHERE id = ?");
        $delStmt->bind_param("i", $slide_id);
        if ($delStmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-circle-check me-2'></i>Header slide <strong>" . htmlspecialchars($row['title'] ?? 'Slide', ENT_QUOTES, 'UTF-8') . "</strong> removed from homepage slider.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $delStmt->close();
    }
}

// 26. TOGGLE HEADER SLIDE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_slide_status'])) {
    $slide_id = filter_input(INPUT_POST, 'slide_id', FILTER_VALIDATE_INT);
    if ($slide_id) {
        $conn->query("UPDATE header_slides SET is_active = IF(is_active = 1, 0, 1) WHERE id = $slide_id");
        $message = "<div class='alert alert-info alert-dismissible fade show shadow-sm' role='alert'><i class='fa-solid fa-arrows-rotate me-2'></i>Slide visibility status updated.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// Fetch Stats
$total_courses = $conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
$total_students = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")->fetch_assoc()['c'];
$total_facilitators = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'facilitator'")->fetch_assoc()['c'];
$total_modules = $conn->query("SELECT COUNT(*) AS c FROM modules")->fetch_assoc()['c'];
$total_enrollments = (int)($conn->query("SELECT COUNT(*) AS c FROM enrollments")->fetch_assoc()['c'] ?? 0);
$pending_enrollments = (int)($conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
$active_enrollments = (int)($conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE status = 'enrolled'")->fetch_assoc()['c'] ?? 0);
$completed_enrollments = (int)($conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE status = 'completed'")->fetch_assoc()['c'] ?? 0);

// Fetch Courses list
$coursesQuery = $conn->query("SELECT c.*, u.full_name AS facilitator_name, u.role AS facilitator_role,
    (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS module_count,
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
    FROM courses c
    LEFT JOIN users u ON c.created_by = u.id
    ORDER BY c.id ASC");

$allCourses = [];
while ($row = $coursesQuery->fetch_assoc()) {
    $allCourses[] = $row;
}

// Fetch Facilitators for dropdowns and listing
$facQuery = $conn->query("SELECT u.id, u.full_name, u.email, u.role, u.created_at,
    (SELECT COUNT(*) FROM courses c WHERE c.created_by = u.id) AS assigned_courses,
    (SELECT GROUP_CONCAT(c.title SEPARATOR '||') FROM courses c WHERE c.created_by = u.id) AS assigned_course_titles
    FROM users u WHERE u.role IN ('admin', 'facilitator') ORDER BY u.full_name ASC");
$facilitatorsList = [];
while ($f = $facQuery->fetch_assoc()) {
    $facilitatorsList[] = $f;
}

// Fetch All Modules for Materials tab
$modulesQuery = $conn->query("SELECT m.*, c.title AS course_title
    FROM modules m
    JOIN courses c ON m.course_id = c.id
    ORDER BY m.id DESC LIMIT 50");

// Fetch Recent Enrollments (Pending requests prioritized at top)
$enrollQuery = $conn->query("SELECT e.*, u.full_name AS student_name, u.email AS student_email, u.phone AS student_phone, c.title AS course_title
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY (e.status = 'pending') DESC, e.enrolled_at DESC LIMIT 100");

// Fetch Management Officers
$officersList = [];
$offRes = $conn->query("SELECT * FROM management_officers ORDER BY display_order ASC, id ASC");
if ($offRes) {
    while ($r = $offRes->fetch_assoc()) {
        $officersList[] = $r;
    }
}
$total_officers = count($officersList);

// Fetch Partners
$partnersList = [];
$partRes = $conn->query("SELECT * FROM partners ORDER BY display_order ASC, id ASC");
if ($partRes) {
    while ($r = $partRes->fetch_assoc()) {
        $partnersList[] = $r;
    }
}
$total_partners = count($partnersList);

// Fetch Gallery
$galleryList = [];
$galRes = $conn->query("SELECT * FROM gallery ORDER BY display_order ASC, id ASC");
if ($galRes) {
    while ($r = $galRes->fetch_assoc()) {
        $galleryList[] = $r;
    }
}
$total_gallery = count($galleryList);

// Fetch News & Opportunities
$newsAdminList = [];
$newsAdminRes = $conn->query("SELECT * FROM news ORDER BY id DESC");
if ($newsAdminRes) {
    while ($r = $newsAdminRes->fetch_assoc()) {
        $newsAdminList[] = $r;
    }
}
$total_news = count($newsAdminList);
$published_news = count(array_filter($newsAdminList, function($n) { return ($n['status'] ?? '') === 'published'; }));
$draft_news = $total_news - $published_news;

// Fetch Header Slides
$slidesAdminList = [];
$slRes = $conn->query("SELECT * FROM header_slides ORDER BY display_order ASC, id ASC");
if ($slRes) {
    while ($s = $slRes->fetch_assoc()) {
        $slidesAdminList[] = $s;
    }
}
$total_slides = count($slidesAdminList);
$active_slides_count = count(array_filter($slidesAdminList, function($s) { return ((int)($s['is_active'] ?? 0)) === 1; }));

// Fetch System Administrators
$total_admins = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] ?? 0);
$adminsList = [];
$admRes = $conn->query("SELECT id, full_name, email, phone, role, created_at,
    (SELECT COUNT(*) FROM courses c WHERE c.created_by = users.id) AS assigned_courses
    FROM users WHERE role = 'admin' ORDER BY id ASC");
if ($admRes) {
    while ($a = $admRes->fetch_assoc()) {
        $adminsList[] = $a;
    }
}

// Fetch Potential Admins from Hub (candidates among facilitators and students)
$potentialAdmins = [];
$potRes = $conn->query("SELECT id, full_name, email, role, phone FROM users WHERE role != 'admin' ORDER BY role ASC, full_name ASC");
if ($potRes) {
    while ($pa = $potRes->fetch_assoc()) {
        $potentialAdmins[] = $pa;
    }
}

// Standard categories list
$standardCategories = [
    'Artificial Intelligence',
    'Creative & Media',
    'Creative & Design',
    'Software Development',
    'Cloud & Infrastructure',
    'Cybersecurity',
    'Cybersecurity & Networks',
    'Data Science',
    'DevOps & Systems',
    'Marketing & Business',
    'Agriculture & Agribusiness',
    'Technical & Engineering',
    'Vocational & Crafts',
    'Hospitality & Culinary'
];
?>
<?php include 'includes/header.php'; ?>

<?php
  // Fetch current profile image from session
  $adminProfileImg = $_SESSION['profile_image'] ?? '';
  $adminProfileImgExists = ($adminProfileImg !== '' && file_exists(__DIR__ . '/' . $adminProfileImg));
  // Flash message from upload_profile_picture.php
  if (!empty($_SESSION['profile_msg'])) {
      $message .= $_SESSION['profile_msg'];
      unset($_SESSION['profile_msg']);
  }
?>

<style>
  .profile-avatar-lg {
    width: 100px; height: 100px; object-fit: cover; border-radius: 50%;
    border: 4px solid #0b6623; box-shadow: 0 6px 20px rgba(11,102,35,0.25);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .profile-avatar-lg:hover { transform: scale(1.05); box-shadow: 0 8px 28px rgba(11,102,35,0.35); }
  .profile-initials {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, #0b6623, #15803d);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 2.2rem; font-weight: 700;
    border: 4px solid #fff; box-shadow: 0 6px 20px rgba(11,102,35,0.25);
  }
</style>

<div class="container my-5">
  <!-- Top Header -->
  <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <!-- Profile Picture + Welcome -->
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="text-center">
        <?php if ($adminProfileImgExists): ?>
          <img src="<?php echo htmlspecialchars($adminProfileImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="profile-avatar-lg">
        <?php else: ?>
          <?php
            $initials = '';
            $nameParts = explode(' ', $_SESSION['full_name'] ?? 'A');
            $initials .= strtoupper(substr($nameParts[0], 0, 1));
            if (count($nameParts) > 1) $initials .= strtoupper(substr(end($nameParts), 0, 1));
          ?>
          <div class="profile-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-user-shield me-2 text-hub"></i>Admin Control Panel</h2>
        <p class="text-muted mb-1">Manage courses, assign facilitators, upload curriculum materials, and control hub public content (Officers, Partners &amp; Gallery).</p>
        <!-- Compact profile picture upload -->
        <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2 mt-1">
          <label for="adminProfilePic" class="btn btn-outline-success btn-sm fw-semibold mb-0" style="cursor:pointer;">
            <i class="fa-solid fa-camera me-1"></i><?php echo $adminProfileImgExists ? 'Change Photo' : 'Upload Photo'; ?>
          </label>
          <input type="file" name="profile_picture" id="adminProfilePic" accept="image/*" class="d-none" onchange="this.form.submit();">
        </form>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 w-100 w-lg-auto mt-2 mt-lg-0">
      <button class="btn btn-hub fw-bold shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#addCourseModal">
        <i class="fa-solid fa-plus-circle me-1"></i>Add Course
      </button>
      <button class="btn btn-info text-white fw-bold shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#assignCourseGeneralModal">
        <i class="fa-solid fa-user-check me-1"></i>Assign Facilitator
      </button>
      <button class="btn btn-warning fw-bold shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
        <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Material
      </button>

      <!-- CMS Quick Actions Dropdown -->
      <div class="dropdown flex-grow-1 flex-md-grow-0">
        <button class="btn btn-outline-success dropdown-toggle fw-bold shadow-sm w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fa-solid fa-sliders me-1"></i>Add Content
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px;">
          <li>
            <a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#addOfficerModal">
              <i class="fa-solid fa-user-plus text-success me-2"></i>Add Management Officer
            </a>
          </li>
          <li>
            <a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
              <i class="fa-solid fa-handshake text-primary me-2"></i>Add Partner / Sponsor
            </a>
          </li>
          <li>
            <a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#addGalleryModal">
              <i class="fa-solid fa-image text-warning me-2"></i>Add Gallery Item
            </a>
          </li>
          <li>
            <a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#addNewsModal">
              <i class="fa-solid fa-bullhorn text-danger me-2"></i>Add News / Opportunity
            </a>
          </li>
          <li>
            <a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#addSlideModal">
              <i class="fa-solid fa-sliders text-warning me-2"></i>Add Header Slide (10s Hero)
            </a>
          </li>
        </ul>
      </div>

      <a href="logout.php" class="btn btn-outline-danger fw-bold shadow-sm d-inline-flex align-items-center justify-content-center flex-grow-1 flex-md-grow-0" title="Log out of admin session">
        <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
      </a>
    </div>
  </div>

  <?php echo $message; ?>

  <!-- Stats Grid -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-white h-100" style="background: linear-gradient(135deg, #0b6623, #15803d); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$total_courses; ?></h2>
            <small class="opacity-75">Available Courses</small>
          </div>
          <i class="fa-solid fa-book-open fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-dark h-100" style="background: linear-gradient(135deg, #f4b41a, #fcd34d); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$total_modules; ?></h2>
            <small class="text-dark-50">Uploaded Modules / Files</small>
          </div>
          <i class="fa-solid fa-layer-group fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-white h-100" style="background: linear-gradient(135deg, #0284c7, #38bdf8); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$total_students; ?></h2>
            <small class="opacity-75">Registered Students</small>
          </div>
          <i class="fa-solid fa-users fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm p-3 text-white h-100" style="background: linear-gradient(135deg, #6366f1, #818cf8); border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="fw-bold mb-0"><?php echo (int)$total_facilitators; ?></h2>
            <small class="opacity-75">Active Facilitators</small>
          </div>
          <i class="fa-solid fa-chalkboard-user fa-2x opacity-50"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Secondary CMS Stats Bar -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg">
      <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-danger h-100" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small text-uppercase fw-semibold">Administration</span>
            <h4 class="fw-bold mb-0 text-dark"><?php echo (int)$total_admins; ?> <span class="fs-6 fw-normal text-muted">Admins</span></h4>
          </div>
          <div class="rounded-circle bg-danger-subtle p-2 text-danger">
            <i class="fa-solid fa-user-shield fa-lg"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg">
      <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-success h-100" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small text-uppercase fw-semibold">Leadership</span>
            <h4 class="fw-bold mb-0 text-dark"><?php echo (int)$total_officers; ?> <span class="fs-6 fw-normal text-muted">Officers</span></h4>
          </div>
          <div class="rounded-circle bg-success-subtle p-2 text-success">
            <i class="fa-solid fa-users-gear fa-lg"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg">
      <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-info h-100" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small text-uppercase fw-semibold">Partners</span>
            <h4 class="fw-bold mb-0 text-dark"><?php echo (int)$total_partners; ?> <span class="fs-6 fw-normal text-muted">Hub Partners</span></h4>
          </div>
          <div class="rounded-circle bg-info-subtle p-2 text-info">
            <i class="fa-solid fa-handshake fa-lg"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg">
      <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-primary h-100" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small text-uppercase fw-semibold">News &amp; Ops</span>
            <h4 class="fw-bold mb-0 text-dark"><?php echo (int)$total_news; ?> <span class="fs-6 fw-normal text-muted">Posts</span></h4>
          </div>
          <div class="rounded-circle bg-primary-subtle p-2 text-primary">
            <i class="fa-solid fa-bullhorn fa-lg"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg">
      <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-warning h-100" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small text-uppercase fw-semibold">Header Slider (10s)</span>
            <h4 class="fw-bold mb-0 text-dark"><?php echo (int)$active_slides_count; ?> <span class="fs-6 fw-normal text-muted">/ <?php echo (int)$total_slides; ?> Active</span></h4>
          </div>
          <div class="rounded-circle bg-warning-subtle p-2 text-warning">
            <i class="fa-solid fa-sliders fa-lg"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <ul class="nav nav-pills mb-4 gap-2 bg-light p-2 rounded-4 border" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active rounded-pill fw-bold" id="courses-tab" data-bs-toggle="pill" data-bs-target="#courses-pane" type="button" role="tab">
        <i class="fa-solid fa-book-bookmark me-2"></i>Courses (<?php echo count($allCourses); ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="facilitators-tab" data-bs-toggle="pill" data-bs-target="#facilitators-pane" type="button" role="tab">
        <i class="fa-solid fa-user-tie me-2"></i>Facilitators (<?php echo (int)$total_facilitators; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="admins-tab" data-bs-toggle="pill" data-bs-target="#admins-pane" type="button" role="tab">
        <i class="fa-solid fa-user-shield me-2"></i>Admins (<?php echo (int)$total_admins; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="materials-tab" data-bs-toggle="pill" data-bs-target="#materials-pane" type="button" role="tab">
        <i class="fa-solid fa-folder-open me-2"></i>Materials (<?php echo (int)$total_modules; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="enrollments-tab" data-bs-toggle="pill" data-bs-target="#enrollments-pane" type="button" role="tab">
        <i class="fa-solid fa-graduation-cap me-2"></i>Enrollments (<?php echo (int)$total_enrollments; ?>)
        <?php if ($pending_enrollments > 0): ?>
          <span class="badge bg-danger rounded-pill ms-1"><?php echo (int)$pending_enrollments; ?> Pending</span>
        <?php endif; ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="officers-tab" data-bs-toggle="pill" data-bs-target="#officers-pane" type="button" role="tab">
        <i class="fa-solid fa-users-gear me-2"></i>Officers (<?php echo (int)$total_officers; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="partners-tab" data-bs-toggle="pill" data-bs-target="#partners-pane" type="button" role="tab">
        <i class="fa-solid fa-handshake me-2"></i>Partners (<?php echo (int)$total_partners; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="gallery-tab" data-bs-toggle="pill" data-bs-target="#gallery-pane" type="button" role="tab">
        <i class="fa-solid fa-images me-2"></i>Gallery (<?php echo (int)$total_gallery; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="news-tab" data-bs-toggle="pill" data-bs-target="#news-pane" type="button" role="tab">
        <i class="fa-solid fa-newspaper me-2"></i>News &amp; Opportunities (<?php echo (int)$total_news; ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link rounded-pill fw-bold" id="slider-tab" data-bs-toggle="pill" data-bs-target="#slider-pane" type="button" role="tab">
        <i class="fa-solid fa-sliders me-2 text-warning"></i>Header Slider (<?php echo (int)$total_slides; ?>)
      </button>
    </li>
  </ul>

  <!-- Tab Content -->
  <div class="tab-content" id="adminTabsContent">
    
    <!-- PANE 1: COURSES MANAGEMENT -->
    <div class="tab-pane fade show active" id="courses-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0">All Hub Courses</h5>
            <small class="text-muted">Total of <?php echo count($allCourses); ?> courses currently in curriculum</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <input type="text" id="courseSearchInput" class="form-control form-control-sm" placeholder="Filter courses..." style="width: 200px;" onkeyup="filterAdminCourses()">
            <button class="btn btn-info text-white btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#assignCourseGeneralModal">
              <i class="fa-solid fa-user-check me-1"></i>Assign Facilitator
            </button>
            <button class="btn btn-hub btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addCourseModal">
              <i class="fa-solid fa-plus me-1"></i>Add Course
            </button>
            <button class="btn btn-warning btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
              <i class="fa-solid fa-upload me-1"></i>Upload Material
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="coursesAdminTable">
            <thead class="table-light">
              <tr>
                <th style="width: 45px;">#</th>
                <th>Course Title</th>
                <th>Category</th>
                <th>Assigned Facilitator</th>
                <th>Modules</th>
                <th>Students</th>
                <th class="text-end" style="min-width: 220px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allCourses as $c): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border">#<?php echo (int)$c['id']; ?></span></td>
                  <td>
                    <div class="fw-bold">
                      <a href="course_view.php?id=<?php echo (int)$c['id']; ?>" class="text-dark text-decoration-none" title="Preview course">
                        <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                      </a>
                    </div>
                    <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 75, '...'), ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
                  <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($c['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td>
                    <div class="d-flex align-items-center gap-1">
                      <span class="fw-semibold text-dark small">
                        <i class="fa-solid fa-user-tie me-1 text-hub"></i><?php echo htmlspecialchars($c['facilitator_name'] ?? 'Hub Admin', ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                      <button class="btn btn-outline-info btn-sm py-0 px-2" style="font-size: 0.75rem;" title="Change facilitator assignment" data-bs-toggle="modal" data-bs-target="#assignCourseModal<?php echo (int)$c['id']; ?>">
                        <i class="fa-solid fa-user-pen me-1"></i>Assign
                      </button>
                    </div>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModulesModal<?php echo (int)$c['id']; ?>">
                      <i class="fa-solid fa-layer-group me-1"></i><?php echo (int)$c['module_count']; ?> Modules
                    </button>
                  </td>
                  <td><span class="badge bg-light text-secondary border"><i class="fa-solid fa-user-graduate me-1"></i><?php echo (int)$c['student_count']; ?></span></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-info" title="Assign Facilitator to this Course" data-bs-toggle="modal" data-bs-target="#assignCourseModal<?php echo (int)$c['id']; ?>">
                        <i class="fa-solid fa-user-check"></i>
                      </button>
                      <button class="btn btn-outline-warning" title="Upload Material to this course" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal" onclick="setUploadCourseId(<?php echo (int)$c['id']; ?>)">
                        <i class="fa-solid fa-upload"></i>
                      </button>
                      <button class="btn btn-outline-secondary" title="Edit Course Details" data-bs-toggle="modal" data-bs-target="#editCourseModal<?php echo (int)$c['id']; ?>">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                      <button class="btn btn-outline-danger" title="Delete Course" data-bs-toggle="modal" data-bs-target="#deleteCourseModal<?php echo (int)$c['id']; ?>">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- MODAL: ASSIGN FACILITATOR TO THIS COURSE -->
                <div class="modal fade" id="assignCourseModal<?php echo (int)$c['id']; ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <form method="POST">
                        <div class="modal-header bg-info text-white">
                          <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-user-check me-2"></i>Assign Course Facilitator
                          </h5>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                          <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                          <div class="alert alert-light border mb-3">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Target Course</div>
                            <h6 class="fw-bold mb-0 text-hub"><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                            <small class="text-muted">Category: <?php echo htmlspecialchars($c['category'], ENT_QUOTES, 'UTF-8'); ?></small>
                          </div>

                          <div class="mb-3">
                            <label class="form-label fw-bold">Select Facilitator / Instructor <span class="text-danger">*</span></label>
                            <select name="facilitator_id" class="form-select form-select-lg" required>
                              <?php foreach ($facilitatorsList as $f): ?>
                                <option value="<?php echo (int)$f['id']; ?>" <?php echo ((int)$c['created_by'] === (int)$f['id']) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo ucfirst($f['role']); ?> - <?php echo htmlspecialchars($f['email'], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                              <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">
                              The assigned facilitator will gain access to upload modules, review materials, and manage students for this course in their Facilitator Portal.
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer bg-light">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="assign_facilitator" class="btn btn-info text-white fw-bold px-4">
                            <i class="fa-solid fa-check me-1"></i>Confirm Assignment
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- EDIT COURSE MODAL -->
                <div class="modal fade" id="editCourseModal<?php echo (int)$c['id']; ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <form method="POST">
                        <div class="modal-header bg-light">
                          <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-hub"></i>Edit Course</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                          <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Course Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="150">
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($c['category'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Assigned Facilitator</label>
                            <select name="facilitator_id" class="form-select">
                              <?php foreach ($facilitatorsList as $f): ?>
                                <option value="<?php echo (int)$f['id']; ?>" <?php echo ((int)$c['created_by'] === (int)$f['id']) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($f['email'], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                          </div>
                        </div>
                        <div class="modal-footer bg-light">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="edit_course" class="btn btn-hub fw-semibold">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- DELETE COURSE MODAL -->
                <div class="modal fade" id="deleteCourseModal<?php echo (int)$c['id']; ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <form method="POST">
                        <div class="modal-header bg-danger text-white">
                          <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Delete Course</h5>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                          <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                          <p class="mb-2">Are you sure you want to permanently delete <strong><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?></strong>?</p>
                          <div class="alert alert-warning small mb-0">
                            <i class="fa-solid fa-info-circle me-1"></i> This will also remove all <?php echo (int)$c['module_count']; ?> modules, uploaded material files, quizzes, and student enrollments for this course.
                          </div>
                        </div>
                        <div class="modal-footer bg-light">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="delete_course" class="btn btn-danger fw-semibold">Delete Course</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- VIEW MODULES MODAL -->
                <div class="modal fade" id="viewModulesModal<?php echo (int)$c['id']; ?>" tabindex="-1">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold">
                          <i class="fa-solid fa-layer-group me-2 text-hub"></i>Modules for <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body p-4">
                        <?php 
                          $cModStmt = $conn->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY id ASC");
                          $cModStmt->bind_param("i", $c['id']);
                          $cModStmt->execute();
                          $courseModules = $cModStmt->get_result();
                        ?>
                        <?php if ($courseModules->num_rows > 0): ?>
                          <div class="list-group">
                            <?php while ($m = $courseModules->fetch_assoc()): ?>
                              <div class="list-group-item d-flex justify-content-between align-items-start p-3 mb-2 border rounded">
                                <div class="me-3">
                                  <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                  <?php if (!empty($m['content'])): ?>
                                    <p class="small text-muted mb-2"><?php echo htmlspecialchars(mb_strimwidth($m['content'], 0, 160, '...'), ENT_QUOTES, 'UTF-8'); ?></p>
                                  <?php endif; ?>
                                  <?php if (!empty($m['file_path']) && file_exists(__DIR__ . '/' . $m['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($m['file_path'], ENT_QUOTES, 'UTF-8'); ?>" download class="badge bg-success text-decoration-none py-2 px-3">
                                      <i class="fa-solid fa-download me-1"></i>Download <?php echo strtoupper(pathinfo($m['file_path'], PATHINFO_EXTENSION)); ?> File
                                    </a>
                                  <?php else: ?>
                                    <span class="badge bg-secondary">No file attached</span>
                                  <?php endif; ?>
                                </div>
                                <form method="POST" onsubmit="return confirm('Delete this module?');">
                                  <input type="hidden" name="module_id" value="<?php echo (int)$m['id']; ?>">
                                  <button type="submit" name="delete_module" class="btn btn-outline-danger btn-sm" title="Delete module">
                                    <i class="fa-solid fa-trash"></i>
                                  </button>
                                </form>
                              </div>
                            <?php endwhile; ?>
                          </div>
                        <?php else: ?>
                          <div class="alert alert-info text-center py-4">
                            <i class="fa-solid fa-file-circle-question fa-2x mb-2 text-info"></i>
                            <p class="mb-2">No learning modules have been uploaded for this course yet.</p>
                            <button class="btn btn-hub btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal" onclick="setUploadCourseId(<?php echo (int)$c['id']; ?>)">
                              <i class="fa-solid fa-upload me-1"></i>Upload First Module
                            </button>
                          </div>
                        <?php endif; ?>
                        <?php $cModStmt->close(); ?>
                      </div>
                      <div class="modal-footer bg-light justify-content-between">
                        <button class="btn btn-warning btn-sm fw-semibold" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal" onclick="setUploadCourseId(<?php echo (int)$c['id']; ?>)">
                          <i class="fa-solid fa-plus me-1"></i>Attach More Materials
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>

              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 2: FACILITATOR MANAGEMENT TAB -->
    <div class="tab-pane fade" id="facilitators-pane" role="tabpanel">
      <div class="row g-4">
        <!-- Add Facilitator Form -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-user-plus me-2 text-hub"></i>Add New Facilitator</h5>
            <form method="POST">
              <div class="mb-3">
                <label class="form-label fw-semibold">Full Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Aminu Ibrahim" required maxlength="100">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="facilitator@kibiyahub.org" required maxlength="100">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Password (minimum 8 characters)</label>
                <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
              </div>
              <button type="submit" name="add_facilitator" class="btn btn-hub w-100 fw-bold py-2">
                <i class="fa-solid fa-user-check me-2"></i>Register Facilitator
              </button>
            </form>
          </div>

          <!-- Quick Course Assignment Card -->
          <div class="card border-0 shadow-sm p-4" style="border-radius: 16px; background: linear-gradient(135deg, #f0fdf4, #e0f2fe);">
            <h5 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-link me-2 text-hub"></i>Quick Course Assignment</h5>
            <p class="small text-muted mb-3">Assign any course to an instructor to give them teaching and module management access.</p>
            <button class="btn btn-info text-white fw-bold w-100 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#assignCourseGeneralModal">
              <i class="fa-solid fa-user-gear me-2"></i>Open Assignment Tool
            </button>
          </div>
        </div>

        <!-- Facilitators and their Assigned Courses Table -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold mb-0"><i class="fa-solid fa-chalkboard-user me-2 text-hub"></i>Facilitators &amp; Assigned Courses</h5>
              <button class="btn btn-info text-white btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#assignCourseGeneralModal">
                <i class="fa-solid fa-plus me-1"></i>Assign Courses
              </button>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Facilitator</th>
                    <th>Role &amp; Contact</th>
                    <th>Courses Leading</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($facilitatorsList as $f): ?>
                    <?php 
                      $assignedList = !empty($f['assigned_course_titles']) ? explode('||', $f['assigned_course_titles']) : [];
                    ?>
                    <tr>
                      <td class="fw-bold">
                        <div class="d-flex align-items-center">
                          <div class="rounded-circle bg-hub text-white d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;font-size:0.9rem;">
                            <i class="fa-solid fa-user"></i>
                          </div>
                          <div>
                            <div><?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="badge <?php echo $f['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>" style="font-size: 0.68rem;">
                              <?php echo strtoupper($f['role']); ?>
                            </span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <small class="text-muted d-block"><?php echo htmlspecialchars($f['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                        <small class="text-muted" style="font-size: 0.72rem;">Joined <?php echo date('M d, Y', strtotime($f['created_at'])); ?></small>
                      </td>
                      <td>
                        <div class="mb-1">
                          <span class="badge <?php echo count($assignedList) > 0 ? 'bg-hub text-white' : 'bg-light text-muted border'; ?>">
                            <?php echo count($assignedList); ?> Assigned
                          </span>
                        </div>
                        <?php if (!empty($assignedList)): ?>
                          <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                            <?php foreach (array_slice($assignedList, 0, 3) as $cTitle): ?>
                              <span class="badge bg-light text-dark border small text-truncate" style="max-width: 120px;" title="<?php echo htmlspecialchars($cTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($cTitle, ENT_QUOTES, 'UTF-8'); ?>
                              </span>
                            <?php endforeach; ?>
                            <?php if (count($assignedList) > 3): ?>
                              <span class="badge bg-secondary small">+<?php echo count($assignedList) - 3; ?> more</span>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <small class="text-muted fst-italic">None assigned</small>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-outline-info btn-sm fw-semibold" title="Assign courses to this facilitator" data-bs-toggle="modal" data-bs-target="#assignBatchToFacModal<?php echo (int)$f['id']; ?>">
                          <i class="fa-solid fa-plus me-1"></i>Assign
                        </button>
                      </td>
                    </tr>

                    <!-- MODAL: BATCH ASSIGN COURSES TO THIS FACILITATOR -->
                    <div class="modal fade" id="assignBatchToFacModal<?php echo (int)$f['id']; ?>" tabindex="-1">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow">
                          <form method="POST">
                            <input type="hidden" name="facilitator_id" value="<?php echo (int)$f['id']; ?>">
                            <div class="modal-header bg-info text-white">
                              <h5 class="modal-title fw-bold">
                                <i class="fa-solid fa-user-check me-2"></i>Assign Courses to <?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                              </h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                              <p class="text-muted small mb-3">Check the courses you wish to assign to <strong><?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>. They will be able to upload materials and manage lessons for selected programs.</p>

                              <div class="border rounded p-3 bg-light" style="max-height: 320px; overflow-y: auto;">
                                <div class="row g-2">
                                  <?php foreach ($allCourses as $crs): ?>
                                    <?php $isAssignedToThis = ((int)$crs['created_by'] === (int)$f['id']); ?>
                                    <div class="col-md-6">
                                      <div class="form-check p-2 bg-white rounded border">
                                        <input class="form-check-input ms-1 me-2" type="checkbox" name="course_ids[]" value="<?php echo (int)$crs['id']; ?>" id="chk_f<?php echo (int)$f['id']; ?>_c<?php echo (int)$crs['id']; ?>" <?php echo $isAssignedToThis ? 'checked' : ''; ?>>
                                        <label class="form-check-label small fw-semibold text-truncate d-block" for="chk_f<?php echo (int)$f['id']; ?>_c<?php echo (int)$crs['id']; ?>" title="<?php echo htmlspecialchars($crs['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                          #<?php echo (int)$crs['id']; ?> <?php echo htmlspecialchars($crs['title'], ENT_QUOTES, 'UTF-8'); ?>
                                          <?php if ($isAssignedToThis): ?>
                                            <span class="badge bg-success-subtle text-success ms-1">Currently Assigned</span>
                                          <?php endif; ?>
                                        </label>
                                      </div>
                                    </div>
                                  <?php endforeach; ?>
                                </div>
                              </div>
                            </div>
                            <div class="modal-footer bg-light">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" name="assign_batch_courses" class="btn btn-info text-white fw-bold px-4">
                                <i class="fa-solid fa-save me-1"></i>Save Assignments
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PANE 3: UPLOADED MATERIALS TAB -->
    <div class="tab-pane fade" id="materials-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0">All Uploaded Course Modules &amp; Materials</h5>
            <small class="text-muted">Direct management of curriculum files, lecture notes, and downloadable assets</small>
          </div>
          <button class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
            <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload New Material
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Course</th>
                <th>Module Title</th>
                <th>Attached File</th>
                <th>Uploaded At</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($modulesQuery->num_rows > 0): ?>
                <?php while ($m = $modulesQuery->fetch_assoc()): ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border">#<?php echo (int)$m['id']; ?></span></td>
                    <td><strong class="text-hub"><?php echo htmlspecialchars($m['course_title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php if (!empty($m['content'])): ?>
                        <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($m['content'], 0, 70, '...'), ENT_QUOTES, 'UTF-8'); ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($m['file_path']) && file_exists(__DIR__ . '/' . $m['file_path'])): ?>
                        <a href="<?php echo htmlspecialchars($m['file_path'], ENT_QUOTES, 'UTF-8'); ?>" download class="btn btn-sm btn-outline-success">
                          <i class="fa-solid fa-download me-1"></i><?php echo strtoupper(pathinfo($m['file_path'], PATHINFO_EXTENSION)); ?>
                        </a>
                      <?php else: ?>
                        <span class="text-muted small">Text Notes Only</span>
                      <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?php echo date('M d, Y', strtotime($m['uploaded_at'])); ?></small></td>
                    <td class="text-end">
                      <form method="POST" onsubmit="return confirm('Delete this material?');" style="display:inline;">
                        <input type="hidden" name="module_id" value="<?php echo (int)$m['id']; ?>">
                        <button type="submit" name="delete_module" class="btn btn-outline-danger btn-sm" title="Delete">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">No materials uploaded yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 4: ENROLLMENTS TAB -->
    <div class="tab-pane fade" id="enrollments-pane" role="tabpanel">
      <!-- Summary Metric Cards -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px; background: linear-gradient(135deg, #0b6623, #15803d); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-white-50 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Total Enrollments</small>
                <h3 class="fw-bold mb-0 mt-1"><?php echo (int)$total_enrollments; ?></h3>
              </div>
              <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                <i class="fa-solid fa-graduation-cap fa-lg"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px; background: <?php echo $pending_enrollments > 0 ? 'linear-gradient(135deg, #d97706, #f59e0b)' : 'linear-gradient(135deg, #64748b, #94a3b8)'; ?>; color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-white-50 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Pending Approval</small>
                <h3 class="fw-bold mb-0 mt-1"><?php echo (int)$pending_enrollments; ?></h3>
              </div>
              <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                <i class="fa-solid fa-clock-rotate-left fa-lg"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px; background: linear-gradient(135deg, #0284c7, #0ea5e9); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-white-50 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Active / Enrolled</small>
                <h3 class="fw-bold mb-0 mt-1"><?php echo (int)$active_enrollments; ?></h3>
              </div>
              <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                <i class="fa-solid fa-circle-check fa-lg"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px; background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-white-50 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Completed Courses</small>
                <h3 class="fw-bold mb-0 mt-1"><?php echo (int)$completed_enrollments; ?></h3>
              </div>
              <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                <i class="fa-solid fa-award fa-lg"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-graduation-cap me-2 text-hub"></i>Student Course Enrollment Access</h5>
            <small class="text-muted">Review, grant access, or decline student enrollments. Pending students cannot start courses until approved.</small>
          </div>
          <?php if ($pending_enrollments > 0): ?>
            <form method="POST" onsubmit="return confirm('Approve all <?php echo (int)$pending_enrollments; ?> pending student enrollment requests and grant course access?');">
              <button type="submit" name="approve_all_pending" class="btn btn-success fw-bold btn-sm shadow-sm">
                <i class="fa-solid fa-check-double me-1"></i>Approve All Pending (<?php echo (int)$pending_enrollments; ?>)
              </button>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($pending_enrollments > 0): ?>
          <div class="alert alert-warning border-0 rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-triangle-exclamation text-warning fs-5"></i>
              <div>
                <strong><?php echo (int)$pending_enrollments; ?> Student(s) Waiting for Access:</strong>
                <span class="small text-secondary d-block">These students cannot view modules or take assessments until you click "Give Access".</span>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Student</th>
                <th>Course</th>
                <th>Status</th>
                <th>Quiz Score</th>
                <th>Date Requested</th>
                <th class="text-end">Access Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($enrollQuery && $enrollQuery->num_rows > 0): ?>
                <?php while ($enr = $enrollQuery->fetch_assoc()): ?>
                  <tr class="<?php echo $enr['status'] === 'pending' ? 'table-warning bg-opacity-25' : ''; ?>">
                    <td>
                      <div class="fw-bold"><?php echo htmlspecialchars($enr['student_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <small class="text-muted"><?php echo htmlspecialchars($enr['student_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                      <?php if (!empty($enr['student_phone'])): ?>
                        <br><small class="text-muted"><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($enr['student_phone'], ENT_QUOTES, 'UTF-8'); ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong class="text-hub"><?php echo htmlspecialchars($enr['course_title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </td>
                    <td>
                      <?php if ($enr['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark px-2 py-1">
                          <i class="fa-solid fa-clock me-1"></i>Pending Approval
                        </span>
                      <?php elseif ($enr['status'] === 'enrolled'): ?>
                        <span class="badge bg-success px-2 py-1">
                          <i class="fa-solid fa-circle-check me-1"></i>Access Granted
                        </span>
                      <?php elseif ($enr['status'] === 'completed'): ?>
                        <span class="badge bg-primary px-2 py-1">
                          <i class="fa-solid fa-award me-1"></i>Completed
                        </span>
                      <?php elseif ($enr['status'] === 'rejected'): ?>
                        <span class="badge bg-danger px-2 py-1">
                          <i class="fa-solid fa-ban me-1"></i>Declined
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?php echo ucfirst($enr['status']); ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php echo $enr['quiz_score'] !== null ? (int)$enr['quiz_score'] . '%' : '<span class="text-muted small">Not taken</span>'; ?>
                    </td>
                    <td><small class="text-muted"><?php echo date('M d, Y', strtotime($enr['enrolled_at'])); ?></small></td>
                    <td class="text-end">
                      <div class="d-inline-flex align-items-center gap-1">
                        <?php if ($enr['status'] === 'pending'): ?>
                          <form method="POST" class="d-inline">
                            <input type="hidden" name="enrollment_id" value="<?php echo (int)$enr['id']; ?>">
                            <button type="submit" name="approve_enrollment" class="btn btn-success btn-sm fw-bold shadow-sm" title="Grant access to course">
                              <i class="fa-solid fa-user-check me-1"></i>Give Access
                            </button>
                          </form>
                          <form method="POST" class="d-inline">
                            <input type="hidden" name="enrollment_id" value="<?php echo (int)$enr['id']; ?>">
                            <button type="submit" name="reject_enrollment" class="btn btn-outline-danger btn-sm" title="Decline enrollment">
                              <i class="fa-solid fa-xmark"></i>
                            </button>
                          </form>
                        <?php elseif ($enr['status'] === 'enrolled'): ?>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Revoke access for this student? The student will no longer be able to study modules.');">
                            <input type="hidden" name="enrollment_id" value="<?php echo (int)$enr['id']; ?>">
                            <button type="submit" name="reject_enrollment" class="btn btn-outline-warning btn-sm" title="Revoke course access">
                              <i class="fa-solid fa-lock me-1"></i>Revoke
                            </button>
                          </form>
                        <?php elseif ($enr['status'] === 'rejected'): ?>
                          <form method="POST" class="d-inline">
                            <input type="hidden" name="enrollment_id" value="<?php echo (int)$enr['id']; ?>">
                            <button type="submit" name="approve_enrollment" class="btn btn-outline-success btn-sm" title="Grant access">
                              <i class="fa-solid fa-user-check me-1"></i>Grant Access
                            </button>
                          </form>
                        <?php endif; ?>

                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this enrollment record completely?');">
                          <input type="hidden" name="enrollment_id" value="<?php echo (int)$enr['id']; ?>">
                          <button type="submit" name="delete_enrollment" class="btn btn-outline-secondary btn-sm" title="Delete enrollment record">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">No student enrollments found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 5: MANAGEMENT OFFICERS TAB -->
    <div class="tab-pane fade" id="officers-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-users-gear me-2 text-hub"></i>Management &amp; Leadership Officers</h5>
            <small class="text-muted">Manage executive directors, curriculum heads, and administrative personnel displayed on the public Management page.</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <input type="text" id="officerSearchInput" class="form-control form-control-sm" placeholder="Filter officers..." style="width: 200px;" onkeyup="filterAdminOfficers()">
            <button class="btn btn-hub btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addOfficerModal">
              <i class="fa-solid fa-user-plus me-1"></i>Add Officer
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="officersAdminTable">
            <thead class="table-light">
              <tr>
                <th style="width: 45px;">#</th>
                <th style="width: 80px;">Photo</th>
                <th>Name &amp; Contact</th>
                <th>Designation / Role</th>
                <th>Bio / Responsibilities</th>
                <th style="width: 70px;">Order</th>
                <th class="text-end" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($officersList)): ?>
                <?php foreach ($officersList as $off): ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border">#<?php echo (int)$off['id']; ?></span></td>
                    <td>
                      <?php if (!empty($off['image_path']) && file_exists(__DIR__ . '/' . $off['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($off['image_path'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle border border-2 border-success shadow-sm" width="60" height="60" style="object-fit:cover;" alt="Photo">
                      <?php else: ?>
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold border shadow-sm" style="width:60px;height:60px;font-size:1.35rem;">
                          <?php echo strtoupper(substr($off['name'], 0, 1)); ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-bold text-dark"><?php echo htmlspecialchars($off['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="small text-muted">
                        <?php if (!empty($off['email'])): ?>
                          <span class="me-2"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($off['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($off['phone'])): ?>
                          <span><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($off['phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <span class="badge <?php echo htmlspecialchars($off['badge_color'] ?: 'bg-success', ENT_QUOTES, 'UTF-8'); ?> p-2" style="font-size:0.8rem;">
                        <?php echo htmlspecialchars($off['role_title'], ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td>
                      <small class="text-muted d-block" style="max-width: 320px;">
                        <?php echo htmlspecialchars(mb_strimwidth($off['bio'] ?? '', 0, 90, '...'), ENT_QUOTES, 'UTF-8'); ?>
                      </small>
                    </td>
                    <td>
                      <span class="badge bg-secondary-subtle text-secondary"><?php echo (int)$off['display_order']; ?></span>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editOfficerModal<?php echo (int)$off['id']; ?>" title="Edit Officer">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this officer?');" style="display:inline;">
                          <input type="hidden" name="officer_id" value="<?php echo (int)$off['id']; ?>">
                          <button type="submit" name="delete_officer" class="btn btn-outline-danger" title="Delete Officer">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL: EDIT OFFICER -->
                  <div class="modal fade" id="editOfficerModal<?php echo (int)$off['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" enctype="multipart/form-data">
                          <input type="hidden" name="officer_id" value="<?php echo (int)$off['id']; ?>">
                          <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fw-bold">
                              <i class="fa-solid fa-user-pen me-2"></i>Edit Officer: <?php echo htmlspecialchars($off['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-3">
                              <div class="col-md-7">
                                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($off['name'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="150">
                              </div>
                              <div class="col-md-5">
                                <label class="form-label fw-bold">Position / Role Title <span class="text-danger">*</span></label>
                                <input type="text" name="role_title" class="form-control" value="<?php echo htmlspecialchars($off['role_title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="150" placeholder="e.g. Executive Director">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Badge Color Style</label>
                                <select name="badge_color" class="form-select">
                                  <option value="bg-success" <?php echo $off['badge_color'] === 'bg-success' ? 'selected' : ''; ?>>Green (bg-success)</option>
                                  <option value="bg-warning text-dark" <?php echo $off['badge_color'] === 'bg-warning text-dark' ? 'selected' : ''; ?>>Yellow (bg-warning text-dark)</option>
                                  <option value="bg-info text-dark" <?php echo $off['badge_color'] === 'bg-info text-dark' ? 'selected' : ''; ?>>Sky Blue (bg-info text-dark)</option>
                                  <option value="bg-primary" <?php echo $off['badge_color'] === 'bg-primary' ? 'selected' : ''; ?>>Blue (bg-primary)</option>
                                  <option value="bg-danger" <?php echo $off['badge_color'] === 'bg-danger' ? 'selected' : ''; ?>>Red (bg-danger)</option>
                                  <option value="bg-dark text-white" <?php echo $off['badge_color'] === 'bg-dark text-white' ? 'selected' : ''; ?>>Dark (bg-dark)</option>
                                  <option value="bg-secondary" <?php echo $off['badge_color'] === 'bg-secondary' ? 'selected' : ''; ?>>Grey (bg-secondary)</option>
                                </select>
                              </div>
                              <div class="col-md-3">
                                <label class="form-label fw-bold">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo (int)$off['display_order']; ?>">
                              </div>
                              <div class="col-md-3">
                                <label class="form-label fw-bold">Phone (Optional)</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($off['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+234...">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Email (Optional)</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($off['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="officer@kibiyahub.ng">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Replace Profile Photo (Optional)</label>
                                <input type="file" name="officer_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text small">Leave blank to retain current image.</div>
                              </div>
                              <?php if (!empty($off['image_path']) && file_exists(__DIR__ . '/' . $off['image_path'])): ?>
                                <div class="col-12 d-flex align-items-center gap-3 bg-light p-2 rounded">
                                  <img src="<?php echo htmlspecialchars($off['image_path'], ENT_QUOTES, 'UTF-8'); ?>" width="80" height="80" class="rounded-circle border border-2 shadow-sm" style="object-fit:cover;">
                                  <span class="small text-muted">Current photo attached: <code><?php echo htmlspecialchars($off['image_path'], ENT_QUOTES, 'UTF-8'); ?></code></span>
                                </div>
                              <?php endif; ?>
                              <div class="col-12">
                                <label class="form-label fw-bold">Bio &amp; Key Responsibilities</label>
                                <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($off['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                              </div>
                            </div>
                          </div>
                          <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_officer" class="btn btn-success fw-bold px-4">
                              <i class="fa-solid fa-save me-1"></i>Save Changes
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No management officers added yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 6: PARTNERS TAB -->
    <div class="tab-pane fade" id="partners-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-handshake me-2 text-hub"></i>Hub Partners &amp; Stakeholders</h5>
            <small class="text-muted">Manage government partners, development agencies, corporate sponsors, and community leadership displayed on the Partners page.</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <input type="text" id="partnerSearchInput" class="form-control form-control-sm" placeholder="Filter partners..." style="width: 200px;" onkeyup="filterAdminPartners()">
            <button class="btn btn-info text-white btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
              <i class="fa-solid fa-handshake me-1"></i>Add Partner
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="partnersAdminTable">
            <thead class="table-light">
              <tr>
                <th style="width: 45px;">#</th>
                <th style="width: 65px;">Logo / Icon</th>
                <th>Partner Name</th>
                <th>Category</th>
                <th>Description</th>
                <th>Website</th>
                <th style="width: 70px;">Order</th>
                <th class="text-end" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($partnersList)): ?>
                <?php foreach ($partnersList as $p): ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border">#<?php echo (int)$p['id']; ?></span></td>
                    <td>
                      <?php if (!empty($p['logo_path']) && file_exists(__DIR__ . '/' . $p['logo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($p['logo_path'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded border p-1" width="48" height="48" style="object-fit:contain;background:#fff;" alt="Logo">
                      <?php else: ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px;background:<?php echo htmlspecialchars($p['icon_bg'] ?: '#0b6623', ENT_QUOTES, 'UTF-8'); ?>;">
                          <i class="fa-solid <?php echo htmlspecialchars($p['icon'] ?: 'fa-handshake', ENT_QUOTES, 'UTF-8'); ?> fa-lg"></i>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong class="text-dark"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </td>
                    <td>
                      <span class="badge <?php echo htmlspecialchars($p['badge_color'] ?: 'bg-success', ENT_QUOTES, 'UTF-8'); ?> p-2" style="font-size:0.8rem;">
                        <?php echo htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td>
                      <small class="text-muted d-block" style="max-width: 320px;">
                        <?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 90, '...'), ENT_QUOTES, 'UTF-8'); ?>
                      </small>
                    </td>
                    <td>
                      <?php if (!empty($p['website_url'])): ?>
                        <a href="<?php echo htmlspecialchars($p['website_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-outline-info text-nowrap">
                          <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Visit
                        </a>
                      <?php else: ?>
                        <span class="text-muted small fst-italic">None</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-secondary-subtle text-secondary"><?php echo (int)$p['display_order']; ?></span>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPartnerModal<?php echo (int)$p['id']; ?>" title="Edit Partner">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Delete this partner?');" style="display:inline;">
                          <input type="hidden" name="partner_id" value="<?php echo (int)$p['id']; ?>">
                          <button type="submit" name="delete_partner" class="btn btn-outline-danger" title="Delete Partner">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL: EDIT PARTNER -->
                  <div class="modal fade" id="editPartnerModal<?php echo (int)$p['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" enctype="multipart/form-data">
                          <input type="hidden" name="partner_id" value="<?php echo (int)$p['id']; ?>">
                          <div class="modal-header bg-info text-white">
                            <h5 class="modal-title fw-bold">
                              <i class="fa-solid fa-handshake me-2"></i>Edit Partner: <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-3">
                              <div class="col-md-7">
                                <label class="form-label fw-bold">Partner Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="150">
                              </div>
                              <div class="col-md-5">
                                <label class="form-label fw-bold">Partnership Category</label>
                                <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Government Partner, Tech Development" required maxlength="100">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Badge Color</label>
                                <select name="badge_color" class="form-select">
                                  <option value="bg-success" <?php echo $p['badge_color'] === 'bg-success' ? 'selected' : ''; ?>>Green (bg-success)</option>
                                  <option value="bg-warning text-dark" <?php echo $p['badge_color'] === 'bg-warning text-dark' ? 'selected' : ''; ?>>Yellow (bg-warning text-dark)</option>
                                  <option value="bg-info text-white" <?php echo $p['badge_color'] === 'bg-info text-white' ? 'selected' : ''; ?>>Sky Blue (bg-info text-white)</option>
                                  <option value="bg-primary" <?php echo $p['badge_color'] === 'bg-primary' ? 'selected' : ''; ?>>Blue (bg-primary)</option>
                                  <option value="bg-dark" <?php echo $p['badge_color'] === 'bg-dark' ? 'selected' : ''; ?>>Dark (bg-dark)</option>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">FontAwesome Icon</label>
                                <select name="icon" class="form-select">
                                  <option value="fa-landmark" <?php echo $p['icon'] === 'fa-landmark' ? 'selected' : ''; ?>>fa-landmark (Government)</option>
                                  <option value="fa-globe" <?php echo $p['icon'] === 'fa-globe' ? 'selected' : ''; ?>>fa-globe (Global / Tech)</option>
                                  <option value="fa-users" <?php echo $p['icon'] === 'fa-users' ? 'selected' : ''; ?>>fa-users (Community)</option>
                                  <option value="fa-handshake" <?php echo $p['icon'] === 'fa-handshake' ? 'selected' : ''; ?>>fa-handshake (Partnership)</option>
                                  <option value="fa-building" <?php echo $p['icon'] === 'fa-building' ? 'selected' : ''; ?>>fa-building (Corporate)</option>
                                  <option value="fa-graduation-cap" <?php echo $p['icon'] === 'fa-graduation-cap' ? 'selected' : ''; ?>>fa-graduation-cap (Academic)</option>
                                  <option value="fa-award" <?php echo $p['icon'] === 'fa-award' ? 'selected' : ''; ?>>fa-award (Sponsor)</option>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Icon Circle Background</label>
                                <input type="color" name="icon_bg" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($p['icon_bg'] ?: '#0b6623', ENT_QUOTES, 'UTF-8'); ?>">
                              </div>
                              <div class="col-md-8">
                                <label class="form-label fw-bold">Website URL (Optional)</label>
                                <input type="url" name="website_url" class="form-control" value="<?php echo htmlspecialchars($p['website_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo (int)$p['display_order']; ?>">
                              </div>
                              <div class="col-md-12">
                                <label class="form-label fw-bold">Replace Partner Logo Image (Optional)</label>
                                <input type="file" name="partner_logo" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                                <div class="form-text small">If an image logo is uploaded, it is displayed instead of the default icon circle.</div>
                              </div>
                              <?php if (!empty($p['logo_path']) && file_exists(__DIR__ . '/' . $p['logo_path'])): ?>
                                <div class="col-12 d-flex align-items-center gap-3 bg-light p-2 rounded">
                                  <img src="<?php echo htmlspecialchars($p['logo_path'], ENT_QUOTES, 'UTF-8'); ?>" width="55" height="55" class="rounded border p-1 bg-white" style="object-fit:contain;">
                                  <span class="small text-muted">Current logo attached: <code><?php echo htmlspecialchars($p['logo_path'], ENT_QUOTES, 'UTF-8'); ?></code></span>
                                </div>
                              <?php endif; ?>
                              <div class="col-12">
                                <label class="form-label fw-bold">Description / Support Overview</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                              </div>
                            </div>
                          </div>
                          <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_partner" class="btn btn-info text-white fw-bold px-4">
                              <i class="fa-solid fa-save me-1"></i>Save Changes
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">No partners added yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 7: GALLERY TAB -->
    <div class="tab-pane fade" id="gallery-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-images me-2 text-hub"></i>Photo Gallery &amp; Events Showcase</h5>
            <small class="text-muted">Upload hub training moments, tech workshops, hackathon ceremonies, and computer lab activities for public display.</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <input type="text" id="gallerySearchInput" class="form-control form-control-sm" placeholder="Filter gallery..." style="width: 200px;" onkeyup="filterAdminGallery()">
            <button class="btn btn-warning text-dark btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addGalleryModal">
              <i class="fa-solid fa-cloud-arrow-up me-1"></i>Add Gallery Item
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="galleryAdminTable">
            <thead class="table-light">
              <tr>
                <th style="width: 45px;">#</th>
                <th style="width: 90px;">Preview</th>
                <th>Title &amp; Category</th>
                <th>Description</th>
                <th style="width: 70px;">Order</th>
                <th class="text-end" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($galleryList)): ?>
                <?php foreach ($galleryList as $g): ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border">#<?php echo (int)$g['id']; ?></span></td>
                    <td>
                      <?php if (!empty($g['image_path']) && file_exists(__DIR__ . '/' . $g['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($g['image_path'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded border shadow-sm" width="80" height="52" style="object-fit:cover;" alt="Photo">
                      <?php else: ?>
                        <div class="rounded text-center d-flex align-items-center justify-content-center text-white shadow-sm" style="width:80px;height:52px;background:<?php echo htmlspecialchars($g['gradient_bg'] ?: 'linear-gradient(135deg, #0b6623, #0d7a2b)', ENT_QUOTES, 'UTF-8'); ?>;">
                          <i class="fa-solid <?php echo htmlspecialchars($g['icon'] ?: 'fa-camera', ENT_QUOTES, 'UTF-8'); ?> fa-lg"></i>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-bold text-dark"><?php echo htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <span class="badge bg-secondary-subtle text-dark border small mt-1">
                        <?php echo htmlspecialchars($g['category'] ?: 'Activities', ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td>
                      <small class="text-muted d-block" style="max-width: 350px;">
                        <?php echo htmlspecialchars(mb_strimwidth($g['description'] ?? '', 0, 100, '...'), ENT_QUOTES, 'UTF-8'); ?>
                      </small>
                    </td>
                    <td>
                      <span class="badge bg-secondary-subtle text-secondary"><?php echo (int)$g['display_order']; ?></span>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editGalleryModal<?php echo (int)$g['id']; ?>" title="Edit Gallery Item">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Delete this gallery item?');" style="display:inline;">
                          <input type="hidden" name="gallery_id" value="<?php echo (int)$g['id']; ?>">
                          <button type="submit" name="delete_gallery" class="btn btn-outline-danger" title="Delete Item">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL: EDIT GALLERY ITEM -->
                  <div class="modal fade" id="editGalleryModal<?php echo (int)$g['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" enctype="multipart/form-data">
                          <input type="hidden" name="gallery_id" value="<?php echo (int)$g['id']; ?>">
                          <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold">
                              <i class="fa-solid fa-images me-2"></i>Edit Gallery Item: <?php echo htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-3">
                              <div class="col-md-8">
                                <label class="form-label fw-bold">Title / Headline <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="150">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Category</label>
                                <select name="category" class="form-select">
                                  <option value="Training" <?php echo $g['category'] === 'Training' ? 'selected' : ''; ?>>Training</option>
                                  <option value="Workshop" <?php echo $g['category'] === 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                  <option value="Events" <?php echo $g['category'] === 'Events' ? 'selected' : ''; ?>>Events</option>
                                  <option value="Ceremony" <?php echo $g['category'] === 'Ceremony' ? 'selected' : ''; ?>>Ceremony</option>
                                  <option value="Outreach" <?php echo $g['category'] === 'Outreach' ? 'selected' : ''; ?>>Outreach</option>
                                  <option value="Lab" <?php echo $g['category'] === 'Lab' ? 'selected' : ''; ?>>Lab</option>
                                  <option value="Competition" <?php echo $g['category'] === 'Competition' ? 'selected' : ''; ?>>Competition / Hackathon</option>
                                  <option value="Activities" <?php echo $g['category'] === 'Activities' ? 'selected' : ''; ?>>Activities</option>
                                </select>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">FontAwesome Icon (Fallback)</label>
                                <select name="icon" class="form-select">
                                  <option value="fa-laptop-code" <?php echo $g['icon'] === 'fa-laptop-code' ? 'selected' : ''; ?>>fa-laptop-code (Coding)</option>
                                  <option value="fa-robot" <?php echo $g['icon'] === 'fa-robot' ? 'selected' : ''; ?>>fa-robot (Robotics / AI)</option>
                                  <option value="fa-graduation-cap" <?php echo $g['icon'] === 'fa-graduation-cap' ? 'selected' : ''; ?>>fa-graduation-cap (Graduation)</option>
                                  <option value="fa-chalkboard-user" <?php echo $g['icon'] === 'fa-chalkboard-user' ? 'selected' : ''; ?>>fa-chalkboard-user (Trainer / Facilitator)</option>
                                  <option value="fa-people-group" <?php echo $g['icon'] === 'fa-people-group' ? 'selected' : ''; ?>>fa-people-group (Community Outreach)</option>
                                  <option value="fa-lightbulb" <?php echo $g['icon'] === 'fa-lightbulb' ? 'selected' : ''; ?>>fa-lightbulb (Innovation)</option>
                                  <option value="fa-desktop" <?php echo $g['icon'] === 'fa-desktop' ? 'selected' : ''; ?>>fa-desktop (Computer Lab)</option>
                                  <option value="fa-users" <?php echo $g['icon'] === 'fa-users' ? 'selected' : ''; ?>>fa-users (Group Study)</option>
                                  <option value="fa-trophy" <?php echo $g['icon'] === 'fa-trophy' ? 'selected' : ''; ?>>fa-trophy (Hackathon / Awards)</option>
                                  <option value="fa-camera" <?php echo $g['icon'] === 'fa-camera' ? 'selected' : ''; ?>>fa-camera (General Photo)</option>
                                </select>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Banner Gradient Preset</label>
                                <select name="gradient_bg" class="form-select">
                                  <option value="linear-gradient(135deg, #0b6623, #0d7a2b)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #0b6623, #0d7a2b)' ? 'selected' : ''; ?>>Hub Emerald Green</option>
                                  <option value="linear-gradient(135deg, #1a237e, #283593)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #1a237e, #283593)' ? 'selected' : ''; ?>>Deep Indigo Navy</option>
                                  <option value="linear-gradient(135deg, #e65100, #f57c00)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #e65100, #f57c00)' ? 'selected' : ''; ?>>Vibrant Orange</option>
                                  <option value="linear-gradient(135deg, #4a148c, #6a1b9a)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #4a148c, #6a1b9a)' ? 'selected' : ''; ?>>Royal Purple</option>
                                  <option value="linear-gradient(135deg, #01579b, #0288d1)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #01579b, #0288d1)' ? 'selected' : ''; ?>>Sky Blue</option>
                                  <option value="linear-gradient(135deg, #bf360c, #e64a19)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #bf360c, #e64a19)' ? 'selected' : ''; ?>>Coral Red</option>
                                  <option value="linear-gradient(135deg, #004d40, #00695c)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #004d40, #00695c)' ? 'selected' : ''; ?>>Teal Forest</option>
                                  <option value="linear-gradient(135deg, #827717, #9e9d24)" <?php echo $g['gradient_bg'] === 'linear-gradient(135deg, #827717, #9e9d24)' ? 'selected' : ''; ?>>Gold Olive</option>
                                </select>
                              </div>
                              <div class="col-md-8">
                                <label class="form-label fw-bold">Replace Photo (Optional)</label>
                                <input type="file" name="gallery_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text small">Upload actual photograph taken at hub events.</div>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo (int)$g['display_order']; ?>">
                              </div>
                              <?php if (!empty($g['image_path']) && file_exists(__DIR__ . '/' . $g['image_path'])): ?>
                                <div class="col-12 d-flex align-items-center gap-3 bg-light p-2 rounded">
                                  <img src="<?php echo htmlspecialchars($g['image_path'], ENT_QUOTES, 'UTF-8'); ?>" width="75" height="50" class="rounded border shadow-sm" style="object-fit:cover;">
                                  <span class="small text-muted">Current photo attached: <code><?php echo htmlspecialchars($g['image_path'], ENT_QUOTES, 'UTF-8'); ?></code></span>
                                </div>
                              <?php endif; ?>
                              <div class="col-12">
                                <label class="form-label fw-bold">Description / Caption</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($g['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                              </div>
                            </div>
                          </div>
                          <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_gallery" class="btn btn-warning fw-bold px-4">
                              <i class="fa-solid fa-save me-1"></i>Save Changes
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">No gallery items added yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 8: SYSTEM ADMINS TAB -->
    <div class="tab-pane fade" id="admins-pane" role="tabpanel">
      <div class="row g-4 mb-4">
        
        <!-- Left Column: Assign / Promote Hub Member & Create New Admin -->
        <div class="col-lg-5 col-xl-4">
          <!-- Card 1: Assign from Hub -->
          <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="rounded-circle bg-success-subtle p-2 text-success">
                <i class="fa-solid fa-crown fa-lg"></i>
              </div>
              <h5 class="fw-bold mb-0">Assign Admin from Hub</h5>
            </div>
            <p class="small text-muted mb-3">
              Promote an existing registered member (facilitator or student) from Kibiya Hub to full System Administrator.
            </p>

            <?php if (!empty($potentialAdmins)): ?>
              <form method="POST">
                <div class="mb-3">
                  <label class="form-label fw-bold small">Select Hub Member to Promote <span class="text-danger">*</span></label>
                  <select name="user_id" class="form-select" required>
                    <option value="">-- Choose Member from Hub --</option>
                    <?php 
                      $groupedFacs = array_filter($potentialAdmins, function($u) { return $u['role'] === 'facilitator'; });
                      $groupedStudents = array_filter($potentialAdmins, function($u) { return $u['role'] === 'student'; });
                    ?>
                    <?php if (!empty($groupedFacs)): ?>
                      <optgroup label="Facilitators / Instructors (<?php echo count($groupedFacs); ?>)">
                        <?php foreach ($groupedFacs as $u): ?>
                          <option value="<?php echo (int)$u['id']; ?>">
                            <?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?> &mdash; <?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($groupedStudents)): ?>
                      <optgroup label="Registered Students (<?php echo count($groupedStudents); ?>)">
                        <?php foreach ($groupedStudents as $u): ?>
                          <option value="<?php echo (int)$u['id']; ?>">
                            <?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?> &mdash; <?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                  </select>
                </div>

                <div class="alert alert-warning py-2 px-3 small border-0 bg-warning-subtle text-dark rounded-3 mb-3">
                  <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>
                  <strong>Access Warning:</strong> System Administrators have full unrestricted access to create/delete courses, modify student/facilitator records, and manage all hub settings.
                </div>

                <button type="submit" name="assign_existing_admin" class="btn btn-hub fw-bold w-100 py-2 shadow-sm" onclick="return confirm('Are you sure you want to promote this member to System Administrator?');">
                  <i class="fa-solid fa-user-shield me-2"></i>Assign as Administrator
                </button>
              </form>
            <?php else: ?>
              <div class="alert alert-light border small text-muted text-center py-3 mb-0">
                All registered hub members are already administrators.
              </div>
            <?php endif; ?>
          </div>

          <!-- Card 2: Create Brand-New Admin Account -->
          <div class="card border-0 shadow-sm p-4" style="border-radius: 16px; background: linear-gradient(135deg, #f0fdf4, #f8fafc);">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="rounded-circle bg-primary-subtle p-2 text-primary">
                <i class="fa-solid fa-user-plus fa-lg"></i>
              </div>
              <h5 class="fw-bold mb-0">Create New Admin Account</h5>
            </div>
            <p class="small text-muted mb-3">
              Register a brand-new administrator directly if the person does not have a hub account yet.
            </p>
            <button class="btn btn-dark fw-bold w-100 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#createNewAdminModal">
              <i class="fa-solid fa-user-plus me-2"></i>Create New Admin Account
            </button>
          </div>
        </div>

        <!-- Right Column: Active System Administrators Table -->
        <div class="col-lg-7 col-xl-8">
          <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div>
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-shield-halved me-2 text-hub"></i>Current System Administrators</h5>
                <small class="text-muted">Users with full administrative rights over the Kibiya LMS Platform.</small>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input type="text" id="adminSearchInput" class="form-control form-control-sm" placeholder="Filter admins..." style="width: 200px;" onkeyup="filterAdminAdmins()">
                <button class="btn btn-hub btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createNewAdminModal">
                  <i class="fa-solid fa-user-plus me-1"></i>New Admin
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle" id="adminsAdminTable">
                <thead class="table-light">
                  <tr>
                    <th style="width: 45px;">#</th>
                    <th>Administrator</th>
                    <th>Email &amp; Contact</th>
                    <th>Date Added</th>
                    <th>Privilege Status</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($adminsList)): ?>
                    <?php foreach ($adminsList as $adm): ?>
                      <?php 
                        $isSelf = ((int)$adm['id'] === (int)($_SESSION['user_id'] ?? 0));
                      ?>
                      <tr>
                        <td>
                          <span class="badge bg-light text-dark border">#<?php echo (int)$adm['id']; ?></span>
                        </td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle <?php echo $isSelf ? 'bg-primary' : 'bg-success'; ?> text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width:40px;height:40px;font-size:1.1rem;">
                              <?php echo strtoupper(substr($adm['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                              <div class="fw-bold text-dark">
                                <?php echo htmlspecialchars($adm['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($isSelf): ?>
                                  <span class="badge bg-primary-subtle text-primary border ms-1 small">You</span>
                                <?php endif; ?>
                              </div>
                              <small class="text-muted">Courses Led: <?php echo (int)$adm['assigned_courses']; ?></small>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div><i class="fa-solid fa-envelope me-1 text-muted small"></i><?php echo htmlspecialchars($adm['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                          <?php if (!empty($adm['phone'])): ?>
                            <small class="text-muted"><i class="fa-solid fa-phone me-1 small"></i><?php echo htmlspecialchars($adm['phone'], ENT_QUOTES, 'UTF-8'); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <small class="text-muted"><?php echo !empty($adm['created_at']) ? date('M d, Y', strtotime($adm['created_at'])) : 'N/A'; ?></small>
                        </td>
                        <td>
                          <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                            <i class="fa-solid fa-shield-halved me-1"></i>Full Admin
                          </span>
                        </td>
                        <td class="text-end">
                          <?php if ($isSelf): ?>
                            <span class="badge bg-secondary-subtle text-secondary small">Active Session</span>
                          <?php else: ?>
                            <div class="btn-group btn-group-sm">
                              <button type="button" class="btn btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Change Role / Demote">
                                <i class="fa-solid fa-user-minus me-1"></i>Demote
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><h6 class="dropdown-header">Demote Admin Privileges</h6></li>
                                <li>
                                  <form method="POST" onsubmit="return confirm('Are you sure you want to demote this user to Facilitator? They will lose system administrative access.');">
                                    <input type="hidden" name="admin_id" value="<?php echo (int)$adm['id']; ?>">
                                    <input type="hidden" name="new_role" value="facilitator">
                                    <button type="submit" name="change_admin_role" class="dropdown-item text-warning">
                                      <i class="fa-solid fa-chalkboard-user me-2"></i>Change to Facilitator
                                    </button>
                                  </form>
                                </li>
                                <li>
                                  <form method="POST" onsubmit="return confirm('Are you sure you want to demote this user to Student? They will lose all administrative access.');">
                                    <input type="hidden" name="admin_id" value="<?php echo (int)$adm['id']; ?>">
                                    <input type="hidden" name="new_role" value="student">
                                    <button type="submit" name="change_admin_role" class="dropdown-item text-info">
                                      <i class="fa-solid fa-user-graduate me-2"></i>Change to Student
                                    </button>
                                  </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                  <form method="POST" onsubmit="return confirm('PERMANENT ACTION: Delete this administrator account from the hub completely?');">
                                    <input type="hidden" name="admin_id" value="<?php echo (int)$adm['id']; ?>">
                                    <button type="submit" name="delete_admin" class="dropdown-item text-danger">
                                      <i class="fa-solid fa-trash me-2"></i>Delete Account
                                    </button>
                                  </form>
                                </li>
                              </ul>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center py-4 text-muted">No administrators registered.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PANE 9: NEWS & OPPORTUNITIES MANAGEMENT -->
    <div class="tab-pane fade" id="news-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-bullhorn text-hub me-2"></i>News, Opportunities &amp; Announcements Desk</h5>
            <small class="text-muted">Total of <?php echo (int)$total_news; ?> posts (<?php echo (int)$published_news; ?> Published, <?php echo (int)$draft_news; ?> Drafts)</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <input type="text" id="newsSearchInput" class="form-control form-control-sm" placeholder="Filter news &amp; posts..." style="width: 220px;" onkeyup="filterAdminNews()">
            <a href="news.php" target="_blank" class="btn btn-outline-secondary btn-sm fw-semibold">
              <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Live Page
            </a>
            <button class="btn btn-hub btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addNewsModal">
              <i class="fa-solid fa-plus-circle me-1"></i>Add News / Opportunity
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="adminNewsTable">
            <thead class="table-light">
              <tr>
                <th style="width: 70px;">Cover</th>
                <th style="min-width: 220px;">Title &amp; Category</th>
                <th style="min-width: 200px;">Summary Excerpt</th>
                <th style="min-width: 150px;">Target &amp; Deadline</th>
                <th style="min-width: 130px;">Author &amp; Views</th>
                <th style="min-width: 110px;">Status</th>
                <th class="text-end" style="min-width: 130px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($newsAdminList)): ?>
                <?php foreach ($newsAdminList as $n): ?>
                  <?php
                    $cat = $n['category'] ?? 'Opportunities';
                    $hasImg = (!empty($n['image_path']) && file_exists(__DIR__ . '/' . $n['image_path']));
                    $isFeat = (bool)$n['is_featured'];
                    $isPub = ($n['status'] === 'published');
                    $deadline = !empty($n['deadline_date']) ? date('M d, Y', strtotime($n['deadline_date'])) : null;
                    $badgeCol = !empty($n['badge_color']) ? $n['badge_color'] : 'bg-success';
                    $ico = !empty($n['icon']) ? $n['icon'] : 'fa-newspaper';
                  ?>
                  <tr class="news-row">
                    <td>
                      <?php if ($hasImg): ?>
                        <img src="<?php echo htmlspecialchars($n['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Cover" class="rounded-3 shadow-sm" style="width: 54px; height: 54px; object-fit: cover;">
                      <?php else: ?>
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white bg-hub shadow-sm" style="width: 54px; height: 54px;">
                          <i class="fa-solid <?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?> fa-lg"></i>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-bold text-dark mb-1">
                        <?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($isFeat): ?>
                          <span class="badge bg-danger ms-1 small"><i class="fa-solid fa-star me-1 text-warning"></i>Featured</span>
                        <?php endif; ?>
                      </div>
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge <?php echo htmlspecialchars($badgeCol, ENT_QUOTES, 'UTF-8'); ?> py-1 px-2 small">
                          <i class="fa-solid <?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?php echo date('M d, Y', strtotime($n['created_at'])); ?></small>
                      </div>
                    </td>
                    <td>
                      <small class="text-muted d-block" style="line-height: 1.4; max-width: 280px;">
                        <?php
                          $snip = !empty($n['summary']) ? $n['summary'] : mb_strimwidth(strip_tags($n['content']), 0, 100, '...');
                          echo htmlspecialchars($snip, ENT_QUOTES, 'UTF-8');
                        ?>
                      </small>
                    </td>
                    <td>
                      <?php if (!empty($n['external_link'])): ?>
                        <a href="<?php echo htmlspecialchars($n['external_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none d-inline-block text-truncate mb-1" style="max-width: 140px;" title="<?php echo htmlspecialchars($n['external_link'], ENT_QUOTES, 'UTF-8'); ?>">
                          <i class="fa-solid fa-link me-1"></i><?php echo htmlspecialchars(!empty($n['link_label']) ? $n['link_label'] : 'External Link', ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php else: ?>
                        <span class="badge bg-light text-muted border mb-1">Internal Notice</span>
                      <?php endif; ?>
                      <div class="small">
                        <?php if ($deadline): ?>
                          <span class="text-warning-emphasis fw-semibold"><i class="fa-solid fa-hourglass-half me-1"></i><?php echo $deadline; ?></span>
                        <?php else: ?>
                          <span class="text-muted">No deadline</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <small class="fw-semibold text-dark d-block text-truncate" style="max-width: 130px;"><?php echo htmlspecialchars($n['author_name'] ?: 'Kibiya Editorial', ENT_QUOTES, 'UTF-8'); ?></small>
                      <small class="text-muted"><i class="fa-regular fa-eye me-1"></i><?php echo (int)$n['views_count']; ?> views</small>
                    </td>
                    <td>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="news_id" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" name="toggle_news_status" class="btn btn-sm <?php echo $isPub ? 'btn-success' : 'btn-secondary'; ?> rounded-pill py-1 px-3 fw-semibold shadow-sm" title="Click to toggle between Published and Draft">
                          <i class="fa-solid <?php echo $isPub ? 'fa-globe' : 'fa-file-lines'; ?> me-1"></i><?php echo ucfirst($n['status']); ?>
                        </button>
                      </form>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a href="news.php?post=<?php echo (int)$n['id']; ?>" target="_blank" class="btn btn-outline-info" title="View Public Post">
                          <i class="fa-solid fa-eye"></i>
                        </a>
                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editNewsModal<?php echo (int)$n['id']; ?>" title="Edit Post">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this post: &quot;<?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>&quot;? This action cannot be undone.');">
                          <input type="hidden" name="news_id" value="<?php echo (int)$n['id']; ?>">
                          <button type="submit" name="delete_news" class="btn btn-outline-danger" title="Delete Post">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL: EDIT NEWS POST -->
                  <div class="modal fade" id="editNewsModal<?php echo (int)$n['id']; ?>" tabindex="-1" aria-labelledby="editNewsModalLabel<?php echo (int)$n['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" enctype="multipart/form-data">
                          <input type="hidden" name="news_id" value="<?php echo (int)$n['id']; ?>">
                          <div class="modal-header bg-hub text-white">
                            <h5 class="modal-title fw-bold" id="editNewsModalLabel<?php echo (int)$n['id']; ?>">
                              <i class="fa-solid fa-pen-to-square me-2"></i>Edit News / Opportunity Post #<?php echo (int)$n['id']; ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-3">
                              <div class="col-md-8">
                                <label class="form-label fw-bold">Post Headline / Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                  <option value="Opportunities" <?php echo ($cat === 'Opportunities') ? 'selected' : ''; ?>>Opportunities &amp; Grants</option>
                                  <option value="Congratulations" <?php echo ($cat === 'Congratulations') ? 'selected' : ''; ?>>Congratulations &amp; Awards</option>
                                  <option value="Jobs" <?php echo ($cat === 'Jobs') ? 'selected' : ''; ?>>Jobs &amp; Careers</option>
                                  <option value="Announcements" <?php echo ($cat === 'Announcements') ? 'selected' : ''; ?>>Hub Announcements</option>
                                  <option value="Events" <?php echo ($cat === 'Events') ? 'selected' : ''; ?>>Workshops &amp; Events</option>
                                </select>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Author / Publisher Name</label>
                                <input type="text" name="author_name" class="form-control" value="<?php echo htmlspecialchars($n['author_name'] ?? 'Kibiya Hub Editorial', ENT_QUOTES, 'UTF-8'); ?>" maxlength="120">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Application / Event Deadline (Optional)</label>
                                <input type="date" name="deadline_date" class="form-control" value="<?php echo htmlspecialchars($n['deadline_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                              </div>
                              <div class="col-12">
                                <label class="form-label fw-bold">Short Excerpt / Summary (Optional)</label>
                                <textarea name="summary" class="form-control" rows="2" placeholder="Brief 1-2 sentence preview for cards..."><?php echo htmlspecialchars($n['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                              </div>
                              <div class="col-12">
                                <label class="form-label fw-bold">Full Post Content <span class="text-danger">*</span></label>
                                <textarea name="content" class="form-control" rows="6" required placeholder="Write the complete story, guidelines, requirements, instructions..."><?php echo htmlspecialchars($n['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                              </div>
                              <div class="col-md-7">
                                <label class="form-label fw-bold">Application URL / Source Link</label>
                                <input type="text" name="external_link" class="form-control" value="<?php echo htmlspecialchars($n['external_link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. contact.php or https://example.com/apply">
                              </div>
                              <div class="col-md-5">
                                <label class="form-label fw-bold">Button Link Label</label>
                                <input type="text" name="link_label" class="form-control" value="<?php echo htmlspecialchars($n['link_label'] ?? 'Learn More / Apply', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Apply Now, Register, Read More">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Badge Color Accent</label>
                                <select name="badge_color" class="form-select">
                                  <option value="bg-success" <?php echo ($n['badge_color'] === 'bg-success') ? 'selected' : ''; ?>>Green (Success / Opportunities)</option>
                                  <option value="bg-warning text-dark" <?php echo ($n['badge_color'] === 'bg-warning text-dark') ? 'selected' : ''; ?>>Amber Gold (Congratulations)</option>
                                  <option value="bg-info text-dark" <?php echo ($n['badge_color'] === 'bg-info text-dark') ? 'selected' : ''; ?>>Cyan Blue (Jobs / Careers)</option>
                                  <option value="bg-primary" <?php echo ($n['badge_color'] === 'bg-primary') ? 'selected' : ''; ?>>Indigo Blue (Announcements)</option>
                                  <option value="bg-danger" <?php echo ($n['badge_color'] === 'bg-danger') ? 'selected' : ''; ?>>Crimson Red (Urgent)</option>
                                  <option value="bg-dark" <?php echo ($n['badge_color'] === 'bg-dark') ? 'selected' : ''; ?>>Dark Slate</option>
                                </select>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label fw-bold">FontAwesome Icon</label>
                                <select name="icon" class="form-select">
                                  <option value="fa-lightbulb" <?php echo ($n['icon'] === 'fa-lightbulb') ? 'selected' : ''; ?>>fa-lightbulb (Opportunities / Ideas)</option>
                                  <option value="fa-award" <?php echo ($n['icon'] === 'fa-award') ? 'selected' : ''; ?>>fa-award (Congratulations / Honors)</option>
                                  <option value="fa-briefcase" <?php echo ($n['icon'] === 'fa-briefcase') ? 'selected' : ''; ?>>fa-briefcase (Jobs / Employment)</option>
                                  <option value="fa-bullhorn" <?php echo ($n['icon'] === 'fa-bullhorn') ? 'selected' : ''; ?>>fa-bullhorn (Announcements)</option>
                                  <option value="fa-calendar-days" <?php echo ($n['icon'] === 'fa-calendar-days') ? 'selected' : ''; ?>>fa-calendar-days (Events / Dates)</option>
                                  <option value="fa-newspaper" <?php echo ($n['icon'] === 'fa-newspaper') ? 'selected' : ''; ?>>fa-newspaper (General News)</option>
                                  <option value="fa-graduation-cap" <?php echo ($n['icon'] === 'fa-graduation-cap') ? 'selected' : ''; ?>>fa-graduation-cap (Graduation)</option>
                                </select>
                              </div>
                              <div class="col-md-8">
                                <label class="form-label fw-bold">Cover Photo (Optional)</label>
                                <input type="file" name="news_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <?php if ($hasImg): ?>
                                  <small class="text-muted mt-1 d-block">Current: <code><?php echo htmlspecialchars($n['image_path'], ENT_QUOTES, 'UTF-8'); ?></code> (uploading new replaces it)</small>
                                <?php endif; ?>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Publishing Status</label>
                                <select name="status" class="form-select">
                                  <option value="published" <?php echo ($n['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                  <option value="draft" <?php echo ($n['status'] === 'draft') ? 'selected' : ''; ?>>Draft / Hidden</option>
                                </select>
                              </div>
                              <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                  <input class="form-check-input" type="checkbox" role="switch" name="is_featured" id="feat_<?php echo (int)$n['id']; ?>" <?php echo $isFeat ? 'checked' : ''; ?>>
                                  <label class="form-check-label fw-bold" for="feat_<?php echo (int)$n['id']; ?>">
                                    Pin as Featured Story (prominently highlighted at top of News page)
                                  </label>
                                </div>
                              </div>
                            </div>
                          </div>
                          <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_news" class="btn btn-hub fw-bold px-4">
                              <i class="fa-solid fa-save me-1"></i>Save Changes
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-bullhorn fa-3x text-muted opacity-50 mb-3 d-block"></i>
                    <h6>No news posts found</h6>
                    <p class="small mb-3">Publish opportunities, graduate congratulations, job openings, or community announcements.</p>
                    <button class="btn btn-hub btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addNewsModal">
                      <i class="fa-solid fa-plus-circle me-1"></i>Publish First Post
                    </button>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANE 10: HEADER SLIDER MANAGEMENT (10s TRANSITION) -->
    <div class="tab-pane fade" id="slider-pane" role="tabpanel">
      <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <div class="d-flex align-items-center gap-2">
              <h5 class="fw-bold mb-0"><i class="fa-solid fa-sliders me-2 text-warning"></i>Homepage Header Slider</h5>
              <span class="badge bg-dark rounded-pill px-3 py-1 small">
                <i class="fa-solid fa-clock me-1 text-warning"></i>10s Auto-Scroll
              </span>
              <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 small">
                <?php echo (int)$active_slides_count; ?> Active Live
              </span>
            </div>
            <small class="text-muted">Create, edit, reorder, and manage hero carousel slides that display on the LMS homepage.</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <input type="text" id="sliderSearchInput" class="form-control form-control-sm" placeholder="Search slides..." style="width: 200px;" onkeyup="filterAdminSlides()">
            <a href="index.php" target="_blank" class="btn btn-outline-success btn-sm fw-bold">
              <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Preview Homepage
            </a>
            <button class="btn btn-hub btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addSlideModal">
              <i class="fa-solid fa-plus-circle me-1"></i>Add New Slide
            </button>
          </div>
        </div>

        <div class="alert alert-info py-2 px-3 small border-0 bg-info-subtle text-dark rounded-3 d-flex align-items-center gap-2 mb-3">
          <i class="fa-solid fa-circle-info text-info fa-lg"></i>
          <div>
            <strong>10-Second Slider Transition:</strong> Slides advance automatically every 10 seconds on the homepage. You can feature leadership quotes, courses, news opportunities, or tech bootcamps with custom gradients and photo covers.
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="adminSlidesTable">
            <thead class="table-light">
              <tr>
                <th style="width: 55px;">Order</th>
                <th style="width: 90px;">Cover / Theme</th>
                <th>Slide Title &amp; Highlight</th>
                <th>Category &amp; Card Type</th>
                <th>Call-to-Action Buttons</th>
                <th style="width: 110px;">Visibility</th>
                <th class="text-end" style="width: 130px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($slidesAdminList)): ?>
                <?php foreach ($slidesAdminList as $sl): ?>
                  <?php
                    $isAct = ((int)$sl['is_active'] === 1);
                    $hasImg = (!empty($sl['image_path']) && file_exists(__DIR__ . '/' . $sl['image_path']));
                    $bColor = !empty($sl['badge_color']) ? $sl['badge_color'] : 'bg-warning text-dark';
                    $bIcon = !empty($sl['badge_icon']) ? $sl['badge_icon'] : 'fa-rocket';
                    $cType = $sl['card_type'] ?? 'graphic';
                    $grad = !empty($sl['bg_gradient']) ? $sl['bg_gradient'] : 'linear-gradient(135deg, #0b6623, #084919)';
                  ?>
                  <tr class="slide-row">
                    <td>
                      <span class="badge bg-light text-dark border fw-bold">#<?php echo (int)$sl['display_order']; ?></span>
                    </td>
                    <td>
                      <?php if ($hasImg): ?>
                        <div class="position-relative" style="width: 74px; height: 50px;">
                          <img src="<?php echo htmlspecialchars($sl['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Cover" class="rounded-3 shadow-sm border" style="width: 74px; height: 50px; object-fit: cover;">
                        </div>
                      <?php else: ?>
                        <div class="rounded-3 shadow-sm border d-flex align-items-center justify-content-center text-white" style="width: 74px; height: 50px; background: <?php echo htmlspecialchars($grad, ENT_QUOTES, 'UTF-8'); ?>;">
                          <i class="fa-solid <?php echo htmlspecialchars($bIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-bold text-dark mb-1">
                        <?php echo htmlspecialchars($sl['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($sl['title_highlight'])): ?>
                          <span class="badge bg-warning text-dark ms-1 small"><?php echo htmlspecialchars($sl['title_highlight'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                      </div>
                      <small class="text-muted d-block text-truncate" style="max-width: 320px;">
                        <?php echo htmlspecialchars(mb_strimwidth($sl['subtitle'], 0, 95, '...'), ENT_QUOTES, 'UTF-8'); ?>
                      </small>
                      <?php if (!empty($sl['quote_author'])): ?>
                        <small class="text-hub fw-semibold d-block">
                          <i class="fa-solid fa-quote-left me-1 small"></i><?php echo htmlspecialchars($sl['quote_author'], ENT_QUOTES, 'UTF-8'); ?>
                          <?php if (!empty($sl['quote_author_role'])): ?>
                            <span class="text-muted fw-normal">(<?php echo htmlspecialchars($sl['quote_author_role'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                          <?php endif; ?>
                        </small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?php echo htmlspecialchars($bColor, ENT_QUOTES, 'UTF-8'); ?> py-1 px-2 small mb-1 d-inline-block">
                        <i class="fa-solid <?php echo htmlspecialchars($bIcon, ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php echo htmlspecialchars($sl['badge_text'], ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                      <div>
                        <span class="badge bg-light text-secondary border small">
                          <i class="fa-solid fa-layer-group me-1"></i><?php echo ucfirst(str_replace('_', ' ', $cType)); ?>
                        </span>
                        <span class="badge bg-light text-muted border small ms-1">
                          <?php echo ucfirst($sl['slide_type']); ?>
                        </span>
                      </div>
                    </td>
                    <td>
                      <?php if (!empty($sl['btn_primary_text'])): ?>
                        <div class="small fw-semibold text-dark mb-1">
                          <i class="fa-solid <?php echo htmlspecialchars($sl['btn_primary_icon'] ?: 'fa-arrow-right', ENT_QUOTES, 'UTF-8'); ?> text-warning me-1"></i>
                          <?php echo htmlspecialchars($sl['btn_primary_text'], ENT_QUOTES, 'UTF-8'); ?>
                          <code class="small text-muted">(<?php echo htmlspecialchars($sl['btn_primary_link'] ?: '#', ENT_QUOTES, 'UTF-8'); ?>)</code>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($sl['btn_secondary_text'])): ?>
                        <div class="small text-muted">
                          <i class="fa-solid <?php echo htmlspecialchars($sl['btn_secondary_icon'] ?: 'fa-info', ENT_QUOTES, 'UTF-8'); ?> me-1"></i>
                          <?php echo htmlspecialchars($sl['btn_secondary_text'], ENT_QUOTES, 'UTF-8'); ?>
                          <code class="small text-muted">(<?php echo htmlspecialchars($sl['btn_secondary_link'] ?: '#', ENT_QUOTES, 'UTF-8'); ?>)</code>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="slide_id" value="<?php echo (int)$sl['id']; ?>">
                        <button type="submit" name="toggle_slide_status" class="btn btn-sm <?php echo $isAct ? 'btn-success' : 'btn-secondary'; ?> rounded-pill py-1 px-3 fw-semibold shadow-sm" title="Click to toggle slide visibility on homepage">
                          <i class="fa-solid <?php echo $isAct ? 'fa-eye' : 'fa-eye-slash'; ?> me-1"></i><?php echo $isAct ? 'Active' : 'Hidden'; ?>
                        </button>
                      </form>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editSlideModal<?php echo (int)$sl['id']; ?>" title="Edit Slide">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this slide: &quot;<?php echo htmlspecialchars($sl['title'], ENT_QUOTES, 'UTF-8'); ?>&quot;? This cannot be undone.');">
                          <input type="hidden" name="slide_id" value="<?php echo (int)$sl['id']; ?>">
                          <button type="submit" name="delete_slide" class="btn btn-outline-danger" title="Delete Slide">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL: EDIT HEADER SLIDE -->
                  <div class="modal fade" id="editSlideModal<?php echo (int)$sl['id']; ?>" tabindex="-1" aria-labelledby="editSlideModalLabel<?php echo (int)$sl['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" enctype="multipart/form-data">
                          <input type="hidden" name="slide_id" value="<?php echo (int)$sl['id']; ?>">
                          <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title fw-bold" id="editSlideModalLabel<?php echo (int)$sl['id']; ?>">
                              <i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Edit Header Slide #<?php echo (int)$sl['id']; ?> (<?php echo htmlspecialchars($sl['title'], ENT_QUOTES, 'UTF-8'); ?>)
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body p-4">
                            <div class="row g-3">
                              
                              <div class="col-md-8">
                                <label class="form-label fw-bold">Primary Slide Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($sl['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Highlighted Gold Phrase</label>
                                <input type="text" name="title_highlight" class="form-control" value="<?php echo htmlspecialchars($sl['title_highlight'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. at Kibiya Hub">
                              </div>

                              <div class="col-12">
                                <label class="form-label fw-bold">Subtitle / Quotation Text <span class="text-danger">*</span></label>
                                <textarea name="subtitle" class="form-control" rows="3" required placeholder="Enter the text or leadership quote displayed on this slide..."><?php echo htmlspecialchars($sl['subtitle'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Slide Category / Role</label>
                                <select name="slide_type" class="form-select">
                                  <option value="welcome" <?php echo ($sl['slide_type'] === 'welcome') ? 'selected' : ''; ?>>Welcome &amp; Courses</option>
                                  <option value="ceo" <?php echo ($sl['slide_type'] === 'ceo') ? 'selected' : ''; ?>>CEO &amp; Leadership</option>
                                  <option value="news" <?php echo ($sl['slide_type'] === 'news') ? 'selected' : ''; ?>>News &amp; Opportunities</option>
                                  <option value="event" <?php echo ($sl['slide_type'] === 'event') ? 'selected' : ''; ?>>Events &amp; Bootcamps</option>
                                  <option value="custom" <?php echo ($sl['slide_type'] === 'custom') ? 'selected' : ''; ?>>Custom Announcement</option>
                                </select>
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Top Pill Badge Text</label>
                                <input type="text" name="badge_text" class="form-control" value="<?php echo htmlspecialchars($sl['badge_text'] ?? 'Kibiya Tech Hub', ENT_QUOTES, 'UTF-8'); ?>">
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Badge Icon</label>
                                <select name="badge_icon" class="form-select">
                                  <option value="fa-rocket" <?php echo ($bIcon === 'fa-rocket') ? 'selected' : ''; ?>>fa-rocket (Innovation / Launch)</option>
                                  <option value="fa-crown" <?php echo ($bIcon === 'fa-crown') ? 'selected' : ''; ?>>fa-crown (Leadership / CEO)</option>
                                  <option value="fa-bullhorn" <?php echo ($bIcon === 'fa-bullhorn') ? 'selected' : ''; ?>>fa-bullhorn (News / Alerts)</option>
                                  <option value="fa-calendar-star" <?php echo ($bIcon === 'fa-calendar-star') ? 'selected' : ''; ?>>fa-calendar-star (Events)</option>
                                  <option value="fa-award" <?php echo ($bIcon === 'fa-award') ? 'selected' : ''; ?>>fa-award (Honors / Recognition)</option>
                                  <option value="fa-graduation-cap" <?php echo ($bIcon === 'fa-graduation-cap') ? 'selected' : ''; ?>>fa-graduation-cap (Graduation)</option>
                                  <option value="fa-laptop-code" <?php echo ($bIcon === 'fa-laptop-code') ? 'selected' : ''; ?>>fa-laptop-code (Coding / Tech)</option>
                                  <option value="fa-lightbulb" <?php echo ($bIcon === 'fa-lightbulb') ? 'selected' : ''; ?>>fa-lightbulb (Opportunities)</option>
                                </select>
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Badge Color Preset</label>
                                <select name="badge_color" class="form-select">
                                  <option value="bg-warning text-dark" <?php echo ($bColor === 'bg-warning text-dark') ? 'selected' : ''; ?>>Amber Gold (bg-warning text-dark)</option>
                                  <option value="bg-success text-white" <?php echo ($bColor === 'bg-success text-white' || $bColor === 'bg-success') ? 'selected' : ''; ?>>Emerald Green (bg-success)</option>
                                  <option value="bg-info text-dark" <?php echo ($bColor === 'bg-info text-dark') ? 'selected' : ''; ?>>Cyan Blue (bg-info text-dark)</option>
                                  <option value="bg-primary text-white" <?php echo ($bColor === 'bg-primary text-white' || $bColor === 'bg-primary') ? 'selected' : ''; ?>>Royal Blue (bg-primary)</option>
                                  <option value="bg-danger text-white" <?php echo ($bColor === 'bg-danger text-white' || $bColor === 'bg-danger') ? 'selected' : ''; ?>>Crimson Red (bg-danger)</option>
                                  <option value="bg-dark text-white" <?php echo ($bColor === 'bg-dark text-white' || $bColor === 'bg-dark') ? 'selected' : ''; ?>>Dark Slate (bg-dark)</option>
                                </select>
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Quote Author (Optional)</label>
                                <input type="text" name="quote_author" class="form-control" value="<?php echo htmlspecialchars($sl['quote_author'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Musa Abdullahi Kibiya">
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Quote Author Role</label>
                                <input type="text" name="quote_author_role" class="form-control" value="<?php echo htmlspecialchars($sl['quote_author_role'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Executive Director">
                              </div>

                              <!-- Buttons -->
                              <div class="col-md-4">
                                <label class="form-label fw-bold">Primary Button Text</label>
                                <input type="text" name="btn_primary_text" class="form-control" value="<?php echo htmlspecialchars($sl['btn_primary_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Browse Courses">
                              </div>
                              <div class="col-md-5">
                                <label class="form-label fw-bold">Primary Button Link</label>
                                <input type="text" name="btn_primary_link" class="form-control" value="<?php echo htmlspecialchars($sl['btn_primary_link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. courses.php or https://...">
                              </div>
                              <div class="col-md-3">
                                <label class="form-label fw-bold">Primary Icon</label>
                                <input type="text" name="btn_primary_icon" class="form-control" value="<?php echo htmlspecialchars($sl['btn_primary_icon'] ?? 'fa-arrow-right', ENT_QUOTES, 'UTF-8'); ?>" placeholder="fa-book-open">
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Secondary Button Text</label>
                                <input type="text" name="btn_secondary_text" class="form-control" value="<?php echo htmlspecialchars($sl['btn_secondary_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Join Free Account">
                              </div>
                              <div class="col-md-5">
                                <label class="form-label fw-bold">Secondary Button Link</label>
                                <input type="text" name="btn_secondary_link" class="form-control" value="<?php echo htmlspecialchars($sl['btn_secondary_link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. register.php">
                              </div>
                              <div class="col-md-3">
                                <label class="form-label fw-bold">Secondary Icon</label>
                                <input type="text" name="btn_secondary_icon" class="form-control" value="<?php echo htmlspecialchars($sl['btn_secondary_icon'] ?? 'fa-user-plus', ENT_QUOTES, 'UTF-8'); ?>" placeholder="fa-user-plus">
                              </div>

                              <!-- Right Card Visual Type -->
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Right Column Card Style</label>
                                <select name="card_type" class="form-select">
                                  <option value="graphic" <?php echo ($cType === 'graphic') ? 'selected' : ''; ?>>Tech Skills Box (AI, Web, Cyber Badges)</option>
                                  <option value="ceo_profile" <?php echo ($cType === 'ceo_profile') ? 'selected' : ''; ?>>CEO &amp; Leadership Profile Card</option>
                                  <option value="news_highlight" <?php echo ($cType === 'news_highlight') ? 'selected' : ''; ?>>Featured News / Verified Opportunity Card</option>
                                  <option value="event_badge" <?php echo ($cType === 'event_badge') ? 'selected' : ''; ?>>Upcoming Event &amp; Bootcamp Card</option>
                                  <option value="custom_image" <?php echo ($cType === 'custom_image') ? 'selected' : ''; ?>>Custom Uploaded Cover Photo Card</option>
                                </select>
                              </div>

                              <!-- Background Gradient Preset -->
                              <div class="col-md-6">
                                <label class="form-label fw-bold">Background Theme Gradient</label>
                                <select name="bg_gradient" class="form-select">
                                  <option value="linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)" <?php echo ($grad === 'linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)') ? 'selected' : ''; ?>>Hub Forest Green (Emerald Deep)</option>
                                  <option value="linear-gradient(135deg, #063914 0%, #094a1b 55%, #15803d 100%)" <?php echo ($grad === 'linear-gradient(135deg, #063914 0%, #094a1b 55%, #15803d 100%)') ? 'selected' : ''; ?>>Emerald Leadership (CEO Style)</option>
                                  <option value="linear-gradient(135deg, #092e15 0%, #0b5722 55%, #0284c7 100%)" <?php echo ($grad === 'linear-gradient(135deg, #092e15 0%, #0b5722 55%, #0284c7 100%)') ? 'selected' : ''; ?>>Deep Teal &amp; Ocean (News Style)</option>
                                  <option value="linear-gradient(135deg, #0a2540 0%, #0e3d68 55%, #0b6623 100%)" <?php echo ($grad === 'linear-gradient(135deg, #0a2540 0%, #0e3d68 55%, #0b6623 100%)') ? 'selected' : ''; ?>>Midnight Navy &amp; Gold (Event Style)</option>
                                  <option value="linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%)" <?php echo ($grad === 'linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%)') ? 'selected' : ''; ?>>Royal Indigo Tech</option>
                                  <option value="linear-gradient(135deg, #451a03 0%, #78350f 60%, #b45309 100%)" <?php echo ($grad === 'linear-gradient(135deg, #451a03 0%, #78350f 60%, #b45309 100%)') ? 'selected' : ''; ?>>Warm Amber &amp; Gold</option>
                                </select>
                              </div>

                              <div class="col-md-8">
                                <label class="form-label fw-bold">Replace Cover Image (Optional)</label>
                                <input type="file" name="slide_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text small">Max 10 MB. High-resolution photo shown in the slide card.</div>
                              </div>

                              <div class="col-md-4">
                                <label class="form-label fw-bold">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo (int)$sl['display_order']; ?>">
                              </div>

                              <?php if ($hasImg): ?>
                                <div class="col-12 d-flex align-items-center gap-3 bg-light p-3 rounded-3 border">
                                  <img src="<?php echo htmlspecialchars($sl['image_path'], ENT_QUOTES, 'UTF-8'); ?>" width="90" height="60" class="rounded border shadow-sm" style="object-fit:cover;">
                                  <div>
                                    <div class="small fw-semibold text-dark">Current image attached: <code><?php echo htmlspecialchars($sl['image_path'], ENT_QUOTES, 'UTF-8'); ?></code></div>
                                    <div class="form-check mt-1">
                                      <input class="form-check-input" type="checkbox" name="remove_image" id="rem_img_<?php echo (int)$sl['id']; ?>">
                                      <label class="form-check-label text-danger small fw-bold" for="rem_img_<?php echo (int)$sl['id']; ?>">
                                        <i class="fa-solid fa-trash me-1"></i>Remove current photo (revert to card preset)
                                      </label>
                                    </div>
                                  </div>
                                </div>
                              <?php endif; ?>

                              <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                  <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="active_<?php echo (int)$sl['id']; ?>" <?php echo $isAct ? 'checked' : ''; ?>>
                                  <label class="form-check-label fw-bold" for="active_<?php echo (int)$sl['id']; ?>">
                                    Live on Homepage (Slide active and transitions in 10s rotation)
                                  </label>
                                </div>
                              </div>

                            </div>
                          </div>
                          <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_slide" class="btn btn-warning fw-bold px-4">
                              <i class="fa-solid fa-save me-1"></i>Save Slide Changes
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-sliders fa-3x text-muted opacity-50 mb-3 d-block"></i>
                    <h6>No header slides configured</h6>
                    <p class="small mb-3">Add customized slides to illuminate your homepage with announcements, opportunities, and leadership vision.</p>
                    <button class="btn btn-hub btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addSlideModal">
                      <i class="fa-solid fa-plus-circle me-1"></i>Add First Slide
                    </button>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<!-- ================= MODAL: GENERAL ASSIGN FACILITATOR ================= -->
<div class="modal fade" id="assignCourseGeneralModal" tabindex="-1" aria-labelledby="assignCourseGeneralModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title fw-bold" id="assignCourseGeneralModalLabel">
            <i class="fa-solid fa-user-check me-2"></i>Assign Course to Facilitator
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Select Course <span class="text-danger">*</span></label>
            <select name="course_id" class="form-select" required>
              <option value="">-- Choose Course --</option>
              <?php foreach ($allCourses as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>">
                  #<?php echo (int)$c['id']; ?> - <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?> (Current: <?php echo htmlspecialchars($c['facilitator_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Select Facilitator / Instructor <span class="text-danger">*</span></label>
            <select name="facilitator_id" class="form-select" required>
              <option value="">-- Choose Facilitator --</option>
              <?php foreach ($facilitatorsList as $f): ?>
                <option value="<?php echo (int)$f['id']; ?>">
                  <?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo ucfirst($f['role']); ?> - <?php echo htmlspecialchars($f['email'], ENT_QUOTES, 'UTF-8'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text small text-muted">
              The assigned instructor will be displayed on the course page and will have full permissions to add lessons/modules in their Facilitator Portal.
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="assign_facilitator" class="btn btn-info text-white fw-bold px-4">
            <i class="fa-solid fa-check me-1"></i>Assign Facilitator
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: ADD NEW COURSE ================= -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-hub text-white">
          <h5 class="modal-title fw-bold" id="addCourseModalLabel">
            <i class="fa-solid fa-plus-circle me-2"></i>Add New Course
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Course Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="e.g. AI Automation Engineering" required maxlength="150">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
              <select name="category" id="courseCategorySelect" class="form-select" onchange="toggleCustomCategory(this)">
                <?php foreach ($standardCategories as $scat): ?>
                  <option value="<?php echo htmlspecialchars($scat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($scat, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
                <option value="custom">+ Other / Custom Category</option>
              </select>
            </div>

            <div class="col-md-6" id="customCategoryDiv" style="display: none;">
              <label class="form-label fw-bold">Custom Category Name</label>
              <input type="text" name="custom_category" class="form-control" placeholder="Enter custom category name" maxlength="50">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Assign Instructor / Facilitator</label>
              <select name="facilitator_id" class="form-select">
                <?php foreach ($facilitatorsList as $f): ?>
                  <option value="<?php echo (int)$f['id']; ?>">
                    <?php echo htmlspecialchars($f['full_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo ucfirst($f['role']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Course Description &amp; Objectives <span class="text-danger">*</span></label>
              <textarea name="description" class="form-control" rows="3" placeholder="Explain what students will learn, career opportunities, and requirements..." required></textarea>
            </div>

            <!-- Optional Initial Module -->
            <div class="col-12">
              <div class="border rounded p-3 bg-light">
                <h6 class="fw-bold mb-2 text-hub"><i class="fa-solid fa-paperclip me-1"></i>Attach Initial Course Material (Optional)</h6>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label small text-muted">Module / Material Title</label>
                    <input type="text" name="initial_module_title" class="form-control form-control-sm" placeholder="e.g. Module 1: Introduction &amp; Syllabus" maxlength="150">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted">Attach Resource File (PDF, DOCX, ZIP, TXT; max 25MB)</label>
                    <input type="file" name="initial_module_file" class="form-control form-control-sm" accept=".pdf,.docx,.doc,.zip,.rar,.txt,.ppt,.pptx,.xls,.xlsx,.mp4">
                  </div>
                  <div class="col-12">
                    <label class="form-label small text-muted">Module Notes / Curriculum Overview</label>
                    <textarea name="initial_module_content" class="form-control form-control-sm" rows="2" placeholder="Brief outline of lesson topics or syllabus notes..."></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_course" class="btn btn-hub fw-bold px-4">
            <i class="fa-solid fa-save me-1"></i>Publish Course
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: UPLOAD COURSE MATERIAL / MODULE ================= -->
<div class="modal fade" id="uploadMaterialModal" tabindex="-1" aria-labelledby="uploadMaterialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold" id="uploadMaterialModalLabel">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Learning Material / Module
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-bold">Select Target Course <span class="text-danger">*</span></label>
              <select name="course_id" id="uploadTargetCourseSelect" class="form-select" required>
                <option value="">-- Choose Course --</option>
                <?php foreach ($allCourses as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>">
                    #<?php echo (int)$c['id']; ?> - <?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($c['category'], ENT_QUOTES, 'UTF-8'); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Module / Material Title <span class="text-danger">*</span></label>
              <input type="text" name="module_title" class="form-control" placeholder="e.g. Module 2: Advanced Practical Lab Guide" required maxlength="150">
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Lesson Notes / Text Content</label>
              <textarea name="content" class="form-control" rows="3" placeholder="Provide lesson summary, instructions, reading pointers, or curriculum breakdown..."></textarea>
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Attach Learning File / Document</label>
              <input type="file" name="file" class="form-control" accept=".pdf,.docx,.doc,.zip,.rar,.txt,.ppt,.pptx,.xls,.xlsx,.mp4">
              <div class="form-text small text-muted">
                <i class="fa-solid fa-circle-info me-1"></i>Supported formats: PDF, Word (DOCX/DOC), PowerPoint (PPTX), Excel, ZIP archives, TXT, MP4 video (Max 25 MB).
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="upload_module" class="btn btn-warning fw-bold px-4">
            <i class="fa-solid fa-upload me-1"></i>Upload Material
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setUploadCourseId(courseId) {
  const select = document.getElementById('uploadTargetCourseSelect');
  if (select) { select.value = courseId; }
}

function toggleCustomCategory(select) {
  const div = document.getElementById('customCategoryDiv');
  div.style.display = select.value === 'custom' ? 'block' : 'none';
}

function filterAdminCourses() {
  const filter = document.getElementById('courseSearchInput').value.toLowerCase();
  const tr = document.getElementById('coursesAdminTable').getElementsByTagName('tr');
  for (let i = 1; i < tr.length; i++) {
    tr[i].style.display = tr[i].innerText.toLowerCase().includes(filter) ? '' : 'none';
  }
}

function filterAdminOfficers() {
  const filter = document.getElementById('officerSearchInput').value.toLowerCase();
  const tr = document.getElementById('officersAdminTable').getElementsByTagName('tr');
  for (let i = 1; i < tr.length; i++) {
    tr[i].style.display = tr[i].innerText.toLowerCase().includes(filter) ? '' : 'none';
  }
}

function filterAdminPartners() {
  const filter = document.getElementById('partnerSearchInput').value.toLowerCase();
  const tr = document.getElementById('partnersAdminTable').getElementsByTagName('tr');
  for (let i = 1; i < tr.length; i++) {
    tr[i].style.display = tr[i].innerText.toLowerCase().includes(filter) ? '' : 'none';
  }
}

function filterAdminGallery() {
  const filter = document.getElementById('gallerySearchInput').value.toLowerCase();
  const tr = document.getElementById('galleryAdminTable').getElementsByTagName('tr');
  for (let i = 1; i < tr.length; i++) {
    tr[i].style.display = tr[i].innerText.toLowerCase().includes(filter) ? '' : 'none';
  }
}

function filterAdminAdmins() {
  const filter = document.getElementById('adminSearchInput').value.toLowerCase();
  const tr = document.getElementById('adminsAdminTable').getElementsByTagName('tr');
  for (let i = 1; i < tr.length; i++) {
    tr[i].style.display = tr[i].innerText.toLowerCase().includes(filter) ? '' : 'none';
  }
}
</script>

<!-- ================= MODAL: ADD MANAGEMENT OFFICER ================= -->
<div class="modal fade" id="addOfficerModal" tabindex="-1" aria-labelledby="addOfficerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title fw-bold" id="addOfficerModalLabel">
            <i class="fa-solid fa-user-plus me-2"></i>Add New Management Officer
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Musa Abdullahi Kibiya" required maxlength="150">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-bold">Position / Role Title <span class="text-danger">*</span></label>
              <input type="text" name="role_title" class="form-control" placeholder="e.g. Executive Director" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Badge Color Style</label>
              <select name="badge_color" class="form-select">
                <option value="bg-success">Green (bg-success)</option>
                <option value="bg-warning text-dark">Yellow (bg-warning text-dark)</option>
                <option value="bg-info text-dark">Sky Blue (bg-info text-dark)</option>
                <option value="bg-primary">Blue (bg-primary)</option>
                <option value="bg-danger">Red (bg-danger)</option>
                <option value="bg-dark text-white">Dark (bg-dark)</option>
                <option value="bg-secondary">Grey (bg-secondary)</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Phone (Optional)</label>
              <input type="text" name="phone" class="form-control" placeholder="+234...">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Email (Optional)</label>
              <input type="email" name="email" class="form-control" placeholder="officer@kibiyahub.ng">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Profile Photo (Optional)</label>
              <input type="file" name="officer_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text small text-muted">Max 10 MB. If not uploaded, an initial letter avatar is shown.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Bio &amp; Key Responsibilities</label>
              <textarea name="bio" class="form-control" rows="3" placeholder="Describe their responsibilities and role in the hub..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_officer" class="btn btn-success fw-bold px-4">
            <i class="fa-solid fa-save me-1"></i>Add Officer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: ADD PARTNER ================= -->
<div class="modal fade" id="addPartnerModal" tabindex="-1" aria-labelledby="addPartnerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title fw-bold" id="addPartnerModalLabel">
            <i class="fa-solid fa-handshake me-2"></i>Add New Partner / Stakeholder
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label fw-bold">Partner / Organisation Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Kano State Government" required maxlength="150">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-bold">Partnership Category <span class="text-danger">*</span></label>
              <input type="text" name="category" class="form-control" placeholder="e.g. Government Partner, Tech Development" required maxlength="100">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Badge Color</label>
              <select name="badge_color" class="form-select">
                <option value="bg-success">Green (bg-success)</option>
                <option value="bg-warning text-dark">Yellow (bg-warning)</option>
                <option value="bg-info text-white">Sky Blue (bg-info)</option>
                <option value="bg-primary">Blue (bg-primary)</option>
                <option value="bg-dark">Dark (bg-dark)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">FontAwesome Icon</label>
              <select name="icon" class="form-select">
                <option value="fa-landmark">fa-landmark (Government)</option>
                <option value="fa-globe">fa-globe (Global / Tech)</option>
                <option value="fa-users">fa-users (Community)</option>
                <option value="fa-handshake" selected>fa-handshake (Partnership)</option>
                <option value="fa-building">fa-building (Corporate)</option>
                <option value="fa-graduation-cap">fa-graduation-cap (Academic)</option>
                <option value="fa-award">fa-award (Sponsor)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Icon Circle Background</label>
              <input type="color" name="icon_bg" class="form-control form-control-color w-100" value="#0b6623">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-bold">Website URL (Optional)</label>
              <input type="url" name="website_url" class="form-control" placeholder="https://example.gov.ng">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control" value="0" min="0">
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Logo Image (Optional)</label>
              <input type="file" name="partner_logo" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml">
              <div class="form-text small text-muted">Max 10 MB. If a logo is uploaded, it replaces the default icon circle display.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Description / Support Overview</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Describe the partnership and what support they provide..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_partner" class="btn btn-info text-white fw-bold px-4">
            <i class="fa-solid fa-save me-1"></i>Add Partner
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: ADD GALLERY ITEM ================= -->
<div class="modal fade" id="addGalleryModal" tabindex="-1" aria-labelledby="addGalleryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold" id="addGalleryModalLabel">
            <i class="fa-solid fa-image me-2"></i>Add New Gallery Item
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-bold">Title / Headline <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Web Development Training Session" required maxlength="150">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Category</label>
              <select name="category" class="form-select">
                <option value="Training">Training</option>
                <option value="Workshop">Workshop</option>
                <option value="Events">Events</option>
                <option value="Ceremony">Ceremony</option>
                <option value="Outreach">Outreach</option>
                <option value="Lab">Lab</option>
                <option value="Competition">Competition / Hackathon</option>
                <option value="Activities" selected>Activities</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">FontAwesome Icon (Banner Fallback)</label>
              <select name="icon" class="form-select">
                <option value="fa-laptop-code">fa-laptop-code (Coding)</option>
                <option value="fa-robot">fa-robot (Robotics / AI)</option>
                <option value="fa-graduation-cap">fa-graduation-cap (Graduation)</option>
                <option value="fa-chalkboard-user">fa-chalkboard-user (Trainer)</option>
                <option value="fa-people-group">fa-people-group (Outreach)</option>
                <option value="fa-lightbulb">fa-lightbulb (Innovation)</option>
                <option value="fa-desktop">fa-desktop (Computer Lab)</option>
                <option value="fa-users">fa-users (Group Study)</option>
                <option value="fa-trophy">fa-trophy (Hackathon)</option>
                <option value="fa-camera" selected>fa-camera (General Photo)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Banner Gradient Preset</label>
              <select name="gradient_bg" class="form-select">
                <option value="linear-gradient(135deg, #0b6623, #0d7a2b)" selected>Hub Emerald Green</option>
                <option value="linear-gradient(135deg, #1a237e, #283593)">Deep Indigo Navy</option>
                <option value="linear-gradient(135deg, #e65100, #f57c00)">Vibrant Orange</option>
                <option value="linear-gradient(135deg, #4a148c, #6a1b9a)">Royal Purple</option>
                <option value="linear-gradient(135deg, #01579b, #0288d1)">Sky Blue</option>
                <option value="linear-gradient(135deg, #bf360c, #e64a19)">Coral Red</option>
                <option value="linear-gradient(135deg, #004d40, #00695c)">Teal Forest</option>
                <option value="linear-gradient(135deg, #827717, #9e9d24)">Gold Olive</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label fw-bold">Upload Photo (Optional)</label>
              <input type="file" name="gallery_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text small text-muted">Max 10 MB. If uploaded, photo displays instead of the gradient icon banner.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control" value="0" min="0">
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Description / Caption</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Describe the event, participants, and what was learned or celebrated..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_gallery" class="btn btn-warning fw-bold px-4">
            <i class="fa-solid fa-save me-1"></i>Add Gallery Item
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: CREATE NEW ADMIN ================= -->
<div class="modal fade" id="createNewAdminModal" tabindex="-1" aria-labelledby="createNewAdminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold" id="createNewAdminModalLabel">
            <i class="fa-solid fa-user-shield me-2 text-warning"></i>Create New System Administrator
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Ibrahim Sani Kibiya" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Official Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="admin@kibiyahub.ng" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Phone Number (Optional)</label>
            <input type="text" name="phone" class="form-control" placeholder="+234..." maxlength="20">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Password (min 8 characters) <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Enter secure password" required minlength="8" autocomplete="new-password">
            <div class="form-text small text-muted">Must be at least 8 characters. Share these login credentials securely.</div>
          </div>
          <div class="alert alert-warning py-2 small mb-0 border-0 bg-warning-subtle text-dark rounded-3">
            <i class="fa-solid fa-shield-halved me-1 text-warning"></i>
            This account will be created with immediate <strong>Administrator</strong> privileges across the LMS.
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="create_new_admin" class="btn btn-success fw-bold px-4">
            <i class="fa-solid fa-check me-1"></i>Create Administrator
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: ADD NEWS / OPPORTUNITY ================= -->
<div class="modal fade" id="addNewsModal" tabindex="-1" aria-labelledby="addNewsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-hub text-white">
          <h5 class="modal-title fw-bold" id="addNewsModalLabel">
            <i class="fa-solid fa-bullhorn me-2"></i>Publish News, Opportunity or Announcement
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-bold">Post Headline / Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Kano Tech Innovation Fellowship 2026 – Apply Now" required maxlength="255">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
              <select name="category" class="form-select" required>
                <option value="Opportunities" selected>Opportunities &amp; Grants</option>
                <option value="Congratulations">Congratulations &amp; Awards</option>
                <option value="Jobs">Jobs &amp; Careers</option>
                <option value="Announcements">Hub Announcements</option>
                <option value="Events">Workshops &amp; Events</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Author / Publisher Name</label>
              <input type="text" name="author_name" class="form-control" value="Kibiya Hub Editorial" maxlength="120">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Application / Event Deadline (Optional)</label>
              <input type="date" name="deadline_date" class="form-control">
              <div class="form-text small text-muted">Useful for job applications or grant deadlines.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Short Excerpt / Summary (Optional)</label>
              <textarea name="summary" class="form-control" rows="2" placeholder="Brief 1-2 sentence preview for cards and notifications..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">Full Post Content <span class="text-danger">*</span></label>
              <textarea name="content" class="form-control" rows="6" required placeholder="Write the full post, guidelines, requirements, congratulatory notes, or job descriptions..."></textarea>
            </div>
            <div class="col-md-7">
              <label class="form-label fw-bold">Application URL / Target Link (Optional)</label>
              <input type="text" name="external_link" class="form-control" placeholder="e.g. contact.php or https://example.com/apply">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-bold">Button Link Label</label>
              <input type="text" name="link_label" class="form-control" value="Apply / Learn More" placeholder="e.g. Apply Now, View Details, Register">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Badge Color Preset</label>
              <select name="badge_color" class="form-select">
                <option value="bg-success" selected>Green (Success / Opportunities)</option>
                <option value="bg-warning text-dark">Amber Gold (Congratulations)</option>
                <option value="bg-info text-dark">Cyan Blue (Jobs / Careers)</option>
                <option value="bg-primary">Indigo Blue (Announcements)</option>
                <option value="bg-danger">Crimson Red (Urgent / Alerts)</option>
                <option value="bg-dark">Dark Slate</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">FontAwesome Icon</label>
              <select name="icon" class="form-select">
                <option value="fa-lightbulb" selected>fa-lightbulb (Opportunities / Ideas)</option>
                <option value="fa-award">fa-award (Congratulations / Honors)</option>
                <option value="fa-briefcase">fa-briefcase (Jobs / Employment)</option>
                <option value="fa-bullhorn">fa-bullhorn (Announcements)</option>
                <option value="fa-calendar-days">fa-calendar-days (Events / Dates)</option>
                <option value="fa-newspaper">fa-newspaper (General News)</option>
                <option value="fa-graduation-cap">fa-graduation-cap (Graduation)</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label fw-bold">Cover Photo (Optional)</label>
              <input type="file" name="news_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text small text-muted">Max 10 MB. If no photo is selected, dynamic gradient icon is shown.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Publishing Status</label>
              <select name="status" class="form-select">
                <option value="published" selected>Published</option>
                <option value="draft">Draft / Hidden</option>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" role="switch" name="is_featured" id="feat_new">
                <label class="form-check-label fw-bold" for="feat_new">
                  Pin as Featured Story (prominently highlighted on News page)
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_news" class="btn btn-hub fw-bold px-4">
            <i class="fa-solid fa-paper-plane me-1"></i>Publish Post
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL: ADD HEADER SLIDE (10s HERO) ================= -->
<div class="modal fade" id="addSlideModal" tabindex="-1" aria-labelledby="addSlideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold" id="addSlideModalLabel">
            <i class="fa-solid fa-sliders me-2 text-warning"></i>Add New Header Slide (10s Transition)
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="alert alert-warning py-2 px-3 small border-0 bg-warning-subtle text-dark rounded-3 d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-clock me-1 text-warning"></i>
            <span>This slide will be added to the homepage header slider and will display in the <strong>10-second</strong> auto-scroll rotation.</span>
          </div>

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-bold">Primary Slide Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Empowering Kano State Youth with Tech" required maxlength="255">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Highlighted Gold Phrase</label>
              <input type="text" name="title_highlight" class="form-control" placeholder="e.g. at Kibiya Hub">
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Subtitle / Quotation Text <span class="text-danger">*</span></label>
              <textarea name="subtitle" class="form-control" rows="3" required placeholder="Enter the headline description, executive quote, or community invitation..."></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Slide Category / Role</label>
              <select name="slide_type" class="form-select">
                <option value="welcome">Welcome &amp; Courses</option>
                <option value="ceo">CEO &amp; Leadership</option>
                <option value="news">News &amp; Opportunities</option>
                <option value="event">Events &amp; Bootcamps</option>
                <option value="custom" selected>Custom Announcement</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Top Pill Badge Text</label>
              <input type="text" name="badge_text" class="form-control" value="Kibiya Tech Hub" placeholder="e.g. Executive Vision, New Batch, Free Workshop">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Badge Icon</label>
              <select name="badge_icon" class="form-select">
                <option value="fa-rocket" selected>fa-rocket (Innovation / Launch)</option>
                <option value="fa-crown">fa-crown (Leadership / CEO)</option>
                <option value="fa-bullhorn">fa-bullhorn (News / Alerts)</option>
                <option value="fa-calendar-star">fa-calendar-star (Events)</option>
                <option value="fa-award">fa-award (Honors / Recognition)</option>
                <option value="fa-graduation-cap">fa-graduation-cap (Graduation)</option>
                <option value="fa-laptop-code">fa-laptop-code (Coding / Tech)</option>
                <option value="fa-lightbulb">fa-lightbulb (Opportunities)</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Badge Color Preset</label>
              <select name="badge_color" class="form-select">
                <option value="bg-warning text-dark" selected>Amber Gold (bg-warning text-dark)</option>
                <option value="bg-success text-white">Emerald Green (bg-success)</option>
                <option value="bg-info text-dark">Cyan Blue (bg-info text-dark)</option>
                <option value="bg-primary text-white">Royal Blue (bg-primary)</option>
                <option value="bg-danger text-white">Crimson Red (bg-danger)</option>
                <option value="bg-dark text-white">Dark Slate (bg-dark)</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Quote Author (Optional)</label>
              <input type="text" name="quote_author" class="form-control" placeholder="e.g. Musa Abdullahi Kibiya">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Quote Author Role</label>
              <input type="text" name="quote_author_role" class="form-control" placeholder="e.g. Executive Director">
            </div>

            <!-- CTA Buttons -->
            <div class="col-md-4">
              <label class="form-label fw-bold">Primary Button Text</label>
              <input type="text" name="btn_primary_text" class="form-control" value="Browse Courses" placeholder="e.g. Browse Courses">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-bold">Primary Button Link</label>
              <input type="text" name="btn_primary_link" class="form-control" value="courses.php" placeholder="e.g. courses.php or https://...">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Primary Icon</label>
              <input type="text" name="btn_primary_icon" class="form-control" value="fa-book-open" placeholder="fa-book-open">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Secondary Button Text</label>
              <input type="text" name="btn_secondary_text" class="form-control" value="Join Free Account" placeholder="e.g. Join Free Account">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-bold">Secondary Button Link</label>
              <input type="text" name="btn_secondary_link" class="form-control" value="register.php" placeholder="e.g. register.php">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Secondary Icon</label>
              <input type="text" name="btn_secondary_icon" class="form-control" value="fa-user-plus" placeholder="fa-user-plus">
            </div>

            <!-- Right Column Card Style -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Right Column Card Style</label>
              <select name="card_type" class="form-select">
                <option value="graphic" selected>Tech Skills Box (AI, Web, Cyber Badges)</option>
                <option value="ceo_profile">CEO &amp; Leadership Profile Card</option>
                <option value="news_highlight">Featured News / Verified Opportunity Card</option>
                <option value="event_badge">Upcoming Event &amp; Bootcamp Card</option>
                <option value="custom_image">Custom Uploaded Cover Photo Card</option>
              </select>
            </div>

            <!-- Background Theme Gradient -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Background Theme Gradient</label>
              <select name="bg_gradient" class="form-select">
                <option value="linear-gradient(135deg, #0b6623 0%, #084919 60%, #032a0d 100%)" selected>Hub Forest Green (Emerald Deep)</option>
                <option value="linear-gradient(135deg, #063914 0%, #094a1b 55%, #15803d 100%)">Emerald Leadership (CEO Style)</option>
                <option value="linear-gradient(135deg, #092e15 0%, #0b5722 55%, #0284c7 100%)">Deep Teal &amp; Ocean (News Style)</option>
                <option value="linear-gradient(135deg, #0a2540 0%, #0e3d68 55%, #0b6623 100%)">Midnight Navy &amp; Gold (Event Style)</option>
                <option value="linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%)">Royal Indigo Tech</option>
                <option value="linear-gradient(135deg, #451a03 0%, #78350f 60%, #b45309 100%)">Warm Amber &amp; Gold</option>
              </select>
            </div>

            <div class="col-md-8">
              <label class="form-label fw-bold">Cover Photo (Optional)</label>
              <input type="file" name="slide_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text small text-muted">Max 10 MB. High-resolution photo shown in the slide card.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control" value="<?php echo (int)($total_slides + 1); ?>" min="0">
            </div>

            <div class="col-12">
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="active_new_slide" checked>
                <label class="form-check-label fw-bold" for="active_new_slide">
                  Live on Homepage (Slide active and transitions in 10s rotation)
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_slide" class="btn btn-warning fw-bold px-4">
            <i class="fa-solid fa-plus-circle me-1"></i>Add Slide to Header
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function filterAdminNews() {
  const query = (document.getElementById('newsSearchInput')?.value || '').toLowerCase();
  const rows = document.querySelectorAll('#adminNewsTable tbody .news-row');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}

function filterAdminSlides() {
  const query = (document.getElementById('sliderSearchInput')?.value || '').toLowerCase();
  const rows = document.querySelectorAll('#adminSlidesTable tbody .slide-row');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}

// Auto-switch to tab if hash exists in URL (e.g. admin_dashboard.php#slider-pane)
document.addEventListener('DOMContentLoaded', function() {
  const hash = window.location.hash;
  if (hash) {
    const targetTabBtn = document.querySelector(`button[data-bs-target="${hash}"]`);
    if (targetTabBtn) {
      const tab = new bootstrap.Tab(targetTabBtn);
      tab.show();
    }
  }
});
</script>

<?php include 'includes/footer.php'; ?>
