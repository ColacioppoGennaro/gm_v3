<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__.'/../_core/bootstrap.php';
    require_once __DIR__.'/../_core/helpers.php';
    require_login();

    $user=user(); 
    $a=$_GET['a']??$_POST['a']??'';
    
    if($a==='list'){
        $sql="SELECT id,title,description, DATE_FORMAT(starts_at,'%Y-%m-%dT%H:%i:%s') as start, DATE_FORMAT(ends_at,'%Y-%m-%dT%H:%i:%s') as end FROM events WHERE user_id=? ORDER BY starts_at ASC";
        $st=db()->prepare($sql); 
        $st->bind_param("i",$user['id']); 
        $st->execute(); 
        $r=$st->get_result(); 
        ob_end_clean();
        json_out(['success'=>true,'data'=>$r->fetch_all(MYSQLI_ASSOC)]);
    }
    elseif($a==='create'){
        $title=trim($_POST['title']??''); 
        $desc=trim($_POST['description']??''); 
        $start=$_POST['starts_at']??null; 
        $end=$_POST['ends_at']??null;
        
        if(!$title||!$start) {
            ob_end_clean();
            json_out(['success'=>false,'message'=>'Dati mancanti'],400);
        }
        
        $st=db()->prepare("INSERT INTO events(user_id,title,description,starts_at,ends_at,source) VALUES(?,?,?,?,?, 'user')"); 
        $st->bind_param("issss",$user['id'],$title,$desc,$start,$end); 
        $st->execute();
        
        ob_end_clean();
        json_out(['success'=>true,'id'=>db()->insert_id]);
    }
    elseif($a==='delete'){
        $id=intval($_POST['id']??0); 
        $st=db()->prepare("DELETE FROM events WHERE id=? AND user_id=?"); 
        $st->bind_param("ii",$id,$user['id']); 
        $st->execute(); 
        ob_end_clean();
        json_out(['success'=>true]);
    }
    else {
        ob_end_clean();
        json_out(['success'=>false,'message'=>'Azione non valida'],404);
    }
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in calendar.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
