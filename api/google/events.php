<?php
/**
 * api/google/events.php (drop-in)
 * Bridge Google Calendar: LIST (giorno/mese) + GET singolo evento + CREATE/UPDATE + DELETE
 *
 * Correzioni principali:
 * - ROUTING POST: non crea più duplicati. Se c'è id (query) o action=update/eventId -> UPDATE, altrimenti CREATE.
 * - GET singolo: se passi ?id=... torna l'evento completo (reminders, recurrence, extendedProperties) per ricaricare i promemoria nel modal.
 * - Promemoria: accetta sia `reminders` (JSON) sia `reminders_overrides[...]` da FormData.
 * - Ricorrenza: accetta `recurrence` (string/array) o `rrule` (string), normalizza a array RRULE:...
 * - extendedProperties: se arrivano, le applica su create/update.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

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

// Metodo (supporta override _method)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['DELETE','PUT','PATCH'], true)) {
        $method = $override;
    }
}

// calendarId richiesto
$calendarId = $_GET['calendarId'] ?? null;
if (!$calendarId) {
    http_response_code(400);
    echo json_encode(['error' => 'calendarId required']);
    exit;
}

// OAuth
$db = db();
$stmt = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$oauth = $stmt->get_result()->fetch_assoc();
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
    http_response_code(500);
    echo json_encode(['error' => 'Errore Google Client: ' . $e->getMessage()]);
    exit;
}

// Helpers ---------------------------------------------------------
function read_input(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';
    if (stripos($ct, 'application/json') !== false && $raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return $_POST; // FormData/x-www-form-urlencoded
}

function normalize_rrule($in): array {
    // Supporta: recurrence (string | array), rrule (string)
    $out = [];
    if (isset($in['recurrence'])) {
        $rec = $in['recurrence'];
        if (is_string($rec)) { $rec = [$rec]; }
        if (is_array($rec)) {
            foreach ($rec as $r) {
                if (!$r) continue;
                $out[] = (stripos($r, 'RRULE:') === 0) ? $r : ('RRULE:' . $r);
            }
        }
    } elseif (!empty($in['rrule']) && is_string($in['rrule'])) {
        $out[] = (stripos($in['rrule'], 'RRULE:') === 0) ? $in['rrule'] : ('RRULE:' . $in['rrule']);
    }
    return $out;
}

function extract_reminders(array $in): array {
    // 1) Campo JSON "reminders" ([{method,minutes}] | {overrides:[...]})
    if (isset($in['reminders'])) {
        $rem = $in['reminders'];
        if (is_string($rem)) $rem = json_decode($rem, true);
        if (is_array($rem)) {
            $over = $rem['overrides'] ?? $rem;
            $norm = [];
            foreach ($over as $r) {
                if (!isset($r['method']) || !isset($r['minutes'])) continue;
                $norm[] = ['method' => $r['method'], 'minutes' => (int)$r['minutes']];
            }
            return $norm;
        }
    }
    // 2) FormData reminders_overrides[0][method]/[minutes]
    $norm = [];
    if (isset($in['reminders_overrides']) && is_array($in['reminders_overrides'])) {
        foreach ($in['reminders_overrides'] as $r) {
            if (!is_array($r)) continue;
            if (!isset($r['method']) || !isset($r['minutes'])) continue;
            $norm[] = ['method' => $r['method'], 'minutes' => (int)$r['minutes']];
        }
    }
    return $norm;
}

function set_extended_properties(Google_Service_Calendar_Event $ev, array $in): void {
    if (!empty($in['extendedProperties']) && is_array($in['extendedProperties'])) {
        // Rispetta sia extendedProperties[private][key] che [shared]
        $ep = [];
        if (isset($in['extendedProperties']['private']) && is_array($in['extendedProperties']['private'])) {
            $ep['private'] = $in['extendedProperties']['private'];
        }
        if (isset($in['extendedProperties']['shared']) && is_array($in['extendedProperties']['shared'])) {
            $ep['shared'] = $in['extendedProperties']['shared'];
        }
        if ($ep) $ev->setExtendedProperties($ep);
    }
}

function event_to_payload(Google_Service_Calendar_Event $e): array {
    $startObj = $e->getStart();
    $endObj   = $e->getEnd();
    $isAllDay = (bool)$startObj->getDate();

    // Reminders in output (overrides semplice)
    $rem = $e->getReminders();
    $remOut = null;
    if ($rem instanceof Google_Service_Calendar_EventReminders) {
        $ov = $rem->getOverrides();
        if (is_array($ov)) {
            $tmp = [];
            foreach ($ov as $r) {
                $tmp[] = ['method' => $r->getMethod(), 'minutes' => (int)$r->getMinutes()];
            }
            $remOut = ['useDefault' => (bool)$rem->getUseDefault(), 'overrides' => $tmp];
        } else {
            $remOut = ['useDefault' => (bool)$rem->getUseDefault(), 'overrides' => []];
        }
    }

    return [
        'id'     => $e->getId(),
        'title'  => $e->getSummary() ?: '(senza titolo)',
        'start'  => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
        'end'    => $isAllDay ? $endObj->getDate()   : $endObj->getDateTime(),
        'allDay' => $isAllDay,
        'extendedProps' => [
            'description' => $e->getDescription(),
            'recurrence'  => $e->getRecurrence(),
            'reminders'   => $remOut,
            'extendedProperties' => $e->getExtendedProperties(),
            'location'    => $e->getLocation(),
        ],
    ];
}

// Router ----------------------------------------------------------
try {
    if ($method === 'GET') {
        // GET singolo evento se presente id
        if (!empty($_GET['id'])) {
            $id = $_GET['id'];
            $ev = $service->events->get($calendarId, $id);
            echo json_encode(event_to_payload($ev));
            exit;
        }
        // Altrimenti lista
        $timeMin = $_GET['start'] ?? null;
        $timeMax = $_GET['end'] ?? null;
        $params  = ['singleEvents' => true, 'orderBy' => 'startTime'];
        if ($timeMin) $params['timeMin'] = $timeMin;
        if ($timeMax) $params['timeMax'] = $timeMax;

        $events = $service->events->listEvents($calendarId, $params);
        $out = [];
        foreach ($events->getItems() as $e) { $out[] = event_to_payload($e); }
        echo json_encode($out);
        exit;
    }

    // CREATE o UPDATE via POST/PUT/PATCH
    if (in_array($method, ['POST','PUT','PATCH'], true)) {
        $in = read_input();

        // Decide operation
        $idQuery  = $_GET['id'] ?? null;
        $action   = isset($in['action']) ? strtolower((string)$in['action']) : null;
        $bodyId   = $in['eventId'] ?? null;
        $isUpdate = ($idQuery || $bodyId || $action === 'update');

        // Promemoria normalizzati
        $remOverrides = extract_reminders($in);
        $useDefault = isset($in['reminders_useDefault']) ? (bool)$in['reminders_useDefault'] : false;

        // Ricorrenza normalizzata
        $recurrence = normalize_rrule($in); // array di RRULE:...

        // All-day vs timed
        $allDayFlag = isset($in['allDay']) ? (int)$in['allDay'] : null;
        $isAllDay   = ($allDayFlag === 1 || $allDayFlag === '1');

        if ($isUpdate) {
            $id = $idQuery ?: $bodyId;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required for update']); exit; }

            $ev = $service->events->get($calendarId, $id);
            // Titolo / descrizione / location
            if (isset($in['title']))       $ev->setSummary($in['title']);
            if (isset($in['description'])) $ev->setDescription($in['description']);
            if (isset($in['location']))    $ev->setLocation($in['location']);

            if ($isAllDay) {
                if (!empty($in['startDate'])) $ev->setStart(new Google_Service_Calendar_EventDateTime(['date' => $in['startDate']]));
                if (!empty($in['endDate']))   $ev->setEnd(new Google_Service_Calendar_EventDateTime(['date' => $in['endDate']]));
            } else {
                $startDT  = $in['startDateTime'] ?? ($in['start'] ?? null);
                $endDT    = $in['endDateTime']   ?? ($in['end']   ?? null);
                $timeZone = $in['timeZone']      ?? 'Europe/Rome';
                if ($startDT) $ev->setStart(new Google_Service_Calendar_EventDateTime(['dateTime' => $startDT, 'timeZone' => $timeZone]));
                if ($endDT)   $ev->setEnd  (new Google_Service_Calendar_EventDateTime(['dateTime' => $endDT,   'timeZone' => $timeZone]));
            }

            // Promemoria
            if ($remOverrides !== []) {
                $ev->setReminders(['useDefault' => $useDefault, 'overrides' => $remOverrides]);
            }
            // Ricorrenza
            if ($recurrence !== []) { $ev->setRecurrence($recurrence); }

            // extendedProperties
            set_extended_properties($ev, $in);

            $updated = $service->events->update($calendarId, $id, $ev);
            echo json_encode(['ok' => true, 'id' => $updated->getId()]);
            exit;
        }

        // CREATE
        $title       = $in['title'] ?? ($in['summary'] ?? '');
        $description = $in['description'] ?? '';
        $location    = $in['location'] ?? null;

        if ($isAllDay) {
            $startDate = $in['startDate'] ?? null;
            $endDate   = $in['endDate']   ?? null; // esclusivo già dal front
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
                'reminders'   => ['useDefault' => $useDefault, 'overrides' => $remOverrides],
            ]);
        } else {
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
                'reminders'   => ['useDefault' => $useDefault, 'overrides' => $remOverrides],
            ]);
        }
        if ($location) $ev->setLocation($location);

        if ($recurrence !== []) $ev->setRecurrence($recurrence);

        // Attendees opzionali (array o stringa comma)
        if (!empty($in['attendees'])) {
            $attRaw = $in['attendees'];
            if (is_string($attRaw)) { $attRaw = array_filter(array_map('trim', explode(',', $attRaw))); }
            if (is_array($attRaw)) {
                $att = [];
                foreach ($attRaw as $a) {
                    if (is_array($a) && !empty($a['email'])) $att[] = ['email' => $a['email']];
                    elseif (is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)) $att[] = ['email' => $a];
                }
                if ($att) $ev->setAttendees($att);
            }
        }

        // extendedProperties opzionali
        set_extended_properties($ev, $in);

        $created = $service->events->insert($calendarId, $ev);
        echo json_encode(['id' => $created->getId()]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
        $service->events->delete($calendarId, $id);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Metodo non supportato']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
