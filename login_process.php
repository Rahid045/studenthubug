<?php
session_start(); require_once __DIR__.'/connect.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: login.html');exit;}
$email=trim($_POST['email']??'');$password=$_POST['password']??'';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){header('Location: login.html?error=invalid');exit;}
$stmt=mysqli_prepare($connect,"SELECT user_id,full_name,email,course,year_of_study,role,password_hash FROM users WHERE email=? LIMIT 1");
mysqli_stmt_bind_param($stmt,'s',$email);mysqli_stmt_execute($stmt);$u=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if(!$u||!password_verify($password,$u['password_hash'])){header('Location: login.html?error=invalid');exit;}
session_regenerate_id(true);$_SESSION['user_id']=(int)$u['user_id'];$_SESSION['full_name']=$u['full_name'];$_SESSION['email']=$u['email'];$_SESSION['course']=$u['course'];$_SESSION['year_of_study']=$u['year_of_study'];$_SESSION['role']=$u['role']??'student';
header('Location: dashboard.php');exit;
?>
