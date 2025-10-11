<?php
/**
 * api/google/events.php
 * ✅ Gestisce eventi Google Calendar con mysqli
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carica dipendenze
require_once __DIR__ . '/../../_core/helpers.php';

// Verifica se google_client.php esiste
if (!file_exists(__DIR__ . '/../../_core/google_client.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Google Client non configurato']);
    exit;
}

require_once __DIR__ . '/../../_core/google_client.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$calendarId = $_GET['calendarId'] ?? $_POST['calendarId'] ?? null;

if (!$calendarId) { 
    http_response_code(400); 
    echo json_encode(['error' => 'calendarId required']);
    exit; 
}

// ✅ Ottieni OAuth con mysqli
$db = db();
$stmt = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$oauth = $result->fetch_assoc();

if (!$oauth || !$oauth['google_oauth_token']) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Google non trovato. Collega il tuo account Google.']);
    exit;
}

// Converti in formato array per makeGoogleClientForUser
$oauthFormatted = [
    'access_token' => $oauth['google_oauth_token'],
    'refresh_token' => $oauth['google_oauth_refresh'],
    'access_expires_at' => $oauth['google_oauth_expiry']
];

try {
    $client = makeGoogleClientForUser($oauthFormatted);
    $service = new Google_Service_Calendar($client);
} catch (Exception $e) {
    error_log("Google Client Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore Google Client: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

try {
    switch ($method) {
        case 'GET':
            $timeMin = $_GET['start'] ?? null;
            $timeMax = $_GET['end'] ?? null;
            $params = ['singleEvents' => true, 'orderBy' => 'startTime'];
            if ($timeMin) $params['timeMin'] = $timeMin;
            if ($timeMax) $params['timeMax'] = $timeMax;

            $events = $service->events->listEvents($calendarId, $params);
            $out = [];
            foreach ($events->getItems() as $e) {
                $out[] = [
                    'id'    => $e->getId(),
                    'title' => $e->getSummary() ?: '(senza titolo)',
                    'start' => $e->getStart()->getDateTime() ?: $e->getStart()->getDate(),
                    'end'   => $e->getEnd()->getDateTime() ?: $e->getEnd()->getDate(),
                    'allDay' => (bool)$e->getStart()->getDate(),
                ];
            }
            echo json_encode($out);
            break;

        case 'POST':
            $payload = json_decode(file_get_contents('php://input'), true);
            $ev = new Google_Service_Calendar_Event([
                'summary' => $payload['title'] ?? '',
                'description' => $payload['description'] ?? '',
                'start' => ['dateTime' => $payload['start']],
                'end'   => ['dateTime' => $payload['end']],
                'attendees' => array_map(fn($m) => ['email' => $m], $payload['attendees'] ?? []),
                'recurrence' => !empty($payload['rrule']) ? [$payload['rrule']] : [],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => array_map(fn($m) => ['method' => $m['method'], 'minutes' => $m['minutes']], $payload['reminders'] ?? [])
                ],
            ]);
            $created = $service->events->insert($calendarId, $ev);
            echo json_encode(['id' => $created->getId()]);
            break;

        case 'PATCH':
            $id = $_GET['id'] ?? null;
            if (!$id) { 
                http_response_code(400); 
                echo json_encode(['error' => 'id required']);
                exit; 
            }
            $payload = json_decode(file_get_contents('php://input'), true);
            $ev = $service->events->get($calendarId, $id);
            if (isset($payload['title'])) $ev->setSummary($payload['title']);
            if (isset($payload['description'])) $ev->setDescription($payload['description']);
            if (isset($payload['start'])) $ev->setStart(new Google_Service_Calendar_EventDateTime(['dateTime' => $payload['start']]));
            if (isset($payload['end'])) $ev->setEnd(new Google_Service_Calendar_EventDateTime(['dateTime' => $payload['end']]));
            $service->events->update($calendarId, $id, $ev);
            http_response_code(204);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) { 
                http_response_code(400); 
                echo json_encode(['error' => 'id required']);
                exit; 
            }
            $service->events->delete($calendarId, $id);
            http_response_code(204);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non supportato']);
    }
} catch (Exception $e) {
    error_log("Google Events API Error: " . $e->getMessage());
    error_log("Full Google API Error: " . print_r($e, true));
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
