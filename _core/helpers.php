
<?php
function env_get($key,$default=null){static $E=null;if($E===null){$E=[];$paths=[dirname(__DIR__,2)."/config/gm_v3/.env",__DIR__."/../.env"];foreach($paths as $p){if(is_readable($p)){foreach(file($p,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){if(str_starts_with(trim($line),'#'))continue;$parts=explode('=',$line,2);if(count($parts)==2)$E[trim($parts[0])]=trim($parts[1]);}break;}}return $E[$key]??$default;}
function db(){static $c=null;if($c===null){$c=new mysqli(env_get('DB_HOST'),env_get('DB_USER'),env_get('DB_PASS'),env_get('DB_NAME'));if($c->connect_errno)throw new Exception('DB fail');$c->set_charset('utf8mb4');}return $c;}
function json_out($a,$code=200){http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function require_login(){if(!isset($_SESSION['user_id']))json_out(['success'=>false,'message'=>'Accesso negato'],401);}function user(){return ['id'=>$_SESSION['user_id']??null,'role'=>$_SESSION['role']??'free','email'=>$_SESSION['email']??null];}
function is_pro(){return (user()['role']??'free')==='pro';}function is_admin(){return (user()['role']??'free')==='admin';}
function ratelimit($key,$limit,$window=60){$file=sys_get_temp_dir()."/gmv3_rl_".md5($key);$data=['count'=>0,'until'=>time()+$window];if(file_exists($file))$data=json_decode(file_get_contents($file),true);if(time()>($data['until']??0))$data=['count'=>0,'until'=>time()+$window];$data['count']++;file_put_contents($file,json_encode($data));if($data['count']>$limit)json_out(['success'=>false,'message'=>'Troppi tentativi, riprova tra poco']);}
function hash_password($p){return password_hash($p,PASSWORD_BCRYPT);}function verify_password($p,$h){return password_verify($p,$h);}
