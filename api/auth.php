<?php
session_start(); // IMPORTANTE: sessione prima di tutto

require_once __DIR__.'/../_core/bootstrap.php';
require_once __DIR__.'/../_core/helpers.php';

$action=$_GET['a']??$_POST['a']??'';
if($action==='login'){
  ratelimit('login:'.($_SERVER['REMOTE_ADDR']??'na'),10,60);
  $email=strtolower(trim($_POST['email']??'')); $pass=$_POST['password']??'';
  if(!$email||!$pass) json_out(['success'=>false,'message'=>'Campi mancanti'],400);
  $stmt=db()->prepare("SELECT id, pass_hash, role FROM users WHERE email=?"); $stmt->bind_param("s",$email); $stmt->execute(); $res=$stmt->get_result();
  if($row=$res->fetch_assoc()){ if(verify_password($pass,$row['pass_hash'])){ $_SESSION['user_id']=$row['id']; $_SESSION['role']=$row['role']; $_SESSION['email']=$email; json_out(['success'=>true]); } }
  json_out(['success'=>false,'message'=>'Credenziali errate'],401);
}
elseif($action==='register'){
  $email=strtolower(trim($_POST['email']??'')); $pass=$_POST['password']??'';
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<6) json_out(['success'=>false,'message'=>'Dati non validi'],400);
  $hash=hash_password($pass);
  $stmt=db()->prepare("INSERT INTO users(email,pass_hash) VALUES(?,?)"); $stmt->bind_param("ss",$email,$hash);
  try{ $stmt->execute(); } catch(Throwable $e){ json_out(['success'=>false,'message'=>'Email già esistente'],409); }
  $master='master'; $label_id='lbl_'.bin2hex(random_bytes(10));
  $stmt2=db()->prepare("INSERT INTO labels(user_id, name, docanalyzer_label_id) VALUES(?,?,?)"); $uid=db()->insert_id; $stmt2->bind_param("iss",$uid,$master,$label_id); $stmt2->execute();
  json_out(['success'=>true]);
}
elseif($action==='logout'){ session_destroy(); json_out(['success'=>true]); }
else json_out(['success'=>false,'message'=>'Azione non valida'],404);
