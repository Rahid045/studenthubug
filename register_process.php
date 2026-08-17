<?php
session_start();require_once __DIR__.'/connect.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: register.html');exit;}
$full_name=trim($_POST['full_name']??'');$email=trim($_POST['email']??'');$course=trim($_POST['course']??'');$year=trim($_POST['year']??'');$password=$_POST['password']??'';$confirm=$_POST['confirm_password']??'';
$errors=[];if($full_name==='')$errors[]='Full name is required.';if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';if($course==='')$errors[]='Course is required.';if(strlen($password)<8)$errors[]='Password must be at least 8 characters.';if($password!==$confirm)$errors[]='Passwords do not match.';
if($errors){header('Location: register.html?error='.rawurlencode(implode(' ',$errors)));exit;}
$stmt=mysqli_prepare($connect,"SELECT user_id FROM users WHERE email=? LIMIT 1");mysqli_stmt_bind_param($stmt,'s',$email);mysqli_stmt_execute($stmt);mysqli_stmt_store_result($stmt);
if(mysqli_stmt_num_rows($stmt)>0){header('Location: register.html?error='.rawurlencode('Email is already registered.'));exit;}
mysqli_stmt_close($stmt);
$hash=password_hash($password,PASSWORD_DEFAULT);$sql="INSERT INTO users(full_name,email,password_hash,course,year_of_study,role) VALUES(?,?,?,?,?,'student')";$stmt=mysqli_prepare($connect,$sql);
mysqli_stmt_bind_param($stmt,'sssss',$full_name,$email,$hash,$course,$year);
if(!mysqli_stmt_execute($stmt)){header('Location: register.html?error='.rawurlencode('Registration could not be completed.'));exit;}
$_SESSION['user_id']=mysqli_insert_id($connect);$_SESSION['full_name']=$full_name;$_SESSION['email']=$email;$_SESSION['course']=$course;$_SESSION['year_of_study']=$year;$_SESSION['role']='student';header('Location: dashboard.php');exit;
?>
