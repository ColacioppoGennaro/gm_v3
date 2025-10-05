<?php
session_start();
require_once __DIR__.'/../_core/bootstrap.php';
require_once __DIR__.'/../_core/helpers.php';
require_login();

$user=user(); $q=trim($_POST['q']??''); $category=trim($_POST['category']??'');
if($q==='') json_out(['success'=>false,'message'=>'Domanda vuota'],400);
$max=is_pro()?200:20; $day=(new DateTime())->format('Y-m-d');
$st=db()->prepare("INSERT INTO quotas(user_id,day,uploads_count,chat_count) VALUES(?, ?, 0, 0) ON DUPLICATE KEY UPDATE day=day"); $st->bind_param("is",$user['id'],$day); $st->execute();
db()->query("UPDATE quotas SET chat_count=chat_count+1 WHERE user_id={$user['id']} AND day='$day'");
$r=db()->query("SELECT chat_count FROM quotas WHERE user_id={$user['id']} AND day='$day'")->fetch_assoc();
if(intval($r['chat_count'])>$max) json_out(['success'=>false,'message'=>'Limite chat giornaliero raggiunto'],403);

// label logic
if(is_pro()){
  if(!$category) json_out(['success'=>false,'message'=>'Seleziona una categoria'],400);
  $st=db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name=?"); $st->bind_param("is",$user['id'],$category); $st->execute(); $rr=$st->get_result(); if(!($row=$rr->fetch_assoc())) json_out(['success'=>false,'message'=>'Categoria non valida'],400);
  $label_ids=[$row['docanalyzer_label_id']];
} else {
  $st=db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'"); $st->bind_param("i",$user['id']); $st->execute(); $rr=$st->get_result(); $row=$rr->fetch_assoc(); $label_ids=[$row['docanalyzer_label_id']];
}

// placeholder RAG + fallback
$found=false; $answer=null;
if(stripos($q,'imu')!==false || stripos($q,'scadenza')!==false){ $found=true; $answer="Nei tuoi documenti ho trovato riferimenti alla scadenza IMU: 16 giugno. Vuoi un promemoria 10 giorni prima?"; }
if(!$found){ $answer="Non ho trovato nei documenti. Risposta AI generica (fallback)."; $source='llm'; } else { $source='docs'; }

$st=db()->prepare("INSERT INTO chat_logs(user_id,source,question,answer) VALUES(?,?,?,?)"); $st->bind_param("isss",$user['id'],$source,$q,$answer); $st->execute();
json_out(['success'=>true,'source'=>$source,'answer'=>$answer]);
