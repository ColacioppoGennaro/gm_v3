<?php
// view_logs.php - Visualizza ultimi log PHP
require_once '_core/helpers.php';
require_login();

$logFile = ini_get('error_log');
if (!$logFile || $logFile === 'syslog') {
    $logFile = '/var/log/apache2/error.log';
}

$lines = 100; // Ultimi 100 righe

echo "<h1>Ultimi $lines log PHP</h1>";
echo "<pre>";

if (file_exists($logFile)) {
    $log = shell_exec("tail -n $lines " . escapeshellarg($logFile));
    echo htmlspecialchars($log);
} else {
    echo "Log file non trovato: $logFile\n";
    echo "\nProva con: " . php_ini_loaded_file() . "\n";
    echo "error_log setting: " . ini_get('error_log');
}

echo "</pre>";
