<?php
/**
 * api/google/events.php
 * ✅ ENHANCED: Colori, inviti, promemoria + TIPIZZAZIONE EVENTI
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

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['DELETE', 'PUT', 'PATCH'], true)) {
        $method = $override;
    }
}

$calendarId = $_GET['calendarId'] ?? null;
if (!$calendarId) {
    http_response_code(400);
    echo json_encode(['error' => 'calendarId required']);
    exit;
}

$db = db();
$stmt = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$oauth = $result->fetch_assoc();

if (!$oauth || empty($oauth['google_oauth_token'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Google non trovato']);
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

/**
 * ✅ SOSTITUISCI la funzione read_input() alla riga 69 circa
 * con questa versione che rimuove i campi metadata
 */

function read_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJSON = stripos($ct, 'application/json') !== false;

    if ($isJSON && $raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            // ✅ FIX: rimuovi metadata non validi per Google API
            unset($data['_method'], $data['calendarId']);
            return $data;
        }
    }
    
    $data = $_POST;
    // ✅ FIX: rimuovi metadata anche da POST
    unset($data['_method'], $data['calendarId']);
    return $data;
}
function buildEventDateTime($isAllDay, $dateOrDateTime, $timeZone = null) {
    if ($isAllDay) {
        return new Google_Service_Calendar_EventDateTime(['date' => $dateOrDateTime]);
    } else {
        $arr = ['dateTime' => $dateOrDateTime];
        if ($timeZone) $arr['timeZone'] = $timeZone;
        return new Google_Service_Calendar_EventDateTime($arr);
    }
}

