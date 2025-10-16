<?php
/**
 * oauth/google/login.php
 * ✅ CORRETTO con error handling
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../../_core/bootstrap.php';
require_once __DIR__ . '/../../_core/helpers.php';

// Verifica che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    die('Devi essere loggato per collegare Google Calendar. <a href="../../index.php">Torna al login</a>');
}

// Controlla se il file google_client.php esiste
if (!file_exists(__DIR__ . '/../../_core/google_client.php')) {
    die('Errore: google_client.php non trovato. Verifica la configurazione.');
}

require_once __DIR__ . '/../../_core/google_client.php';

// Verifica variabili .env
$clientId = env_get('GOOGLE_CLIENT_ID');
$clientSecret = env_get('GOOGLE_CLIENT_SECRET');
$redirectUri = env_get('GOOGLE_REDIRECT_URI');

if (!$clientId || !$clientSecret || !$redirectUri) {
    die('Errore: Credenziali OAuth Google mancanti nel .env<br>
        GOOGLE_CLIENT_ID: ' . ($clientId ? 'OK' : 'MANCANTE') . '<br>
        GOOGLE_CLIENT_SECRET: ' . ($clientSecret ? 'OK' : 'MANCANTE') . '<br>
        GOOGLE_REDIRECT_URI: ' . ($redirectUri ? 'OK' : 'MANCANTE'));
}

try {
    $client = makeGoogleClientForUser(null);
    $authUrl = $client->createAuthUrl();
    
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
} catch (Exception $e) {
    die('Errore Google Client: ' . htmlspecialchars($e->getMessage()) . '<br><br>
        <a href="../../google_connect.php">← Torna indietro</a>');
}
