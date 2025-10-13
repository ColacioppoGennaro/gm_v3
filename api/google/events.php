<?php
/**
 * api/google/events.php
 * Bridge Google Calendar (GET list, POST create/update, DELETE)
 * CORRETTO: Gestisce correttamente update vs create e restituisce tutti i dati necessari.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// ---- Dipendenze
require_once __DIR__ . '/../../_core/helpers.php';
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

// ---- Metodo (supporta override POST _method=DELETE)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['DELETE', 'PUT', 'PATCH'], true)) {
        $method = $override;
    }
}

$calendarId = $_GET['calendarId'] ?? 'primary';

// ---- OAuth da DB
$db = db();
$stmt = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$oauth = $stmt->get_result()->fetch_assoc();

if (!$oauth || empty($oauth['google_oauth_token'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Google non trovato. Ricollega l\'account.']);
    exit;
}

$oauthFormatted = [
    'access_token'      => $oauth['google_oauth_token'],
    'refresh_token'     => $oauth['google_oauth_refresh'] ?? null,
    'access_expires_at' => $oauth['google_oauth_expiry'] ?? null,
];

try {
    $client  = makeGoogleClientForUser($oauthFormatted);
    $service = new Google_Service_Calendar($client);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore Google Client: ' . $e->getMessage()]);
    exit;
}

// ---- Helper input: JSON o FormData
function read_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false && $raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return $_POST;
}

// ---- Funzioni Logiche per C.R.U.D ---

function list_events($service, $calendarId) {
    $timeMin = $_GET['start'] ?? null;
    $timeMax = $_GET['end'] ?? null;
    $params  = ['singleEvents' => true, 'orderBy' => 'startTime'];
    if ($timeMin) $params['timeMin'] = $timeMin;
    if ($timeMax) $params['timeMax'] = $timeMax;

    $events = $service->events->listEvents($calendarId, $params);
    $out = [];
    foreach ($events->getItems() as $e) {
        $startObj = $e->getStart();
        $endObj   = $e->getEnd();
        $isAllDay = (bool)$startObj->getDate();

        // ✅ BUG FIX: Estrai e formatta correttamente i promemoria per il frontend
        $remindersObj = $e->getReminders();
        $remindersArr = [];
        if ($remindersObj && $remindersObj->getOverrides()) {
            foreach ($remindersObj->getOverrides() as $override) {
                $remindersArr[] = [
                    'method' => $override->getMethod(),
                    'minutes' => $override->getMinutes()
                ];
            }
        }
        
        $extendedProps = [
            'description' => $e->getDescription(),
            'recurrence'  => $e->getRecurrence(),
            'reminders'   => json_encode($remindersArr) // Ora invia un JSON valido
        ];

        $out[] = [
            'id'            => $e->getId(),
            'title'         => $e->getSummary() ?: '(senza titolo)',
            'start'         => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
            'end'           => $isAllDay ? $endObj->getDate()   : $endObj->getDateTime(),
            'allDay'        => $isAllDay,
            'extendedProps' => $extendedProps,
        ];
    }
    echo json_encode($out);
}

function create_or_update_event($service, $calendarId) {
    $eventId = $_GET['id'] ?? null;
    $in = read_input();
    
    $event = $eventId ? $service->events->get($calendarId, $eventId) : new Google_Service_Calendar_Event();

    if (isset($in['title'])) $event->setSummary($in['title']);
    if (isset($in['description'])) $event->setDescription($in['description']);

    $isAllDay = isset($in['allDay']) && ($in['allDay'] === '1' || $in['allDay'] === 1);
    if ($isAllDay) {
        if (!empty($in['startDate'])) $event->setStart(new Google_Service_Calendar_EventDateTime(['date' => $in['startDate']]));
        if (!empty($in['endDate'])) $event->setEnd(new Google_Service_Calendar_EventDateTime(['date' => $in['endDate']]));
    } else {
        $timeZone = $in['timeZone'] ?? 'Europe/Rome';
        if (!empty($in['startDateTime'])) $event->setStart(new Google_Service_Calendar_EventDateTime(['dateTime' => $in['startDateTime'], 'timeZone' => $timeZone]));
        if (!empty($in['endDateTime'])) $event->setEnd(new Google_Service_Calendar_EventDateTime(['dateTime' => $in['endDateTime'], 'timeZone' => $timeZone]));
    }

    // ✅ BUG FIX: Gestisci correttamente la cancellazione della ricorrenza
    if (isset($in['recurrence'])) {
        $rrule = $in['recurrence'];
        $event->setRecurrence($rrule ? [$rrule] : []); // Usa un array vuoto per cancellare
    }
    
    if (isset($in['reminders'])) {
        $remindersData = is_string($in['reminders']) ? json_decode($in['reminders'], true) : $in['reminders'];
        $overrides = [];
        if (is_array($remindersData)) {
            foreach ($remindersData as $r) {
                if (isset($r['method'], $r['minutes'])) {
                    $overrides[] = ['method' => $r['method'], 'minutes' => (int)$r['minutes']];
                }
            }
        }
        $event->setReminders(['useDefault' => false, 'overrides' => $overrides]);
    }
    
    if ($eventId) {
        $updatedEvent = $service->events->update($calendarId, $eventId, $event);
        echo json_encode(['id' => $updatedEvent->getId()]);
    } else {
        $createdEvent = $service->events->insert($calendarId, $event);
        echo json_encode(['id' => $createdEvent->getId()]);
    }
}

function delete_event($service, $calendarId) {
    $eventId = $_GET['id'] ?? null;
    if (!$eventId) {
        http_response_code(400);
        echo json_encode(['error' => 'eventId mancante']);
        exit;
    }
    $service->events->delete($calendarId, $eventId);
    echo json_encode(['ok' => true]);
}

// ---- Router ----
try {
    switch ($method) {
        case 'GET':
            list_events($service, $calendarId);
            break;

        case 'POST':
        case 'PUT':
        case 'PATCH':
            create_or_update_event($service, $calendarId);
            break;

        case 'DELETE':
            delete_event($service, $calendarId);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non supportato']);
    }
} catch (Exception $e) {
    error_log("Google Events API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

