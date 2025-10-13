<?php
/**
 * api/google/events.php
 * Bridge Google Calendar (GET list, POST create/update, DELETE)
 * VERSIONE DEFINITIVA: Logica C.R.U.D. unificata e robusta.
 */

ini_set('display_errors', 0); // Disattiva errori HTML, gestiamo JSON
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

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['DELETE', 'PUT', 'PATCH'], true)) {
        $method = $override;
    }
}
$calendarId = $_GET['calendarId'] ?? 'primary';

try {
    $db = db();
    $stmt = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $oauth = $stmt->get_result()->fetch_assoc();

    if (!$oauth || empty($oauth['google_oauth_token'])) {
        throw new Exception('Token Google non trovato. Ricollega l\'account.', 401);
    }

    $client  = makeGoogleClientForUser([
        'access_token'      => $oauth['google_oauth_token'],
        'refresh_token'     => $oauth['google_oauth_refresh'] ?? null,
        'access_expires_at' => $oauth['google_oauth_expiry'] ?? null,
    ]);
    $service = new Google_Service_Calendar($client);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['error' => 'Errore di configurazione: ' . $e->getMessage()]);
    exit;
}

function read_input(): array {
    return $_POST;
}

function list_events($service, $calendarId) {
    $params  = [
        'singleEvents' => true, 
        'orderBy' => 'startTime',
        'timeMin' => $_GET['start'] ?? null,
        'timeMax' => $_GET['end'] ?? null
    ];
    $events = $service->events->listEvents($calendarId, array_filter($params));
    $out = [];
    foreach ($events->getItems() as $e) {
        $startObj = $e->getStart();
        $endObj   = $e->getEnd();
        $isAllDay = (bool)$startObj->getDate();
        
        $remindersArr = [];
        if (($remindersObj = $e->getReminders()) && $remindersObj->getOverrides()) {
            foreach ($remindersObj->getOverrides() as $override) {
                $remindersArr[] = ['method' => $override->getMethod(), 'minutes' => $override->getMinutes()];
            }
        }
        
        $out[] = [
            'id'            => $e->getId(),
            'title'         => $e->getSummary() ?: '(senza titolo)',
            'start'         => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
            'end'           => $isAllDay ? $endObj->getDate()   : $endObj->getDateTime(),
            'allDay'        => $isAllDay,
            'extendedProps' => [
                'description' => $e->getDescription(),
                'recurrence'  => $e->getRecurrence(),
                'reminders'   => json_encode($remindersArr)
            ],
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

    $isAllDay = isset($in['allDay']) && $in['allDay'] === '1';
    if ($isAllDay) {
        if (!empty($in['startDate'])) $event->setStart(new Google_Service_Calendar_EventDateTime(['date' => $in['startDate']]));
        if (!empty($in['endDate'])) $event->setEnd(new Google_Service_Calendar_EventDateTime(['date' => $in['endDate']]));
    } else {
        $timeZone = $in['timeZone'] ?? 'Europe/Rome';
        if (!empty($in['startDateTime'])) $event->setStart(new Google_Service_Calendar_EventDateTime(['dateTime' => $in['startDateTime'], 'timeZone' => $timeZone]));
        if (!empty($in['endDateTime'])) $event->setEnd(new Google_Service_Calendar_EventDateTime(['dateTime' => $in['endDateTime'], 'timeZone' => $timeZone]));
    }

    if (array_key_exists('recurrence', $in)) {
        $event->setRecurrence(!empty($in['recurrence']) ? [$in['recurrence']] : []);
    }
    
    if (array_key_exists('reminders', $in)) {
        $remindersData = is_string($in['reminders']) ? json_decode($in['reminders'], true) : [];
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
    
    $result = $eventId ? $service->events->update($calendarId, $eventId, $event) : $service->events->insert($calendarId, $event);
    echo json_encode(['id' => $result->getId()]);
}

function delete_event($service, $calendarId) {
    $eventId = $_GET['id'] ?? null;
    if (!$eventId) throw new Exception('eventId mancante', 400);
    $service->events->delete($calendarId, $eventId);
    echo json_encode(['ok' => true]);
}

try {
    switch ($method) {
        case 'GET': list_events($service, $calendarId); break;
        case 'POST': 
        case 'PUT':
        case 'PATCH': create_or_update_event($service, $calendarId); break;
        case 'DELETE': delete_event($service, $calendarId); break;
        default: throw new Exception('Metodo non supportato', 405);
    }
} catch (Exception $e) {
    $code = is_int($e->getCode()) && $e->getCode() > 200 ? $e->getCode() : 500;
    http_response_code($code);
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

