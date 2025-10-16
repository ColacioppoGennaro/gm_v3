<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>🔍 Test Google OAuth Setup</h1>";

require_once __DIR__.'/_core/bootstrap.php';
require_once __DIR__.'/_core/helpers.php';

echo "<h2>1. Variabili .env Google</h2>";
echo "GOOGLE_CLIENT_ID: " . (env_get('GOOGLE_CLIENT_ID') ? '✅ PRESENTE' : '❌ MANCANTE') . "<br>";
echo "GOOGLE_CLIENT_SECRET: " . (env_get('GOOGLE_CLIENT_SECRET') ? '✅ PRESENTE' : '❌ MANCANTE') . "<br>";
echo "GOOGLE_REDIRECT_URI: " . (env_get('GOOGLE_REDIRECT_URI') ?: '❌ MANCANTE') . "<br>";

echo "<h2>2. Composer Autoload</h2>";
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
];

$found = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        echo "✅ Trovato: $path<br>";
        require_once $path;
        $found = true;
        break;
    } else {
        echo "❌ Non trovato: $path<br>";
    }
}

if (!$found) {
    echo "<br><strong style='color:red'>❌ COMPOSER NON INSTALLATO</strong><br>";
    echo "Esegui da SSH:<br><code>cd " . __DIR__ . " && composer install</code>";
} else {
    echo "<h2>3. Google Client Library</h2>";
    if (class_exists('Google_Client')) {
        echo "✅ Google_Client disponibile<br>";
        
        echo "<h2>4. Test Creazione Client</h2>";
        try {
            require_once __DIR__ . '/_core/google_client.php';
            $client = makeGoogleClientForUser(null);
            echo "✅ Client creato correttamente<br>";
            echo "Redirect URI: " . $client->getRedirectUri() . "<br>";
        } catch (Exception $e) {
            echo "❌ Errore: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Google_Client NON disponibile - Esegui composer install<br>";
    }
}
