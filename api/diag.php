<?php
header('Content-Type: application/json');
$info = [];
$info['php_version'] = phpversion();
$info['extensions'] = [
    'mysqli' => extension_loaded('mysqli'),
    'curl' => extension_loaded('curl'),
    'mbstring' => extension_loaded('mbstring'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl'),
    'zip' => extension_loaded('zip')
];
$info['str_starts_with'] = function_exists('str_starts_with');
try {
    require_once __DIR__.'/../_core/helpers.php';
    $conn = db();
    $info['db_connection'] = $conn ? true : false;
} catch (\Throwable $e) {
    $info['db_connection'] = false;
    $info['db_error'] = $e->getMessage();
}
echo json_encode($info);
