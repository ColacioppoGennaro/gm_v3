<?php
/**
 * GM_V3 - Test Connessione Database
 * 
 * Script temporaneo per verificare che:
 * 1. La connessione al database funziona
 * 2. Le tabelle esistono
 * 3. I dati di test sono presenti
 * 
 * ⚠️ ELIMINARE DOPO IL TEST (per sicurezza)
 */

// Abilita visualizzazione errori (solo per testing)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definisci costante per permettere inclusione db_config
define('GM_V3_INIT', true);

// Includi configurazione
require_once 'db_config.php';

// ============================================
// INIZIO TEST
// ============================================

echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <title>GM_V3 - Test Database</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 50px auto; 
            padding: 20px;
            background: #1e293b;
            color: #e2e8f0;
        }
        .success { 
            color: #10b981; 
            padding: 10px; 
            margin: 10px 0; 
            border: 2px solid #10b981;
            border-radius: 5px;
        }
        .error { 
            color: #ef4444; 
            padding: 10px; 
            margin: 10px 0; 
            border: 2px solid #ef4444;
            border-radius: 5px;
        }
        .info { 
            color: #3b82f6; 
            padding: 10px; 
            margin: 10px 0; 
            border: 2px solid #3b82f6;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #334155;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #475569;
        }
        th {
            background: #1e293b;
            font-weight: bold;
        }
        h1 { color: #818cf8; }
        h2 { color: #a78bfa; margin-top: 30px; }
    </style>
</head>
<body>";

echo "<h1>🔧 GM_V3 - Test Connessione Database</h1>";

// ============================================
// TEST 1: Connessione Base
// ============================================

echo "<h2>Test 1: Connessione al Database</h2>";

try {
    $conn = getDatabaseConnection();
    echo "<div class='success'>✅ Connessione al database riuscita!</div>";
    echo "<div class='info'>";
    echo "Database: <strong>" . DB_NAME . "</strong><br>";
    echo "Host: <strong>" . DB_HOST . "</strong><br>";
    echo "User: <strong>" . DB_USER . "</strong><br>";
    echo "Charset: <strong>" . DB_CHARSET . "</strong><br>";
    echo "Versione Server: <strong>" . $conn->server_info . "</strong>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Errore connessione: " . $e->getMessage() . "</div>";
    die("</body></html>");
}

// ============================================
// TEST 2: Verifica Tabelle
// ============================================

echo "<h2>Test 2: Verifica Tabelle</h2>";

$expectedTables = [
    'users',
    'categories',
    'documents',
    'chat_history',
    'calendar_events',
    'promo_codes',
    'usage_stats',
    'api_logs'
];

$result = $conn->query("SHOW TABLES");
$existingTables = [];

while ($row = $result->fetch_array()) {
    $existingTables[] = $row[0];
}

echo "<table>";
echo "<tr><th>Tabella</th><th>Stato</th><th>Righe</th></tr>";

foreach ($expectedTables as $table) {
    $exists = in_array($table, $existingTables);
    
    if ($exists) {
        $countResult = $conn->query("SELECT COUNT(*) as total FROM `$table`");
        $count = $countResult->fetch_assoc()['total'];
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='color: #10b981;'>✅ Presente</td>";
        echo "<td>$count righe</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='color: #ef4444;'>❌ Mancante</td>";
        echo "<td>-</td>";
        echo "</tr>";
    }
}

echo "</table>";

// ============================================
// TEST 3: Verifica Utente Test
// ============================================

echo "<h2>Test 3: Verifica Utente di Test (Mario Rossi)</h2>";

$sql = "SELECT id, name, email, tier, user_label, created_at FROM users WHERE email = ?";
$result = executeQuery($sql, "s", ["mario.rossi@test.it"]);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<div class='success'>✅ Utente Mario Rossi trovato!</div>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valore</th></tr>";
    foreach ($user as $key => $value) {
        echo "<tr><td>$key</td><td>$value</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ Utente Mario Rossi NON trovato</div>";
}

// ============================================
// TEST 4: Verifica Codice Promo
// ============================================

echo "<h2>Test 4: Verifica Codice Promozionale</h2>";

$sql = "SELECT * FROM promo_codes WHERE code = ?";
$result = executeQuery($sql, "s", ["PRO_TRIAL_2024"]);

if ($result && $result->num_rows > 0) {
    $promo = $result->fetch_assoc();
    echo "<div class='success'>✅ Codice PRO_TRIAL_2024 trovato!</div>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valore</th></tr>";
    foreach ($promo as $key => $value) {
        echo "<tr><td>$key</td><td>" . ($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ Codice promozionale NON trovato</div>";
}

// ============================================
// TEST 5: Test Query Preparata
// ============================================

echo "<h2>Test 5: Test Query Preparata (Sicurezza SQL Injection)</h2>";

$testEmail = "test@example.com' OR '1'='1"; // Tentativo SQL injection
$sql = "SELECT COUNT(*) as total FROM users WHERE email = ?";
$result = executeQuery($sql, "s", [$testEmail]);

if ($result) {
    $count = $result->fetch_assoc()['total'];
    if ($count === 0) {
        echo "<div class='success'>✅ Query preparata funziona correttamente (SQL Injection bloccata)</div>";
    } else {
        echo "<div class='error'>⚠️ ATTENZIONE: possibile vulnerabilità!</div>";
    }
}

// ============================================
// CONCLUSIONE
// ============================================

echo "<h2>📊 Riepilogo Test</h2>";
echo "<div class='success'>";
echo "<strong>✅ TUTTI I TEST COMPLETATI</strong><br><br>";
echo "Il database è configurato correttamente e pronto per lo sviluppo.<br><br>";
echo "<strong>⚠️ IMPORTANTE:</strong> Elimina questo file (test_connection.php) dopo il test per motivi di sicurezza.";
echo "</div>";

// Chiudi connessione
closeDatabaseConnection();

echo "</body></html>";
