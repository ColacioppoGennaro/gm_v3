<?php
/**
 * api/dashboard/events.php
 * ✅ Endpoint per widget eventi dashboard
 * Restituisce solo eventi pending con show_in_dashboard=true
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

$method = $_SERVER['REQUEST_METHOD'];

// Solo GET supportato per ora
if ($method !== 'GET') {
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
    error_log("Google Client Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore Google Client']);
    exit;
}

try {
    // Parametri query
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;
    $filterType = $_GET['type'] ?? null;
    $filterCategory = $_GET['category'] ?? null;
    
    // Carica eventi futuri (prossimi 3 mesi)
    $timeMin = (new DateTime())->format('c');
    $timeMax = (new DateTime('+3 months'))->format('c');
    
    $params = [
        'singleEvents' => true,
        'orderBy' => 'startTime',
        'timeMin' => $timeMin,
        'timeMax' => $timeMax,
        'maxResults' => 500 // Prendiamo molti eventi, poi filtriamo
    ];
    
    $events = $service->events->listEvents('primary', $params);
    $filtered = [];
    
    foreach ($events->getItems() as $e) {
        $extProps = $e->getExtendedProperties();
        $privateProps = $extProps ? $extProps->getPrivate() : null;
        
        if (!$privateProps) continue;
        
        // Filtri obbligatori
        $status = $privateProps['status'] ?? 'pending';
        $showInDashboard = isset($privateProps['show_in_dashboard']) 
            ? ($privateProps['show_in_dashboard'] === 'true' || $privateProps['show_in_dashboard'] === true)
            : true;
        
        if ($status !== 'pending') continue;
        if (!$showInDashboard) continue;
        
        // Filtro tipo (opzionale)
        $eventType = $privateProps['type'] ?? 'personal';
        if ($filterType && $eventType !== $filterType) continue;
        
        // Filtro categoria (opzionale)
        $category = $privateProps['category'] ?? null;
        if ($filterCategory && $category !== $filterCategory) continue;
        
        // Costruisci oggetto evento
        $startObj = $e->getStart();
        $endObj = $e->getEnd();
        $isAllDay = (bool)$startObj->getDate();
        
        $entityId = $privateProps['entity_id'] ?? null;
        $trigger = $privateProps['trigger'] ?? null;
        
        $filtered[] = [
            'id' => $e->getId(),
            'title' => $e->getSummary() ?: '(senza titolo)',
            'description' => $e->getDescription() ?: '',
            'start' => $isAllDay ? $startObj->getDate() : $startObj->getDateTime(),
            'end' => $isAllDay ? $endObj->getDate() : $endObj->getDateTime(),
            'allDay' => $isAllDay,
            'type' => $eventType,
            'category' => $category,
            'status' => $status,
            'entity_id' => $entityId,
            'trigger' => $trigger,
            'show_in_dashboard' => $showInDashboard,
            'color' => getTypeColor($eventType)
        ];
        
        if (count($filtered) >= $limit) break;
    }
    
    echo json_encode([
        'events' => $filtered,
        'count' => count($filtered)
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard Events Error: " . $e->getMessage());
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
