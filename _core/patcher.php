
<?php
require_once __DIR__.'/helpers.php';
function apply_migrations(){
  $c=db();
  $c->query("CREATE TABLE IF NOT EXISTS migrations_log(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(200) UNIQUE,checksum CHAR(64),applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
  $dir=__DIR__."/../_migrations";
  $files=array_values(array_filter(scandir($dir),fn($f)=>str_ends_with($f,'.sql')));
  sort($files);
  foreach($files as $f){
    $path="$dir/$f"; $sql=file_get_contents($path); $checksum=hash('sha256',$sql);
    $stmt=$c->prepare("SELECT id FROM migrations_log WHERE name=? AND checksum=?"); $stmt->bind_param("ss",$f,$checksum); $stmt->execute(); $stmt->store_result();
    if($stmt->num_rows==0){
      if(!$c->multi_query($sql)) throw new Exception("Migration $f failed: ".$c->error);
      while($c->more_results() && $c->next_result()){};
      $stmt2=$c->prepare("INSERT INTO migrations_log(name,checksum) VALUES(?,?)"); $stmt2->bind_param("ss",$f,$checksum); $stmt2->execute();
    }
  }
}
if(php_sapi_name()!=='cli'){ $token=$_GET['token']??''; if($token!==env_get('ADMIN_TOKEN')) json_out(['success'=>false,'message'=>'Unauthorized'],401); }
try{ apply_migrations(); json_out(['success'=>true,'message'=>'Migrazioni applicate']); }catch(Throwable $e){ json_out(['success'=>false,'message'=>$e->getMessage()],500); }
