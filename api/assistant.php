<?php
/**
 * api/assistant.php
 * 
 * Endpoint REST per Assistente AI conversazionale
 * Gestisce chat multi-turno con state management in sessione
 * 
 * POST /api/assistant.php
 * Body: { "message": "testo utente" }
 * Response: { "success": bool, "status": string, "message": string, "data": object }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__ . '/../_core/bootstrap.php';
    require_once __DIR__ . '/../_core/helpers.php';
    require_once __DIR__ . '/../_core/AssistantAgent.php';
    
    require_login();
    
    $user = user();
    $userId = $user['id'];
    
    // Leggi input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST; // Fallback a POST standard
    }
    
    $message = trim($data['message'] ?? '');
    $resetConversation = isset($data['reset']) && $data['reset'] === true;
    
    // Reset conversazione se richiesto
    if ($resetConversation) {
        unset($_SESSION['assistant_state']);
        ob_end_clean();
        json_out([
            'success' => true,
            'status' => 'reset',
            'message' => 'Conversazione resettata. Come posso aiutarti?',
            'data' => null
        ]);
    }
    
    if (empty($message)) {
        ob_end_clean();
        json_out([
            'success' => false,
            'message' => 'Messaggio vuoto'
        ], 400);
    }
    
    // Verifica quota
    $maxChats = is_pro() ? 200 : 20;
    $day = (new DateTime())->format('Y-m-d');
    
    // Crea/aggiorna quota
    $stmt = $db->prepare("INSERT INTO quotas(user_id, day, uploads_count, chat_count) VALUES(?, ?, 0, 0) ON DUPLICATE KEY UPDATE day=day");
    $stmt->bind_param("is", $userId, $day);
    $stmt->execute();
    
    // Incrementa contatore chat
    $db->query("UPDATE quotas SET chat_count = chat_count + 1 WHERE user_id = {$userId} AND day = '{$day}'");
    
    // Controlla limite
    $result = $db->query("SELECT chat_count FROM quotas WHERE user_id = {$userId} AND day = '{$day}'")->fetch_assoc();
    if (intval($result['chat_count']) > $maxChats) {
        ob_end_clean();
        json_out([
            'success' => false,
            'message' => 'Limite chat giornaliero raggiunto'
        ], 403);
    }
    
    // Recupera stato conversazione da sessione
    $sessionState = $_SESSION['assistant_state'] ?? null;
    
    error_log("Assistant API - User: {$userId}, Turn: " . ($sessionState['turn'] ?? 0) . ", Message: " . substr($message, 0, 50));
    
    // Inizializza agent
    $agent = new AssistantAgent($userId);
    
    // Processa messaggio
    $response = $agent->processMessage($message, $sessionState);
    
    // Salva stato in sessione se conversazione incompleta
    if ($response['state']) {
        $_SESSION['assistant_state'] = $response['state'];
    } else {
        // Conversazione completata o errore: resetta stato
        unset($_SESSION['assistant_state']);
    }
    
    // Log conversazione
    $source = 'assistant';
    $answer = $response['message'];
    $stmt = $db->prepare("INSERT INTO chat_logs(user_id, source, question, answer) VALUES(?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $source, $message, $answer);
    $stmt->execute();
    
    // Response
    ob_end_clean();
    json_out([
        'success' => true,
        'status' => $response['status'], // complete | incomplete | error
        'message' => $response['message'],
        'data' => $response['data'],
        'turn' => $response['state']['turn'] ?? 0,
        'intent' => $response['state']['intent'] ?? null
    ]);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Assistant API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Resetta stato in caso di errore critico
    unset($_SESSION['assistant_state']);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