try {
    switch ($method) {
        // =========================
        // LISTA EVENTI (GET) - ✅ Con tipizzazione
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
                $isAllDay = (bool)$startObj->getDate();

                // Promemoria
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

                // Ricorrenza
                $recurrence = $e->getRecurrence();
                $rrule = null;
                if ($recurrence && is_array($recurrence) && count($recurrence) > 0) {
                    $rrule = $recurrence[0];
                }

                // ✅ Colore evento
                $colorId = $e->getColorId();

                // ✅ Invitati
                $attendees = [];
                $attendeesObj = $e->getAttendees();
                if ($attendeesObj) {
                    foreach ($attendeesObj as $att) {
                        $attendees[] = [
                            'email' => $att->getEmail(),
                            'displayName' => $att->getDisplayName() ?: $att->getEmail(),
                            'responseStatus' => $att->getResponseStatus() ?: 'needsAction'
                        ];
                    }
                }

                // ✅ TIPIZZAZIONE: leggi extendedProperties.private
                $extProps = $e->getExtendedProperties();
                $privateProps = $extProps ? $extProps->getPrivate() : null;
                
                $eventType = 'personal'; // default se manca
                $eventStatus = 'pending'; // default
                $entityId = null;
                $trigger = null;
                $showInDashboard = true; // default

                $category = null;

                if ($privateProps) {
                    $eventType = $privateProps['type'] ?? 'personal';
                    $eventStatus = $privateProps['status'] ?? 'pending';
                    $entityId = $privateProps['entity_id'] ?? null;
                    $trigger = $privateProps['trigger'] ?? null;
                    $category = $privateProps['category'] ?? null;

                    
                    $showInDashboard = isset($privateProps['show_in_dashboard']) 
                        ? ($privateProps['show_in_dashboard'] === 'true' || $privateProps['show_in_dashboard'] === true)
                        : true;
                }

                $out[] = [
                    'id'     => $e->getId(),
                    'title'  => $e->getSummary() ?: '(senza titolo)',
                    'start'  => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
                    'end'    => $isAllDay ? $endObj->getDate()   : $endObj->getDateTime(),
                    'allDay' => $isAllDay,
                    'backgroundColor' => $colorId ? getColorHex($colorId) : getTypeColor($eventType),
                    'borderColor' => $colorId ? getColorHex($colorId) : getTypeColor($eventType),
                    'extendedProps' => [
                        'description' => $e->getDescription() ?: '',
                        'reminders'   => ['overrides' => $reminders],
                        'recurrence'  => $rrule ? [$rrule] : null,
                        'colorId'     => $colorId,
                        'attendees'   => $attendees,
                        // ✅ Tipizzazione
                        'type'        => $eventType,
                        'status'      => $eventStatus,
                        'entity_id'   => $entityId,
                        'trigger'     => $trigger,
                    'category'    => $category,
                        'show_in_dashboard' => $showInDashboard,
                    ]
                ];
            }
            echo json_encode($out);
            break;
        }

        // =========================
        // CREA EVENTO (POST) - ✅ Con tipizzazione obbligatoria
        // =========================
        case 'POST': {
            $eventId = $_GET['id'] ?? null;
            if (!$eventId) {
                $in = read_input();
                $eventId = $in['id'] ?? null;
            }
            
            if ($eventId) {
                $_GET['id'] = $eventId;
                goto update_event;
            }

            $in = read_input();
            
            $title       = $in['title'] ?? ($in['summary'] ?? '');
            $description = $in['description'] ?? '';
            $allDayFlag  = isset($in['allDay']) ? (int)$in['allDay'] : null;

            // ✅ TIPO OBBLIGATORIO (default: personal se manca)
            $eventType = $in['type'] ?? 'personal';
            $validTypes = ['payment', 'maintenance', 'document', 'personal'];
            if (!in_array($eventType, $validTypes)) {
                $eventType = 'personal';
            }

            $eventStatus = $in['status'] ?? 'pending';
            $entityId = $in['entity_id'] ?? null;
            $category = $in['category'] ?? null;
            $trigger = $in['trigger'] ?? 'manual';
            $showInDashboard = isset($in['show_in_dashboard']) ? ($in['show_in_dashboard'] === 'true') : true;

            // Promemoria
            $remOverrides = [];
            if (isset($in['reminders'])) {
                $rem = $in['reminders'];
                if (is_string($rem)) $rem = json_decode($rem, true);
                if (is_array($rem)) {
                    $over = $rem['overrides'] ?? $rem;
                    foreach ($over as $r) {
                        if (!isset($r['method']) || !isset($r['minutes'])) continue;
                        $remOverrides[] = ['method' => $r['method'], 'minutes' => (int)$r['minutes']];
                    }
                }
            }

            // Ricorrenza
            $rrule = $in['rrule'] ?? $in['recurrence'] ?? null;
            if ($rrule && stripos($rrule, 'RRULE:') !== 0) {
                $rrule = 'RRULE:' . $rrule;
            }

            $isAllDay = ($allDayFlag === 1 || $allDayFlag === '1');

            if ($isAllDay) {
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

            // ✅ Colore evento (1-11)
            if (!empty($in['colorId'])) {
                $ev->setColorId($in['colorId']);
            }

            // ✅ Invitati
            if (!empty($in['attendees'])) {
                $attRaw = $in['attendees'];
                if (is_string($attRaw)) {
                    $attRaw = array_filter(array_map('trim', explode(',', $attRaw)));
                }
                if (is_array($attRaw)) {
                    $att = [];
                    foreach ($attRaw as $a) {
                        if (is_array($a) && !empty($a['email'])) {
                            $att[] = new Google_Service_Calendar_EventAttendee(['email' => $a['email']]);
                        } elseif (is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)) {
                            $att[] = new Google_Service_Calendar_EventAttendee(['email' => $a]);
                        }
                    }
                    if ($att) $ev->setAttendees($att);
                }
            }

            // ✅ SALVA TIPIZZAZIONE in extendedProperties.private
            $extProps = new Google_Service_Calendar_EventExtendedProperties();
            $privateData = [
                'type' => $eventType,
                'status' => $eventStatus,
                'show_in_dashboard' => $showInDashboard ? 'true' : 'false',
            ];
            if ($entityId) $privateData['entity_id'] = $entityId;
            if ($category) $privateData['category'] = $category;
            if ($trigger) $privateData['trigger'] = $trigger;
            
            $extProps->setPrivate($privateData);
            $ev->setExtendedProperties($extProps);

            $created = $service->events->insert($calendarId, $ev);
            echo json_encode(['id' => $created->getId()]);
            break;
        }

        // =========================
        // AGGIORNA EVENTO - ✅ Con tipizzazione
        // =========================
        case 'PUT':
        case 'PATCH':
        update_event:
            
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id required for update']);
                exit;
            }

            $in = read_input();

            try {
                $ev = $service->events->get($calendarId, $id);
            } catch (Exception $e) {
                http_response_code(404);
                echo json_encode(['error' => 'Evento non trovato: ' . $e->getMessage()]);
                exit;
            }

            if (isset($in['title'])) $ev->setSummary($in['title']);
            if (isset($in['description'])) $ev->setDescription($in['description']);

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
                $startDT  = $in['startDateTime'] ?? ($in['start'] ?? null);
                $endDT    = $in['endDateTime']   ?? ($in['end']   ?? null);
                $timeZone = $in['timeZone']      ?? 'Europe/Rome';

                if ($startDT) $ev->setStart(buildEventDateTime(false, $startDT, $timeZone));
                if ($endDT)   $ev->setEnd(buildEventDateTime(false, $endDT,   $timeZone));
            }

            // Promemoria
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

            // Ricorrenza
            if (isset($in['rrule']) || isset($in['recurrence'])) {
                $rr = $in['rrule'] ?? $in['recurrence'];
                if ($rr && stripos($rr, 'RRULE:') !== 0) $rr = 'RRULE:' . $rr;
                $ev->setRecurrence($rr ? [$rr] : []);
            }

            // ✅ Colore
            if (isset($in['colorId'])) {
                $ev->setColorId($in['colorId'] ?: null);
            }

            // ✅ Invitati
            if (isset($in['attendees'])) {
                $attRaw = $in['attendees'];
                if (is_string($attRaw)) {
                    $attRaw = array_filter(array_map('trim', explode(',', $attRaw)));
                }
                $att = [];
                if (is_array($attRaw)) {
                    foreach ($attRaw as $a) {
                        if (is_array($a) && !empty($a['email'])) {
                            $att[] = new Google_Service_Calendar_EventAttendee(['email' => $a['email']]);
                        } elseif (is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)) {
                            $att[] = new Google_Service_Calendar_EventAttendee(['email' => $a]);
                        }
                    }
                }
                $ev->setAttendees($att);
            }

            // ✅ AGGIORNA TIPIZZAZIONE
            if (isset($in['type']) || isset($in['status']) || isset($in['entity_id']) || isset($in['trigger']) || isset($in['show_in_dashboard'])) {
                $existingExtProps = $ev->getExtendedProperties();
                $existingPrivate = $existingExtProps ? $existingExtProps->getPrivate() : [];
                
                if (!is_array($existingPrivate)) $existingPrivate = [];
                
                if (isset($in['type'])) {
                    $validTypes = ['payment', 'maintenance', 'document', 'personal'];
                    $existingPrivate['type'] = in_array($in['type'], $validTypes) ? $in['type'] : 'personal';
                }
                if (isset($in['status'])) {
                    $existingPrivate['status'] = $in['status'];
                }
                if (isset($in['entity_id'])) {
                    $existingPrivate['entity_id'] = $in['entity_id'];
                }

                if (isset($in['category'])) {
    $existingPrivate['category'] = $in['category'];
}
                
                if (isset($in['trigger'])) {
                    $existingPrivate['trigger'] = $in['trigger'];
                }
                if (isset($in['show_in_dashboard'])) {
                    $existingPrivate['show_in_dashboard'] = ($in['show_in_dashboard'] === 'true') ? 'true' : 'false';
                }
                
                $extProps = new Google_Service_Calendar_EventExtendedProperties();
                $extProps->setPrivate($existingPrivate);
                $ev->setExtendedProperties($extProps);
            }

            $service->events->update($calendarId, $id, $ev);
            echo json_encode(['ok' => true]);
            break;

        // =========================
        // ELIMINA EVENTO
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

// ✅ Helper: mappa colorId Google -> hex
function getColorHex($colorId) {
    $colors = [
        '1' => '#a4bdfc', // Lavanda
        '2' => '#7ae7bf', // Salvia
        '3' => '#dbadff', // Uva
        '4' => '#ff887c', // Fenicottero
        '5' => '#fbd75b', // Banana
        '6' => '#ffb878', // Mandarino
        '7' => '#46d6db', // Pavone
        '8' => '#e1e1e1', // Grafite
        '9' => '#5484ed', // Mirtillo
        '10' => '#51b749', // Basilico
        '11' => '#dc2127'  // Pomodoro
    ];
    return $colors[$colorId] ?? '#7c3aed';
}

// ✅ Helper: colore per tipo evento
function getTypeColor($type) {
    $colors = [
        'payment' => '#dc2127',      // Rosso (Pomodoro)
        'maintenance' => '#ffb878',  // Arancione (Mandarino)
        'document' => '#5484ed',     // Blu (Mirtillo)
        'personal' => '#51b749',     // Verde (Basilico)
    ];
    return $colors[$type] ?? '#51b749'; // default personal
}
