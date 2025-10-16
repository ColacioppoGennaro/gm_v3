<?php
/**
 * api/dashboard/events.php
 * ✅ Endpoint per widget eventi dashboard — supporto feed a finestra con anchor e direzione
 * - dir=up   => futuri rispetto ad anchor
 * - dir=down => passati rispetto ad anchor (con include_done=1 per includere completati)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/google_client.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non supportato']);
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
    error_log('Google Client Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore Google Client']);
    exit;
}

try {
    // ---- input ----
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 20;
    $filterType = $_GET['type'] ?? null;
    $filterCategory = $_GET['category'] ?? null;
    foreach (['filterType', 'filterCategory'] as $k) {
        if (isset($$k) && ($$k === '' || $$k === 'null' || $$k === 'undefined')) $$k = null;
    }

    $dir = strtolower($_GET['dir'] ?? 'up'); // up|down
    if ($dir !== 'up' && $dir !== 'down') $dir = 'up';

    $rangeDays = (int)($_GET['rangeDays'] ?? 30);
    if ($rangeDays < 1) $rangeDays = 1; if ($rangeDays > 180) $rangeDays = 180;

    $anchorStr = $_GET['anchor'] ?? null; // ISO 8601
    $tz = new DateTimeZone('Europe/Rome');
    $anchor = $anchorStr ? new DateTime($anchorStr) : new DateTime('now', $tz);

    $includeDone = isset($_GET['include_done']) && in_array((string)$_GET['include_done'], ['1','true','yes','y','on'], true);

    // ---- finestra temporale in base alla direzione ----
    $timeMinDt = clone $anchor; $timeMaxDt = clone $anchor;
    if ($dir === 'up') {
        $timeMaxDt->modify('+' . $rangeDays . ' days');
    } else { // down
        $timeMinDt->modify('-' . $rangeDays . ' days');
    }

    $params = [
        'singleEvents' => true,
        'orderBy' => 'startTime',
        'timeMin' => $timeMinDt->format('c'),
        'timeMax' => $timeMaxDt->format('c'),
        'maxResults' => 500
    ];

    $events = $service->events->listEvents('primary', $params);

    $filtered = [];
    foreach ($events->getItems() as $e) {
        $extProps = $e->getExtendedProperties();
        $private = $extProps ? $extProps->getPrivate() : [];

        // status / show_in_dashboard (snake+camel)
        $status = $private['status'] ?? 'pending';
        $showRaw = $private['show_in_dashboard'] ?? ($private['showInDashboard'] ?? null);
        $show = true; // default per compatibilità
        if ($showRaw !== null) {
            $s = strtolower((string)$showRaw);
            $show = in_array($s, ['true','1','yes','y','on'], true);
        }

        // filtri core: pending solo se non include_done
        if (!$includeDone && $status !== 'pending') continue;
        if (!$show) continue;

        $type = $private['type'] ?? 'personal';
        $category = $private['category'] ?? null;
        if ($filterType && $type !== $filterType) continue;
        if ($filterCategory && $category !== $filterCategory) continue;

        $startObj = $e->getStart();
        $endObj   = $e->getEnd();
        $isAllDay = (bool)$startObj->getDate();

        $filtered[] = [
            'id' => $e->getId(),
            'title' => $e->getSummary() ?: '(senza titolo)',
            'description' => $e->getDescription() ?: '',
            'start' => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
            'end' => $isAllDay ? $endObj->getDate() : $endObj->getDateTime(),
            'allDay' => $isAllDay,
            'type' => $type,
            'category' => $category,
            'status' => $status,
            'entity_id' => $private['entity_id'] ?? null,
            'trigger' => $private['trigger'] ?? null,
            'show_in_dashboard' => $show,
            'color' => getTypeColor($type)
        ];

        if (count($filtered) >= $limit) break;
    }

    // next anchors per scrolling
    $nextAnchorUp = ($dir === 'up'  ? $timeMaxDt : $anchor)->format('c');
    $nextAnchorDown = ($dir === 'down' ? $timeMinDt : $anchor)->format('c');

    echo json_encode([
        'events' => $filtered,
        'count' => count($filtered),
        'meta' => [
            'dir' => $dir,
            'anchor' => $anchor->format('c'),
            'timeMin' => $timeMinDt->format('c'),
            'timeMax' => $timeMaxDt->format('c'),
            'nextAnchorUp' => $nextAnchorUp,
            'nextAnchorDown' => $nextAnchorDown
        ]
    ]);

} catch (Exception $e) {
    error_log('Dashboard Events Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getTypeColor($type) {
    $colors = [
        'payment' => '#dc2127',
        'maintenance' => '#ffb878',
        'document' => '#5484ed',
        'personal' => '#51b749',
    ];
    return $colors[$type] ?? '#51b749';
}
