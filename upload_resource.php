<?php
session_start();
include 'connect.php';

function ensureResourceColumns($connect) {
    $courseCheck = mysqli_query($connect, "SHOW COLUMNS FROM resources LIKE 'course'");
    if (mysqli_num_rows($courseCheck) === 0) {
        mysqli_query($connect, "ALTER TABLE resources ADD COLUMN course VARCHAR(255) NOT NULL AFTER title");
    }

    $courseUnitCheck = mysqli_query($connect, "SHOW COLUMNS FROM resources LIKE 'course_unit'");
    if (mysqli_num_rows($courseUnitCheck) === 0) {
        mysqli_query($connect, "ALTER TABLE resources ADD COLUMN course_unit VARCHAR(255) NOT NULL AFTER course");
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location:login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resourceFile'])) {
    ensureResourceColumns($connect);

    $uploader_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $course_unit = trim($_POST['course_unit'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $resource_type = trim($_POST['resource_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_paid = (isset($_POST['is_paid']) && $_POST['is_paid'] == '1') ? 1 : 0;
    $price = (float) ($_POST['price'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'GHS');

    $title = mysqli_real_escape_string($connect, $title);
    $course = mysqli_real_escape_string($connect, $course);
    $course_unit = mysqli_real_escape_string($connect, $course_unit);
    $topic = mysqli_real_escape_string($connect, $topic);
    $resource_type = mysqli_real_escape_string($connect, $resource_type);
    $description = mysqli_real_escape_string($connect, $description);

    if (empty($title) || empty($course) || empty($course_unit) || empty($resource_type)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: upload.html");
        exit();
    }

    if ($is_paid && $price <= 0) {
        $_SESSION['error'] = "Please set a valid price before marking the resource as paid.";
        header("Location: upload.html");
        exit();
    }

    $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'png'];
    $file = $_FILES['resourceFile'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Allowed: " . implode(', ', $allowed_types);
        header("Location: upload.html");
        exit();
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        $_SESSION['error'] = "File too large. Max 10MB.";
        header("Location: upload.html");
        exit();
    }

    $new_filename = uniqid('edu_') . '.' . $file_ext;
    $upload_path = 'uploads/resources/' . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $_SESSION['error'] = "Failed to save file. Please try again.";
        header("Location: upload.html");
        exit();
    }

    $subject = $course_unit;
    $sql = "INSERT INTO resources (uploader_id, title, course, course_unit, subject, topic, resource_type, description, file_path, is_paid, price, currency)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($connect, $sql);
    $file_path = $upload_path;

    if (!$stmt) {
        $_SESSION['error'] = "Database error. Please try again.";
        header("Location: upload.html");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "issssssssids",
        $uploader_id,
        $title,
        $course,
        $course_unit,
        $subject,
        $topic,
        $resource_type,
        $description,
        $file_path,
        $is_paid,
        $price,
        $currency
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Resource uploaded successfully!";
        header("Location: resources.php");
        exit();
    } else {
        $_SESSION['error'] = "Database error. Please try again.";
        header("Location: upload.html");
        exit();
    }
}
?>
