<?php
// api/calendar.php - VERSIONE CORRETTA

// Configurazione sessione PRIMA di tutto
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

session_start();

// Disabilita output buffering
ob_start();

require_once __DIR__.'/../_core/helpers.php';

// Verifica autenticazione
require_login();

$user_id = $_SESSION['user_id'];
$db = db();
$method = $_SERVER['REQUEST_METHOD'];

// Header JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // === GET: Lista eventi ===
    if ($method === 'GET') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        
        if (!$start || !$end) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Parametri start e end richiesti']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT id, title, description, starts_at, ends_at, all_day, color, rrule, reminders
            FROM events 
            WHERE user_id = ? 
              AND starts_at < ? 
              AND (ends_at IS NULL OR ends_at >= ?)
            ORDER BY starts_at ASC
        ");
        $stmt->bind_param('iss', $user_id, $end, $start);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'start' => date('c', strtotime($row['starts_at'])),
                'end' => $row['ends_at'] ? date('c', strtotime($row['ends_at'])) : null,
                'allDay' => (bool)$row['all_day'],
                'color' => $row['color'],
                'extendedProps' => [
                    'description' => $row['description'],
                    'rrule' => $row['rrule'],
                    'reminders' => json_decode($row['reminders'] ?: '[]', true)
                ]
            ];
        }
        
        ob_end_clean();
        echo json_encode($events);
        exit;
    }
    
    // === POST: Crea evento ===
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['title']) || !isset($data['start'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Dati mancanti: title e start richiesti']);
            exit;
        }
        
        $title = $data['title'];
        $description = $data['description'] ?? null;
        $start = $data['start'];
        $end = $data['end'] ?? null;
        $allDay = isset($data['allDay']) ? (int)$data['allDay'] : 0;
        $color = $data['color'] ?? null;
        $rrule = $data['rrule'] ?? null;
        $reminders = isset($data['reminders']) ? json_encode($data['reminders']) : '[]';
        
        $stmt = $db->prepare("
            INSERT INTO events (user_id, title, description, starts_at, ends_at, all_day, color, rrule, reminders, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ui')
        ");
        $stmt->bind_param('issssssss', $user_id, $title, $description, $start, $end, $allDay, $color, $rrule, $reminders);
        $stmt->execute();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'id' => $db->insert_id]);
        exit;
    }
    
    // === PATCH/PUT: Aggiorna evento ===
    if ($method === 'PATCH' || $method === 'PUT') {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'ID evento mancante']);
            exit;
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Dati non validi']);
            exit;
        }
        
        // Costruisci UPDATE dinamico
        $fields = [];
        $types = '';
        $values = [];
        
        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $types .= 's';
            $values[] = $data['title'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $types .= 's';
            $values[] = $data['description'];
        }
        if (isset($data['start'])) {
            $fields[] = 'starts_at = ?';
            $types .= 's';
            $values[] = $data['start'];
        }
        if (isset($data['end'])) {
            $fields[] = 'ends_at = ?';
            $types .= 's';
            $values[] = $data['end'];
        }
        if (isset($data['allDay'])) {
            $fields[] = 'all_day = ?';
            $types .= 'i';
            $values[] = (int)$data['allDay'];
        }
        if (isset($data['color'])) {
            $fields[] = 'color = ?';
            $types .= 's';
            $values[] = $data['color'];
        }
        if (isset($data['rrule'])) {
            $fields[] = 'rrule = ?';
            $types .= 's';
            $values[] = $data['rrule'];
        }
        if (isset($data['reminders'])) {
            $fields[] = 'reminders = ?';
            $types .= 's';
            $values[] = json_encode($data['reminders']);
        }
        
        if (empty($fields)) {
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Nessun campo da aggiornare']);
            exit;
        }
        
        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
        $types .= 'ii';
        $values[] = (int)$id;
        $values[] = $user_id;
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        ob_end_clean();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // === DELETE: Elimina evento ===
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'ID evento mancante']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM events WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        
        ob_end_clean();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Metodo non supportato
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non supportato']);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Calendar API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore server: ' . $e->getMessage()]);
}
