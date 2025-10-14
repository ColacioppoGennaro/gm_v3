<?php
/**
 * api/google/events.php
 * Bridge Google Calendar (GET list, POST create/update, DELETE)
 * ✅ CORRETTO: Gestione UPDATE, promemoria e ricorrenza
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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

// ---- calendarId in querystring (obbligatorio)
$calendarId = $_GET['calendarId'] ?? null;
if (!$calendarId) {
    http_response_code(400);
    echo json_encode(['error' => 'calendarId required']);
    exit;
}

// ---- OAuth da DB
$db = db();
$stmt = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$oauth = $result->fetch_assoc();

error_log("OAuth user $user_id: access=" . (!empty($oauth['google_oauth_token']) ? 'YES' : 'NO') .
          " refresh=" . (!empty($oauth['google_oauth_refresh']) ? 'YES' : 'NO') .
          " expiry=" . ($oauth['google_oauth_expiry'] ?? 'NULL'));

if (!$oauth || empty($oauth['google_oauth_token'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Google non trovato. Ricollega l\'account in google_connect.php']);
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
    error_log("Google Client Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore Google Client: ' . $e->getMessage()]);
    exit;
}

// ---- Helper input: JSON o FormData
function read_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJSON = stripos($ct, 'application/json') !== false;

    if ($isJSON && $raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    // FormData / x-www-form-urlencoded
    return $_POST;
}

// ---- Helper: costruisce EventDateTime (all-day vs timed)
function buildEventDateTime($isAllDay, $dateOrDateTime, $timeZone = null) {
    if ($isAllDay) {
        return new Google_Service_Calendar_EventDateTime([
            'date' => $dateOrDateTime, // YYYY-MM-DD (end esclusivo gestito dal front)
        ]);
    } else {
        $arr = ['dateTime' => $dateOrDateTime];
        if ($timeZone) $arr['timeZone'] = $timeZone;
        return new Google_Service_Calendar_EventDateTime($arr);
    }
}

// ---- Router
try {
    switch ($method) {
        // =========================
        // LISTA EVENTI (GET) - ✅ FIX: Recupera promemoria e ricorrenza
        // =========================
        case 'GET': {
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
                $isAllDay = (bool)$startObj->getDate(); // se c'è 'date' è all-day

                // ✅ FIX: Recupera promemoria
                $reminders = [];
                $remObj = $e->getReminders();
                if ($remObj && !$remObj->getUseDefault()) {
                    $overrides = $remObj->getOverrides();
                    if ($overrides) {
                        foreach ($overrides as $o) {
                            $reminders[] = [
                                'method' => $o->getMethod(),
                                'minutes' => $o->getMinutes()
                            ];
                        }
                    }
                }

                // ✅ FIX: Recupera ricorrenza
                $recurrence = $e->getRecurrence();
                $rrule = null;
                if ($recurrence && is_array($recurrence) && count($recurrence) > 0) {
                    $rrule = $recurrence[0]; // Es: "RRULE:FREQ=DAILY;COUNT=5"
                }

                $out[] = [
                    'id'     => $e->getId(),
                    'title'  => $e->getSummary() ?: '(senza titolo)',
                    'start'  => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
                    'end'    => $isAllDay ? $endObj->getDate()   : $endObj->getDateTime(),
                    'allDay' => $isAllDay,
                    'extendedProps' => [
                        'description' => $e->getDescription() ?: '',
                        'reminders'   => ['overrides' => $reminders],
                        'recurrence'  => $rrule ? [$rrule] : null,
                    ]
                ];
            }
            echo json_encode($out);
            break;
        }

        // =========================
        // CREA EVENTO (POST senza id)
        // =========================
        case 'POST': {
            // ✅ FIX: Se c'è 'id' in query o body, è un UPDATE
            $eventId = $_GET['id'] ?? null;
            if (!$eventId) {
                $in = read_input();
                $eventId = $in['id'] ?? null;
            }
            
            if ($eventId) {
                // È un UPDATE mascherato da POST
                $_GET['id'] = $eventId;
                goto update_event;
            }

            // --- CREAZIONE NUOVO EVENTO ---
            $in = read_input();
            
            $title       = $in['title'] ?? ($in['summary'] ?? '');
            $description = $in['description'] ?? '';
            $allDayFlag  = isset($in['allDay']) ? (int)$in['allDay'] : null;

            // Promemoria
            $remOverrides = [];
            if (isset($in['reminders'])) {
                $rem = $in['reminders'];
                if (is_string($rem)) {
                    $rem = json_decode($rem, true);
                }
                if (is_array($rem)) {
                    // può arrivare {overrides:[...]} o direttamente [...]
                    $over = $rem['overrides'] ?? $rem;
                    foreach ($over as $r) {
                        if (!isset($r['method']) || !isset($r['minutes'])) continue;
                        $remOverrides[] = ['method' => $r['method'], 'minutes' => (int)$r['minutes']];
                    }
                }
            }

            // Ricorrenza (accetta sia 'rrule' che 'recurrence')
            $rrule = $in['rrule'] ?? $in['recurrence'] ?? null;
            if ($rrule) {
                // Accetta sia "FREQ=DAILY" sia "RRULE:FREQ=DAILY"
                if (stripos($rrule, 'RRULE:') !== 0) {
                    $rrule = 'RRULE:' . $rrule;
                }
            }

            // All-day vs timed
            $isAllDay = ($allDayFlag === 1 || $allDayFlag === '1');

            if ($isAllDay) {
                // startDate / endDate (end ESCLUSIVO, già calcolato dal front)
                $startDate = $in['startDate'] ?? null;
                $endDate   = $in['endDate']   ?? null;
                if (!$startDate || !$endDate) {
                    http_response_code(400);
                    echo json_encode(['error' => 'startDate/endDate required for all-day']);
                    exit;
                }

                $ev = new Google_Service_Calendar_Event([
                    'summary'     => $title,
                    'description' => $description,
                    'start'       => ['date' => $startDate],
                    'end'         => ['date' => $endDate],
                    'reminders'   => ['useDefault' => false, 'overrides' => $remOverrides],
                ]);
            } else {
                // startDateTime / endDateTime (+ timeZone)
                $startDT  = $in['startDateTime'] ?? ($in['start'] ?? null);
                $endDT    = $in['endDateTime']   ?? ($in['end']   ?? null);
                $timeZone = $in['timeZone']      ?? 'Europe/Rome';

                if (!$startDT || !$endDT) {
                    http_response_code(400);
                    echo json_encode(['error' => 'startDateTime/endDateTime required']);
                    exit;
                }

                $ev = new Google_Service_Calendar_Event([
                    'summary'     => $title,
                    'description' => $description,
                    'start'       => ['dateTime' => $startDT, 'timeZone' => $timeZone],
                    'end'         => ['dateTime' => $endDT,   'timeZone' => $timeZone],
                    'reminders'   => ['useDefault' => false, 'overrides' => $remOverrides],
                ]);
            }

            if ($rrule) $ev->setRecurrence([$rrule]);

            // Eventuali invitati (accetta array o stringa con ,)
            if (!empty($in['attendees'])) {
                $attRaw = $in['attendees'];
                if (is_string($attRaw)) {
                    $attRaw = array_filter(array_map('trim', explode(',', $attRaw)));
                }
                if (is_array($attRaw)) {
                    $att = [];
                    foreach ($attRaw as $a) {
                        if (is_array($a) && !empty($a['email'])) $att[] = ['email' => $a['email']];
                        elseif (is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)) $att[] = ['email' => $a];
                    }
                    if ($att) $ev->setAttendees($att);
                }
            }

            $created = $service->events->insert($calendarId, $ev);
            echo json_encode(['id' => $created->getId()]);
            break;
        }

        // =========================
        // AGGIORNA EVENTO (POST/PUT/PATCH con id)
        // =========================
        case 'PUT':
        case 'PATCH':
        update_event: // ✅ Label per POST con id
            
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required for update']);
                exit;
            }

            $in = read_input();

            // Recupera evento esistente
            try {
                $ev = $service->events->get($calendarId, $id);
            } catch (Exception $e) {
                http_response_code(404);
                echo json_encode(['error' => 'Evento non trovato: ' . $e->getMessage()]);
                exit;
            }

            // Titolo / descrizione
            if (isset($in['title']))       $ev->setSummary($in['title']);
            if (isset($in['description'])) $ev->setDescription($in['description']);

            // Riconosci all-day toggle
            $allDayFlag = isset($in['allDay']) ? (int)$in['allDay'] : null;
            $isAllDay   = ($allDayFlag === 1 || $allDayFlag === '1');

            if ($isAllDay) {
                if (!empty($in['startDate'])) {
                    $ev->setStart(buildEventDateTime(true, $in['startDate']));
                }
                if (!empty($in['endDate'])) {
                    $ev->setEnd(buildEventDateTime(true, $in['endDate']));
                }
            } else {
                // timed
                $startDT  = $in['startDateTime'] ?? ($in['start'] ?? null);
                $endDT    = $in['endDateTime']   ?? ($in['end']   ?? null);
                $timeZone = $in['timeZone']      ?? 'Europe/Rome';

                if ($startDT) $ev->setStart(buildEventDateTime(false, $startDT, $timeZone));
                if ($endDT)   $ev->setEnd(buildEventDateTime(false, $endDT,   $timeZone));
            }

            // ✅ FIX: Promemoria - gestione corretta con oggetto Google
            if (isset($in['reminders'])) {
                $rem = $in['reminders'];
                if (is_string($rem)) $rem = json_decode($rem, true);
                $overrides = [];
                
                if (is_array($rem)) {
                    $raw = $rem['overrides'] ?? $rem;
                    foreach ($raw as $r) {
                        if (!isset($r['method']) || !isset($r['minutes'])) continue;
                        $overrides[] = new Google_Service_Calendar_EventReminder([
                            'method'  => $r['method'], 
                            'minutes' => (int)$r['minutes']
                        ]);
                    }
                }
                
                $reminderObj = new Google_Service_Calendar_EventReminders();
                $reminderObj->setUseDefault(false);
                $reminderObj->setOverrides($overrides);
                $ev->setReminders($reminderObj);
            }

            // Ricorrenza (accetta sia 'rrule' che 'recurrence')
            if (isset($in['rrule']) || isset($in['recurrence'])) {
                $rr = $in['rrule'] ?? $in['recurrence'];
                if ($rr && stripos($rr, 'RRULE:') !== 0) $rr = 'RRULE:' . $rr;
                $ev->setRecurrence($rr ? [$rr] : []);
            }

            $service->events->update($calendarId, $id, $ev);
            echo json_encode(['ok' => true]);
            break;

        // =========================
        // ELIMINA EVENTO (DELETE o POST _method=DELETE)
        // =========================
        case 'DELETE': {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                exit;
            }
            $service->events->delete($calendarId, $id);
            echo json_encode(['ok' => true]);
            break;
        }

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non supportato']);
    }
} catch (Exception $e) {
    error_log("Google Events API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
