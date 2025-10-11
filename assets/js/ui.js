<?php
declare(strict_types=1);

/**
 * _core/google_client.php
 * Versione completa e definitiva
 * ✅ Autoload robusto
 * ✅ Helpers inclusi
 * ✅ Factory del Google_Client
 * ✅ URL di autenticazione
 * ✅ Refresh token automatico
 * ✅ Funzione per restituire token aggiornati al DB
 */

// ======================================================
// 1) Autoload di Composer
// ======================================================
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Fallback se il progetto ha vendor/ due livelli sopra
    $fallback = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($fallback)) {
        require_once $fallback;
    } else {
        throw new RuntimeException(
            "❌ Composer autoload non trovato. Esegui 'composer install' nella root del progetto."
        );
    }
}

// ======================================================
// 2) Helpers (env_get)
// ======================================================
if (!function_exists('env_get')) {
    require_once __DIR__ . '/helpers.php';
}

use Google_Client;
use Google_Service_Calendar;

/**
 * Preleva le credenziali OAuth Google dal file .env
 *
 * @return array{clientId:string,clientSecret:string,redirectUri:string}
 */
function _googleEnv(): array
{
    $clientId     = (string)(env_get('GOOGLE_CLIENT_ID') ?? '');
    $clientSecret = (string)(env_get('GOOGLE_CLIENT_SECRET') ?? '');
    $redirectUri  = (string)(env_get('GOOGLE_REDIRECT_URI') ?? '');

    if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
        throw new InvalidArgumentException(
            '⚠️ Credenziali OAuth Google mancanti nel .env (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI).'
        );
    }

    return compact('clientId', 'clientSecret', 'redirectUri');
}

/**
 * Crea un oggetto Google_Client configurato per l'uso con Google Calendar.
 *
 * @param array<string,mixed>|null $oauth Array opzionale con token esistenti:
 *        [
 *          'access_token'      => 'ya29...',
 *          'refresh_token'     => '1//0g...',
 *          'access_expires_at' => '2025-10-11 20:31:00'
 *        ]
 * @return Google_Client
 */
function makeGoogleClientForUser(?array $oauth = null): Google_Client
{
    $env = _googleEnv();

    $client = new Google_Client();
    $client->setClientId($env['clientId']);
    $client->setClientSecret($env['clientSecret']);
    $client->setRedirectUri($env['redirectUri']);
    $client->setAccessType('offline'); // serve per ottenere refresh_token
    $client->setPrompt('consent');     // forza richiesta token aggiornato
    $client->setScopes([Google_Service_Calendar::CALENDAR]);

    // Se abbiamo token salvati, li carichiamo
    if ($oauth) {
        if (!empty($oauth['access_token'])) {
            $expiresIn = 3600; // default 1 ora
            if (!empty($oauth['access_expires_at'])) {
                $delta = strtotime((string)$oauth['access_expires_at']) - time();
                $expiresIn = max(1, (int)$delta);
            }

            $token = [
                'access_token'  => (string)$oauth['access_token'],
                'expires_in'    => $expiresIn,
                'created'       => time(),
            ];

            if (!empty($oauth['refresh_token'])) {
                $token['refresh_token'] = (string)$oauth['refresh_token'];
            }

            $client->setAccessToken($token);
        } elseif (!empty($oauth['refresh_token'])) {
            // Solo refresh_token presente → richiedi nuovo access token
            $client->fetchAccessTokenWithRefreshToken((string)$oauth['refresh_token']);
        }
    }

    // Se il token è scaduto e c'è un refresh_token, rigenera
    if ($client->isAccessTokenExpired() && $client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    }

    return $client;
}

/**
 * Restituisce l’URL da cui l’utente deve autorizzare l’accesso a Google.
 * Utile se non si dispone ancora di token salvati.
 *
 * @return string
 */
function makeGoogleAuthUrl(): string
{
    $env = _googleEnv();

    $client = new Google_Client();
    $client->setClientId($env['clientId']);
    $client->setClientSecret($env['clientSecret']);
    $client->setRedirectUri($env['redirectUri']);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setScopes([Google_Service_Calendar::CALENDAR]);

    return $client->createAuthUrl();
}

/**
 * Restituisce un array con i token aggiornati da salvare nel database,
 * se il client ha ottenuto un nuovo access_token o refresh_token.
 *
 * @param Google_Client $client
 * @return array<string,string>|null
 */
function googleUpdatedTokensOrNull(Google_Client $client): ?array
{
    $token = $client->getAccessToken();
    if (!is_array($token) || empty($token['access_token'])) {
        return null;
    }

    $created   = isset($token['created']) ? (int)$token['created'] : time();
    $expiresIn = isset($token['expires_in']) ? (int)$token['expires_in'] : 3600;
    $expiresAt = $created + $expiresIn;

    $out = [
        'access_token'      => (string)$token['access_token'],
        'access_expires_at' => date('Y-m-d H:i:s', $expiresAt),
    ];

    if (!empty($token['refresh_token'])) {
        $out['refresh_token'] = (string)$token['refresh_token'];
    } elseif ($client->getRefreshToken()) {
        // A volte Google non rimanda sempre il refresh_token, ma se il client lo ha, includilo
        $out['refresh_token'] = (string)$client->getRefreshToken();
    }

    return $out;
}

/**
 * Esempio di utilizzo (debug)
 *
 * try {
 *     $client = makeGoogleClientForUser($oauthFromDb);
 *     $service = new Google_Service_Calendar($client);
 *     $events = $service->events->listEvents('primary');
 *     var_dump($events);
 * } catch (Throwable $e) {
 *     error_log('Errore Google Client: ' . $e->getMessage());
 * }
 */
