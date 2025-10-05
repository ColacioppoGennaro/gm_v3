<?php
// /gm_v3/api/diag.php
header('Content-Type: application/json');

// --- robust loader .env (niente parse_ini_file) ---
function load_env_loose($file){
    if (!is_file($file)) return ['used'=>false,'path'=>$file,'error'=>'not_found'];
    $raw = file_get_contents($file);
    // rimuovi BOM eventuale
    if (substr($raw,0,3) === "\xEF\xBB\xBF") $raw = substr($raw,3);
    $lines = preg_split("/\r\n|\n|\r/", $raw);
    foreach ($lines as $line){
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        $pos = strpos($line,'=');
        if ($pos === false) continue;
        $key = trim(substr($line,0,$pos));
        $val = trim(substr($line,$pos+1));
        // togli eventuali virgolette avvolgenti
        if ((str_starts_with($val,'"') && str_ends_with($val,'"')) ||
            (str_starts_with($val,"'") && str_ends_with($val,"'"))){
            $val = substr($val,1,-1);
        }
        putenv("$key=$val"); $_ENV[$key]=$val; $_SERVER[$key]=$val;
    }
    return ['used'=>true,'path'=>$file,'error'=>null];
}

$report = [];
$report['php_version'] = PHP_VERSION;
$report['extensions'] = [
    'mysqli'=>extension_loaded('mysqli'),
    'curl'=>extension_loaded('curl'),
    'mbstring'=>extension_loaded('mbstring'),
    'json'=>extension_loaded('json'),
    'openssl'=>extension_loaded('openssl'),
    'zip'=>extension_loaded('zip'),
];
$report['str_starts_with'] = function_exists('str_starts_with');

// prova a caricare .env (prima gm_v3/.env)
$env_paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../config/gm_v3/.env', // nel caso esistesse
];
$env_results = [];
foreach ($env_paths as $p) {
    $env_results[] = load_env_loose($p);
}
$report['env_load'] = $env_results;

// leggi valori (non stampo la password)
$DB_HOST = getenv('DB_HOST') ?: '';
$DB_NAME = getenv('DB_NAME') ?: '';
$DB_USER = getenv('DB_USER') ?: '';
$DB_PASS = getenv('DB_PASS') ?: '';

// prova connessione mysqli con fallback
$report['db_connection'] = false;
$report['db_error'] = null;
$report['db_host_tried'] = [];

mysqli_report(MYSQLI_REPORT_OFF);
foreach ([$DB_HOST, ($DB_HOST==='localhost'?'127.0.0.1':null)] as $hostTry){
    if (!$hostTry) continue;
    $report['db_host_tried'][] = $hostTry;
    $mysqli = @new mysqli($hostTry, $DB_USER, $DB_PASS, $DB_NAME);
    if (!$mysqli->connect_errno){
        $report['db_connection'] = true;
        $report['db_server_info'] = $mysqli->server_info;
        $mysqli->close();
        break;
    } else {
        $report['db_error'] = $mysqli->connect_error;
    }
}

// includi anche un estratto (sicuro) delle prime 3 righe del .env per capire FORMATO
$peek = [];
foreach ($env_paths as $p){
    if (is_file($p)){
        $lines = preg_split("/\r\n|\n|\r/", file_get_contents($p));
        $peek[] = ['path'=>$p, 'first_lines'=>array_slice($lines,0,3)];
    }
}
$report['env_peek'] = $peek;

echo json_encode($report, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
