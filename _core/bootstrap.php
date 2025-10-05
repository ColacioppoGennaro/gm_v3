
<?php
declare(strict_types=1);
session_name(env_get('SESSION_NAME')?:'gmv3sess');
session_start(['cookie_httponly'=>true,'cookie_secure'=>isset($_SERVER['HTTPS']),'cookie_samesite'=>'Lax']);
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/sentinel.php';
sentinel_check();
