<?php
session_start();
require_once __DIR__.'/../_core/bootstrap.php';
require_once __DIR__.'/../_core/helpers.php';
require_login();

$user=user(); $action=$_GET['a']??$_POST['a']??'';

function get_master_label($uid){
  $st=db()->prepare("SELECT id, docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'");
  $st->bind_param("i",$uid); $st->execute(); $r=$st->get_result(); if($row=$r->fetch_assoc()) return $row; throw new Exception('Label master assente');
}

if($action==='list'){
  $st=db()->prepare("SELECT d.id,d.file_name,d.size,d.mime,d.created_at,l.name as label FROM documents d JOIN labels l ON d.label_id=l.id WHERE d.user_id=? ORDER BY d.created_at DESC");
  $st->bind_param("i",$user['id']); $st->execute(); $r=$st->get_result(); json_out(['success'=>true,'data'=>$r->fetch_all(MYSQLI_ASSOC)]);
}
elseif($action==='upload'){
  $max=is_pro()?200:5; $r=db()->query("SELECT COUNT(*) c FROM documents WHERE user_id=".$user['id'])->fetch_assoc(); if(intval($r['c'])>=$max) json_out(['success'=>false,'message'=>'Limite documenti raggiunto'],403);
  if(!isset($_FILES['file'])) json_out(['success'=>false,'message'=>'Nessun file'],400);
  $f=$_FILES['file']; if($f['error']!==UPLOAD_ERR_OK) json_out(['success'=>false,'message'=>'Errore upload'],400);
  $max_size=(is_pro()?150:10)*1024*1024; if($f['size']>$max_size) json_out(['success'=>false,'message'=>'File troppo grande'],400);
  $allowed=['pdf','doc','docx','txt','csv','xlsx','png','jpg','jpeg']; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); if(!in_array($ext,$allowed)) json_out(['success'=>false,'message'=>'Tipo non ammesso'],400);
  $master=get_master_label($user['id']); $label_id=$master['id'];
  if(isset($_POST['category']) && is_pro()){ $cat=trim($_POST['category']); $st=db()->prepare("SELECT id FROM labels WHERE user_id=? AND name=?"); $st->bind_param("is",$user['id'],$cat); $st->execute(); $rr=$st->get_result(); if($row=$rr->fetch_assoc()) $label_id=$row['id']; }
  $doc_id='doc_'.bin2hex(random_bytes(10));
  $st=db()->prepare("INSERT INTO documents(user_id,label_id,file_name,mime,size,docanalyzer_doc_id) VALUES(?,?,?,?,?,?)"); $st->bind_param("iissis",$user['id'],$label_id,$f['name'],$f['type'],$f['size'],$doc_id); $st->execute();
  json_out(['success'=>true]);
}
elseif($action==='delete'){
  $id=intval($_POST['id']??0); if(!$id) json_out(['success'=>false,'message'=>'ID mancante'],400);
  $st=db()->prepare("SELECT docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?"); $st->bind_param("ii",$id,$user['id']); $st->execute(); $r=$st->get_result(); if(!($row=$r->fetch_assoc())) json_out(['success'=>false,'message'=>'Non trovato'],404);
  $st=db()->prepare("DELETE FROM documents WHERE id=? AND user_id=?"); $st->bind_param("ii",$id,$user['id']); $st->execute(); json_out(['success'=>true]);
}
else json_out(['success'=>false,'message'=>'Azione non valida'],404);
