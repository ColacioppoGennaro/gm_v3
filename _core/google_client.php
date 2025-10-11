<?php
/**
 * _core/google_client.php
 * ✅ CORRETTO con error handling
 */

// Verifica se Composer autoload esiste
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php'
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    throw new Exception('Composer autoload non trovato. Esegui: composer install');
}

// Carica helpers per env_get()
if (!function_exists('env_get')) {
    require_once __DIR__ . '/helpers.php';
}

function makeGoogleClientForUser(?array $oauth = null): Google_Client {
    $clientId = env_get('GOOGLE_CLIENT_ID');
    $clientSecret = env_get('GOOGLE_CLIENT_SECRET');
    $redirectUri = env_get('GOOGLE_REDIRECT_URI');
    
    if (!$clientId || !$clientSecret || !$redirectUri) {
        throw new Exception('Credenziali OAuth Google mancanti nel .env');
    }
    
    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setRedirectUri($redirectUri);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setScopes([Google_Service_Calendar::CALENDAR]);
    
    if ($oauth && !empty($oauth['access_token'])) {
        $expiresIn = 3600; // Default 1 ora
        if (isset($oauth['access_expires_at'])) {
            $expiresIn = max(1, strtotime($oauth['access_expires_at']) - time());
        }
        
        $client->setAccessToken([
            'access_token' => $oauth['access_token'],
            'expires_in'   => $expiresIn,
            'created'      => time(),
            'refresh_token'=> $oauth['refresh_token'] ?? null
        ]);
    } elseif ($oauth && !empty($oauth['refresh_token'])) {
        $client->refreshToken($oauth['refresh_token']);
    }
    
    return $client;
}
