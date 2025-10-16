<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__.'/_core/bootstrap.php';
require_once __DIR__.'/_core/helpers.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/_core/google_client.php';

echo "<h1>🔍 Test Redirect URI</h1>";

echo "<h2>1. Nel .env:</h2>";
echo "<code>" . htmlspecialchars(env_get('GOOGLE_REDIRECT_URI')) . "</code><br><br>";

echo "<h2>2. Client ID:</h2>";
echo "<code>" . htmlspecialchars(env_get('GOOGLE_CLIENT_ID')) . "</code><br><br>";

try {
    $client = makeGoogleClientForUser(null);
    
    echo "<h2>3. Redirect URI inviato a Google:</h2>";
    echo "<code>" . htmlspecialchars($client->getRedirectUri()) . "</code><br><br>";
    
    echo "<h2>4. URL Auth completo:</h2>";
    $authUrl = $client->createAuthUrl();
    echo "<textarea style='width:100%;height:100px'>" . htmlspecialchars($authUrl) . "</textarea><br><br>";
    
    echo "<h3>✅ Copia ESATTAMENTE questo redirect URI in Google Console:</h3>";
    echo "<strong style='color:green;font-size:18px'>" . htmlspecialchars($client->getRedirectUri()) . "</strong>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Errore: " . htmlspecialchars($e->getMessage()) . "</p>";
}
