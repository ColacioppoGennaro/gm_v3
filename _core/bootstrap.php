<?php
// DEBUG temporaneo (poi toglilo)
error_reporting(E_ALL);
ini_set('display_errors','1');

// Loader .env TOLLERANTE (stesso di diag.php, evita parse_ini_file)
if (!function_exists('gm_load_env')) {
    function gm_load_env($file){
        if (!is_file($file)) return;
        $raw = file_get_contents($file);
        if (substr($raw,0,3) === "\xEF\xBB\xBF") $raw = substr($raw,3); // BOM
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        foreach ($lines as $line){
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
            $pos = strpos($line,'=');
            if ($pos === false) continue;
            $key = trim(substr($line,0,$pos));
            $val = trim(substr($line,$pos+1));
            if ((str_starts_with($val,'"') && str_ends_with($val,'"')) ||
                (str_starts_with($val,"'") && str_ends_with($val,"'"))){
                $val = substr($val,1,-1);
            }
            putenv("$key=$val"); $_ENV[$key]=$val; $_SERVER[$key]=$val;
        }
    }
}
// carica il nostro .env
gm_load_env(__DIR__.'/../.env');
