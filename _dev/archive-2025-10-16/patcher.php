<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Debug gm_v3</title>";
echo "<style>body{font-family:monospace;background:#0f172a;color:#e5e7eb;padding:20px;line-height:1.6}";
echo "h2{color:#7c3aed;border-bottom:2px solid #7c3aed;padding-bottom:8px}";
echo "code{background:#1f2937;padding:2px 8px;border-radius:4px;color:#10b981}";
echo ".ok{color:#10b981}.error{color:#ef4444}.warn{color:#f59e0b}";
echo "table{border-collapse:collapse;width:100%;margin:16px 0}";
echo "th,td{border:1px solid #374151;padding:8px;text-align:left}";
echo "th{background:#1f2937}</style></head><body>";

echo "<h1>🔍 Debug gm_v3</h1>";

// 1. Sessione
echo "<h2>1. Sessione PHP</h2>";
echo "Status: <code>" . session_status() . "</code> ";
echo session_status() === 2 ? "<span class='ok'>✓ Attiva</span>" : "<span class='error'>✗ Non attiva</span>";
echo "<br>Session ID: <code>" . session_id() . "</code><br>";

// 2. PHP Version
echo "<h2>2. Versione PHP</h2>";
echo "Versione: <code>" . PHP_VERSION . "</code> ";
echo version_compare(PHP_VERSION, '8.1', '>=') ? "<span class='ok'>✓ OK</span>" : "<span class='error'>✗ Troppo vecchia</span>";
echo "<br>";

// 3. Estensioni
echo "<h2>3. Estensioni PHP</h2>";
echo "<table><tr><th>Estensione</th><th>Status</th></tr>";
$required = ['mysqli', 'curl', 'mbstring', 'json', 'openssl', 'zip'];
foreach ($required as $ext) {
    $loaded = extension_loaded($ext);
    echo "<tr><td>$ext</td><td>";
    echo $loaded ? "<span class='ok'>✓ Caricata</span>" : "<span class='error'>✗ Mancante</span>";
    echo "</td></tr>";
}
echo "</table>";

// 4. File esistenti
echo "<h2>4. File del progetto</h2>";
$files = [
    '_core/helpers.php',
    '_core/bootstrap.php',
    'api/auth.php',
    'api/documents.php',
    'api/chat.php',
    'api/calendar.php',
    '.env'
];
echo "<table><tr><th>File</th><th>Status</th></tr>";
foreach ($files as $f) {
    $exists = file_exists(__DIR__ . '/' . $f);
    echo "<tr><td>$f</td><td>";
    echo $exists ? "<span class='ok'>✓ Esiste</span>" : "<span class='error'>✗ Mancante</span>";
    echo "</td></tr>";
}
echo "</table>";

// 5. Include helpers
echo "<h2>5. Caricamento helpers.php</h2>";
try {
    require_once __DIR__.'/_core/helpers.php';
    echo "<span class='ok'>✓ helpers.php caricato correttamente</span><br>";
} catch (Throwable $e) {
    echo "<span class='error'>✗ Errore: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 6. Variabili .env
echo "<h2>6. Configurazione .env</h2>";
if (function_exists('env_get')) {
    echo "<table><tr><th>Variabile</th><th>Valore</th></tr>";
    $vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_ENV'];
    foreach ($vars as $var) {
        $val = env_get($var, '<span class="error">non trovato</span>');
        if ($var === 'DB_PASS') $val = '***nascosta***';
        echo "<tr><td>$var</td><td><code>" . htmlspecialchars($val) . "</code></td></tr>";
    }
    echo "</table>";
} else {
    echo "<span class='error'>✗ Funzione env_get() non disponibile</span><br>";
}

// 7. Connessione DB
echo "<h2>7. Database</h2>";
if (function_exists('db')) {
    try {
        $conn = db();
        echo "<span class='ok'>✓ Connessione riuscita!</span><br>";
        echo "Server: <code>" . $conn->server_info . "</code><br>";
        echo "Charset: <code>" . $conn->character_set_name() . "</code><br>";
        
        // Prova una query
        $result = $conn->query("SELECT COUNT(*) as cnt FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Utenti nel DB: <code>" . $row['cnt'] . "</code><br>";
        }
    } catch (Throwable $e) {
        echo "<span class='error'>✗ Errore DB: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
} else {
    echo "<span class='error'>✗ Funzione db() non disponibile</span><br>";
}

// 8. Test session write
echo "<h2>8. Test scrittura sessione</h2>";
$_SESSION['test'] = 'ok';
echo isset($_SESSION['test']) ? "<span class='ok'>✓ Sessione scrivibile</span>" : "<span class='error'>✗ Sessione non scrivibile</span>";
echo "<br>";

// 9. Percorsi
echo "<h2>9. Percorsi</h2>";
echo "Document Root: <code>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a') . "</code><br>";
echo "Script Path: <code>" . __DIR__ . "</code><br>";
echo "Script File: <code>" . __FILE__ . "</code><br>";

// 10. Headers già inviati?
echo "<h2>10. Headers</h2>";
$file = $line = null;
if (headers_sent($file, $line)) {
    echo "<span class='warn'>⚠ Headers già inviati in <code>$file</code> alla riga <code>$line</code></span><br>";
} else {
    echo "<span class='ok'>✓ Headers non ancora inviati</span><br>";
}

echo "<hr><p style='margin-top:32px'>";
echo "<a href='index.php' style='color:#7c3aed;font-weight:bold'>← Torna a index.php</a> | ";
echo "<a href='api/diag.php' style='color:#7c3aed;font-weight:bold'>Vedi diagnostica API</a>";
echo "</p>";

echo "</body></html>";
