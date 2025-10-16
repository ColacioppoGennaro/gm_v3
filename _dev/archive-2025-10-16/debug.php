<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>🔍 Test Connessione DB (mysqli)</h1>";

require_once __DIR__.'/_core/helpers.php';

echo "<h2>1. Test env_get()</h2>";
echo "DB_HOST: " . env_get('DB_HOST', 'NON TROVATO') . "<br>";
echo "DB_NAME: " . env_get('DB_NAME', 'NON TROVATO') . "<br>";
echo "DB_USER: " . env_get('DB_USER', 'NON TROVATO') . "<br>";
echo "DB_PASS: " . (env_get('DB_PASS') ? '***PRESENTE***' : 'NON TROVATO') . "<br>";

echo "<h2>2. Verifica estensioni PHP</h2>";
echo "mysqli: " . (extension_loaded('mysqli') ? '✅ Disponibile' : '❌ NON DISPONIBILE') . "<br>";
echo "PDO: " . (extension_loaded('pdo') ? '✅ Disponibile' : '❌ NON DISPONIBILE') . "<br>";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? '✅ Disponibile' : '❌ NON DISPONIBILE') . "<br>";

echo "<h2>3. Test db() con mysqli</h2>";
try {
    $db = db();
    echo "✅ Connessione riuscita! (Tipo: " . get_class($db) . ")<br>";
    
    // Test query
    $result = $db->query("SELECT COUNT(*) as total FROM users");
    $row = $result->fetch_assoc();
    echo "✅ Utenti nel DB: " . $row['total'] . "<br>";
    
    // Verifica colonne OAuth
    $result = $db->query("DESCRIBE users");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "<br><h3>Colonne tabella users:</h3>";
    echo implode(', ', $columns) . "<br><br>";
    
    if (in_array('google_oauth_token', $columns)) {
        echo "✅ Colonna google_oauth_token presente<br>";
    } else {
        echo "❌ Colonna google_oauth_token MANCANTE<br>";
        echo "<code>ALTER TABLE users ADD COLUMN google_oauth_token VARCHAR(500) DEFAULT NULL;</code><br>";
    }
    
    if (in_array('google_oauth_refresh', $columns)) {
        echo "✅ Colonna google_oauth_refresh presente<br>";
    } else {
        echo "❌ Colonna google_oauth_refresh MANCANTE<br>";
        echo "<code>ALTER TABLE users ADD COLUMN google_oauth_refresh VARCHAR(500) DEFAULT NULL;</code><br>";
    }
    
    if (in_array('google_oauth_expiry', $columns)) {
        echo "✅ Colonna google_oauth_expiry presente<br>";
    } else {
        echo "❌ Colonna google_oauth_expiry MANCANTE<br>";
        echo "<code>ALTER TABLE users ADD COLUMN google_oauth_expiry DATETIME DEFAULT NULL;</code><br>";
    }
    
} catch (Exception $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>4. Test Sessione</h2>";
session_start();
$_SESSION['test'] = 'ok';
echo isset($_SESSION['test']) ? "✅ Sessione funzionante" : "❌ Sessione non funziona";

echo "<hr><h2>5. Test API auth.php</h2>";
echo "<a href='api/auth.php?a=status' target='_blank'>Test /api/auth.php?a=status</a>";
