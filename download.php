<?php
session_start();require_once __DIR__.'/connect.php';if(!isset($_SESSION['user_id'])){header('Location: login.html');exit;}
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id){http_response_code(400);exit('Invalid resource.');}
$currentUser=(int)$_SESSION['user_id'];
$s=mysqli_prepare($connect,"SELECT r.*, u.full_name FROM resources r JOIN users u ON u.user_id = r.uploader_id WHERE r.resource_id=? LIMIT 1");mysqli_stmt_bind_param($s,'i',$id);mysqli_stmt_execute($s);$resource=mysqli_fetch_assoc(mysqli_stmt_get_result($s));if(!$resource){http_response_code(404);exit('Resource not found.');}
$allowedToDownload=false;
if((int)$resource['uploader_id'] === $currentUser){$allowedToDownload=true;}
if((int)$resource['is_paid'] === 0){$allowedToDownload=true;}
if(!$allowedToDownload){
    $paid=mysqli_prepare($connect,"SELECT purchase_id FROM resource_purchases WHERE user_id=? AND resource_id=? AND status='successful' LIMIT 1");
    mysqli_stmt_bind_param($paid,'ii',$currentUser,$id);mysqli_stmt_execute($paid);$purchase=mysqli_fetch_assoc(mysqli_stmt_get_result($paid));
    if(!$purchase){header('Location: checkout.php?id='.(int)$id);exit;}
    $allowedToDownload=true;
}
$base=realpath(__DIR__.'/uploads/resources');$file=realpath(__DIR__.'/'.str_replace(['\\','/'],DIRECTORY_SEPARATOR,$resource['file_path']));
if(!$file||!$base||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit('File unavailable.');}
mysqli_query($connect,"UPDATE resources SET downloads=downloads+1 WHERE resource_id=".(int)$id);header('Content-Type: '.(mime_content_type($file)?:'application/octet-stream'));header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="'.basename($file).'"');readfile($file);exit; ?>
