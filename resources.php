<?php
session_start();
require_once __DIR__ . '/connect.php';

$q = trim($_GET['q'] ?? '');
$like = "%$q%";
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$queryError = null;

$sql = "SELECT r.*, COALESCE(r.course,'') AS course, COALESCE(r.course_unit, r.subject) AS course_unit, u.full_name,
        CASE WHEN rp.purchase_id IS NOT NULL THEN 1 ELSE 0 END AS already_paid
        FROM resources r
        JOIN users u ON r.uploader_id = u.user_id
        LEFT JOIN resource_purchases rp ON rp.resource_id = r.resource_id AND rp.user_id = ? AND rp.status = 'successful'
        WHERE r.title LIKE ? OR r.description LIKE ? OR r.subject LIKE ? OR r.topic LIKE ? OR COALESCE(r.course,'') LIKE ? OR COALESCE(r.course_unit,'') LIKE ?
        ORDER BY r.uploaded_at DESC LIMIT 100";

$stmt = mysqli_prepare($connect, $sql);
$resources = [];

if ($stmt) {
    $bindOk = mysqli_stmt_bind_param($stmt, 'issssss', $userId, $like, $like, $like, $like, $like, $like);
    if ($bindOk) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $resources = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
    } else {
        $queryError = mysqli_error($connect);
    }
} else {
    $queryError = mysqli_error($connect);
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resources | EduConnect</title><link rel="stylesheet" href="style.css"></head><body>
<header class="site-header"><div class="container nav"><a class="logo" href="index.html"><span class="logo-mark">E</span>EduConnect</a><ul class="nav-links"><li><a href="index.html">Home</a></li><li><a class="active" href="resources.php">Resources</a></li><li><a href="tutoring.php">Tutoring</a></li></ul><div class="nav-actions"><?php if(isset($_SESSION['user_id'])):?><a class="btn btn-soft" href="dashboard.php">Dashboard</a><a class="btn btn-primary" href="upload.php">Upload</a><?php else:?><a class="btn btn-soft" href="login.html">Sign in</a><a class="btn btn-primary" href="register.html">Join free</a><?php endif;?></div></div></header>
<main class="page"><div class="container"><div class="page-title"><h1>Study resources</h1><p>Find notes, past papers, tutorials and other student-shared materials.</p></div><form class="search-bar"><input id="searchInput" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search title, course, unit or topic..."><button class="btn btn-primary">Search</button></form>
<?php if(!$resources):?><div class="empty"><h3>No resources found</h3><p>Try another search or be the first to upload.</p></div><?php else:?><div class="resource-grid"><?php foreach($resources as $r):$terms=strtolower(implode(' ',[$r['title'],$r['course'],$r['course_unit'],$r['subject'],$r['topic'],$r['description']]));$isPaid=(int)$r['is_paid'];$price=(float)$r['price'];$alreadyPaid=(int)$r['already_paid'];?><article class="resource-card" data-search="<?=htmlspecialchars($terms)?>"><span class="badge"><?=htmlspecialchars($r['resource_type'])?></span><?php if($isPaid):?><span class="badge" style="background:#fff3cd;color:#8a5a00; margin-top:8px; display:inline-block;">Paid: <?=htmlspecialchars($r['currency'])?> <?=number_format($price,2)?></span><?php else:?><span class="badge" style="background:#e8f8ee;color:#1a6d46; margin-top:8px; display:inline-block;">Free</span><?php endif;?><h3><?=htmlspecialchars($r['title'])?></h3><p><strong><?=htmlspecialchars($r['course'])?></strong><br><?=htmlspecialchars($r['course_unit'])?></p><p style="margin-top:8px"><?=htmlspecialchars($r['description']?:'No description provided.')?></p><div class="resource-meta"><span>By <?=htmlspecialchars($r['full_name'])?></span><span><?=$r['downloads']?> downloads</span></div><?php if(isset($_SESSION['user_id'])): if($isPaid && !$alreadyPaid):?><a class="btn btn-primary btn-block" href="checkout.php?id=<?=$r['resource_id']?>">Pay via MoMo / Airtel</a><?php else:?><a class="btn btn-primary btn-block" href="download.php?id=<?=$r['resource_id']?>">View / download</a><?php endif; else:?><a class="btn btn-soft btn-block" href="login.html">Sign in to access</a><?php endif;?></article><?php endforeach;?></div><?php endif;?></div></main><footer class="site-footer"><div class="container footer-inner"><span>© 2026 EduConnect</span><span>Learn • Share • Connect</span></div></footer><script src="script.js"></script></body></html>
