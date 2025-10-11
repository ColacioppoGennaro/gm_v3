<?php
/**
 * oauth/google/callback.php
 * ✅ CORRETTO con mysqli e tabella users
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../../_core/bootstrap.php';
require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../_core/google_client.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    die('Errore: devi essere loggato. <a href="../../index.php">Vai al login</a>');
}

$code = $_GET['code'] ?? null;

if (!$code) {
    die('Errore: codice OAuth mancante. <a href="../../google_connect.php">Riprova</a>');
}

try {
    $client = makeGoogleClientForUser(null);
    $token = $client->fetchAccessTokenWithAuthCode($code);
    
    if (isset($token['error'])) {
        throw new Exception('OAuth error: ' . $token['error']);
    }
    
    $access = $client->getAccessToken();
    $accessToken = $access['access_token'];
    $expiresIn = $access['expires_in'] ?? 3600;
    $refreshToken = $client->getRefreshToken();
    
    // Se non c'è refresh token, prendi quello esistente dal DB
    if (!$refreshToken) {
        $db = db();
        $stmt = $db->prepare("SELECT google_oauth_refresh FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $refreshToken = $row['google_oauth_refresh'] ?? null;
    }
    
    // Calcola data scadenza
    $expiryDate = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // Salva nel DB (tabella users)
    $db = db();
    $stmt = $db->prepare("UPDATE users SET google_oauth_token=?, google_oauth_refresh=?, google_oauth_expiry=? WHERE id=?");
    $stmt->bind_param("sssi", $accessToken, $refreshToken, $expiryDate, $user_id);
    $stmt->execute();
    
    error_log("Google OAuth success for user $user_id");
    
    // Redirect al calendario
    header('Location: ../../index.php#/calendar');
    exit;
    
} catch (Exception $e) {
    error_log("Google OAuth Error: " . $e->getMessage());
    die('Errore OAuth: ' . htmlspecialchars($e->getMessage()) . '<br><br>
        <a href="../../google_connect.php">← Torna indietro</a>');
}
