<?php
/**
 * oauth/google/callback.php
 * ✅ VERSIONE CON DEBUG COMPLETO
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

session_start();

echo "<h1>🔍 Google OAuth Callback Debug</h1>";

echo "<h2>1. Sessione</h2>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NULL') . "<br>";
echo "Email: " . ($_SESSION['email'] ?? 'NULL') . "<br>";

if (!isset($_SESSION['user_id'])) {
    die('<p style="color:red">❌ Non sei loggato! <a href="../../index.php">Vai al login</a></p>');
}

$user_id = $_SESSION['user_id'];

echo "<h2>2. Parametri OAuth</h2>";
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    die("<p style='color:red'>❌ Errore OAuth: $error</p>");
}

if (!$code) {
    die('<p style="color:red">❌ Codice OAuth mancante! <a href="../../google_connect.php">Riprova</a></p>');
}

echo "Code ricevuto: " . substr($code, 0, 20) . "...<br>";

echo "<h2>3. Caricamento dipendenze</h2>";
require_once __DIR__ . '/../../_core/bootstrap.php';
echo "✅ Bootstrap caricato<br>";

require_once __DIR__ . '/../../_core/helpers.php';
echo "✅ Helpers caricato<br>";

if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    die('❌ vendor/autoload.php non trovato. Esegui composer install');
}
require_once __DIR__ . '/../../vendor/autoload.php';
echo "✅ Autoload caricato<br>";

if (!file_exists(__DIR__ . '/../../_core/google_client.php')) {
    die('❌ google_client.php non trovato');
}
require_once __DIR__ . '/../../_core/google_client.php';
echo "✅ Google client caricato<br>";

echo "<h2>4. Creazione Google Client</h2>";
try {
    $client = makeGoogleClientForUser(null);
    echo "✅ Client creato<br>";
} catch (Exception $e) {
    die("<p style='color:red'>❌ Errore creazione client: " . $e->getMessage() . "</p>");
}

echo "<h2>5. Exchange code per token</h2>";
try {
    $token = $client->fetchAccessTokenWithAuthCode($code);
    
    if (isset($token['error'])) {
        die("<p style='color:red'>❌ Errore OAuth: " . print_r($token, true) . "</p>");
    }
    
    echo "✅ Token ricevuto<br>";
    echo "<pre>" . print_r($token, true) . "</pre>";
    
    $access = $client->getAccessToken();
    $accessToken = $access['access_token'] ?? null;
    $expiresIn = $access['expires_in'] ?? 3600;
    $refreshToken = $client->getRefreshToken();
    
    echo "<h3>Token details:</h3>";
    echo "Access Token: " . ($accessToken ? substr($accessToken, 0, 30) . "..." : "NULL") . "<br>";
    echo "Refresh Token: " . ($refreshToken ? substr($refreshToken, 0, 30) . "..." : "NULL") . "<br>";
    echo "Expires in: $expiresIn secondi<br>";
    
} catch (Exception $e) {
    die("<p style='color:red'>❌ Errore exchange token: " . $e->getMessage() . "</p>");
}

echo "<h2>6. Controllo refresh token esistente</h2>";
if (!$refreshToken) {
    echo "⚠️ Refresh token non ricevuto, cerco nel DB...<br>";
    try {
        $db = db();
        $stmt = $db->prepare("SELECT google_oauth_refresh FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $refreshToken = $row['google_oauth_refresh'] ?? null;
        
        if ($refreshToken) {
            echo "✅ Refresh token trovato nel DB: " . substr($refreshToken, 0, 20) . "...<br>";
        } else {
            echo "⚠️ Nessun refresh token nel DB<br>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Errore DB: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>7. Calcolo scadenza</h2>";
$expiryDate = date('Y-m-d H:i:s', time() + $expiresIn);
echo "Token scade: $expiryDate<br>";

echo "<h2>8. Salvataggio nel database</h2>";
try {
    $db = db();
    $stmt = $db->prepare("UPDATE users SET google_oauth_token=?, google_oauth_refresh=?, google_oauth_expiry=? WHERE id=?");
    $stmt->bind_param("sssi", $accessToken, $refreshToken, $expiryDate, $user_id);
    
    if ($stmt->execute()) {
        echo "✅ Token salvato nel DB!<br>";
        echo "Righe modificate: " . $stmt->affected_rows . "<br>";
        
        // Verifica immediata
        $verify = $db->prepare("SELECT google_oauth_token IS NOT NULL as has_token, google_oauth_refresh IS NOT NULL as has_refresh FROM users WHERE id=?");
        $verify->bind_param("i", $user_id);
        $verify->execute();
        $check = $verify->get_result()->fetch_assoc();
        
        echo "<h3>Verifica:</h3>";
        echo "Access Token salvato: " . ($check['has_token'] ? '✅ SÌ' : '❌ NO') . "<br>";
        echo "Refresh Token salvato: " . ($check['has_refresh'] ? '✅ SÌ' : '❌ NO') . "<br>";
        
    } else {
        echo "<p style='color:red'>❌ Errore salvataggio: " . $stmt->error . "</p>";
    }
    
} catch (Exception $e) {
    die("<p style='color:red'>❌ Errore DB: " . $e->getMessage() . "</p>");
}

echo "<h2>✅ Successo!</h2>";
echo "<p><a href='../../index.php#/calendar'>→ Vai al Calendario</a></p>";
echo "<p><small>Tra 3 secondi verrai reindirizzato automaticamente...</small></p>";
echo "<script>setTimeout(() => window.location.href='../../index.php#/calendar', 3000);</script>";
