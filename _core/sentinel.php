
<?php
function sentinel_check(){
  $cache=sys_get_temp_dir()."/gmv3_sentinel_cache.json";
  $data=null;
  if(file_exists($cache) && (time()-filemtime($cache))<60){
    $data=json_decode(file_get_contents($cache),true);
  }
  if(!$data){
    $data=['php_ok'=>version_compare(PHP_VERSION,'8.1','>='),'exts_ok'=>true,'db_ok'=>false,'doc_ok'=>false,'errors'=>[]];
    $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true);
    foreach(($manifest['required_ext']??[]) as $ext){
      if(!extension_loaded($ext)){ $data['exts_ok']=false; $data['errors'][]="Missing ext: $ext"; }
    }
    try{ db()->ping(); $data['db_ok']=true; }catch(Throwable $e){ $data['errors'][]='DB fail'; }
    $url=rtrim(env_get('DOCANALYZER_API_URL',''),'/').'/health';
    if($url){
      try{ $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>2]); curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $data['doc_ok']=$code>=200 && $code<500; }catch(Throwable $e){}
    }
    file_put_contents($cache,json_encode($data));
  }
  $ok=$data['php_ok'] && $data['exts_ok'] && $data['db_ok'];
  header('X-GMV3-Health: '.($ok?'ok':'degraded'));
}
