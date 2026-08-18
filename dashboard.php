<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$id = (int) $_SESSION['user_id'];

function prepareAndBind($connect, $sql, $types, ...$params) {
    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) {
        throw new RuntimeException('Database prepare failed: ' . mysqli_error($connect));
    }

    if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
        throw new RuntimeException('Database bind failed: ' . mysqli_error($connect));
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Database execute failed: ' . mysqli_error($connect));
    }

    return $stmt;
}

try {
    $stmt = prepareAndBind($connect, 'SELECT full_name,email,course,year_of_study,role FROM users WHERE user_id=?', 'i', $id);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;

    if (!$user) {
        session_destroy();
        header('Location: login.html');
        exit;
    }

    $stmt = prepareAndBind($connect, 'SELECT COUNT(*) total FROM resources WHERE uploader_id=?', 'i', $id);
    $result = mysqli_stmt_get_result($stmt);
    $uploads = $result ? (int) mysqli_fetch_assoc($result)['total'] : 0;

    $stmt = prepareAndBind($connect, 'SELECT COUNT(*) total FROM tutoring_requests WHERE student_id=?', 'i', $id);
    $result = mysqli_stmt_get_result($stmt);
    $requests = $result ? (int) mysqli_fetch_assoc($result)['total'] : 0;

    $stmt = prepareAndBind($connect, 'SELECT title,course_unit,resource_type,uploaded_at FROM resources WHERE uploader_id=? ORDER BY uploaded_at DESC LIMIT 5', 'i', $id);
    $result = mysqli_stmt_get_result($stmt);
    $my = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

    $stmt = prepareAndBind($connect, 'SELECT subject,topic,preferred_date,preferred_time,status FROM tutoring_requests WHERE student_id=? ORDER BY created_at DESC LIMIT 5', 'i', $id);
    $result = mysqli_stmt_get_result($stmt);
    $tut = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
} catch (RuntimeException $e) {
    die('Dashboard query error: ' . $e->getMessage());
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | EduConnect</title><link rel="stylesheet" href="style.css"></head><body>
<header class="site-header"><div class="container nav"><a class="logo" href="index.html"><span class="logo-mark">E</span>EduConnect</a><ul class="nav-links"><li><a href="resources.php">Resources</a></li><li><a href="upload.php">Upload</a></li><li><a href="tutoring.php">Tutoring</a></li><li><a class="active" href="dashboard.php">Dashboard</a></li></ul><div class="nav-actions"><a class="btn btn-danger" href="logout.php">Log out</a></div></div></header>
<main class="page"><div class="container"><section class="dashboard-hero"><h1>Welcome, <?=htmlspecialchars($user['full_name'])?> 👋</h1><p><?=htmlspecialchars($user['course'])?> • Year <?=htmlspecialchars($user['year_of_study'])?></p></section>
<section class="dashboard-stats"><article class="dashboard-card"><small>Your uploads</small><div class="number"><?=$uploads?></div><a href="upload.php">Share a resource →</a></article><article class="dashboard-card"><small>Tutoring requests</small><div class="number"><?=$requests?></div><a href="tutoring.php">Request tutoring →</a></article><article class="dashboard-card"><small>Account status</small><div class="number">✓</div><small>Active <?=htmlspecialchars($user['role']??'student')?> account</small></article></section>
<section class="dashboard-section"><h2>Your recent uploads</h2><?php if(!$my):?><div class="empty">No uploads yet. <a href="upload.php">Upload your first resource</a>.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Resource</th><th>Course unit</th><th>Type</th><th>Date</th></tr></thead><tbody><?php foreach($my as $r):?><tr><td><strong><?=htmlspecialchars($r['title'])?></strong></td><td><?=htmlspecialchars($r['course_unit']??'—')?></td><td><span class="badge"><?=htmlspecialchars($r['resource_type'])?></span></td><td><?=htmlspecialchars(date('d M Y',strtotime($r['uploaded_at'])))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<section class="dashboard-section"><h2>Your tutoring requests</h2><?php if(!$tut):?><div class="empty">No tutoring requests yet. <a href="tutoring.php">Request help</a>.</div><?php else:?><div class="resource-grid"><?php foreach($tut as $r):?><article class="resource-card"><span class="badge"><?=htmlspecialchars($r['status']??'open')?></span><h3><?=htmlspecialchars($r['subject'])?></h3><p><?=htmlspecialchars($r['topic'])?></p><div class="resource-meta"><span><?=htmlspecialchars($r['preferred_date'])?></span><span><?=htmlspecialchars($r['preferred_time']?:'Flexible')?></span></div></article><?php endforeach;?></div><?php endif;?></section></div></main><script src="script.js"></script></body></html>
