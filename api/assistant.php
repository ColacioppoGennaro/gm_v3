<?php
/**
 * api/assistant.php
 * 
 * Endpoint REST per Assistente AI conversazionale v3.0
 * - Gestione chat multi-turno intelligente
 * - Supporto upload immagini per analisi
 * - Apertura modal calendario precompilato
 * 
 * POST /api/assistant.php
 * Body: { "message": "testo", "image": file (opzionale) }
 * Response: { 
 *   "success": bool, 
 *   "status": "incomplete|ready_for_modal|complete|error", 
 *   "message": string, 
 *   "data": object 
 * }
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
    
    // Inizializza database
    $db = db();
    
    // Gestione upload immagine (se presente)
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['image']['tmp_name'];
        $originalName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $mimeType = $_FILES['image']['type'];
        
        // Validazione
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            ob_end_clean();
            json_out([
                'success' => false,
                'message' => 'Formato immagine non supportato. Usa JPG, PNG o WebP.'
            ], 400);
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($fileSize > $maxSize) {
            ob_end_clean();
            json_out([
                'success' => false,
                'message' => 'Immagine troppo grande. Max 10MB.'
            ], 400);
        }
        
        // Salva temporaneamente
        $tempDir = sys_get_temp_dir();
        $tempFileName = 'assistant_img_' . uniqid() . '_' . $originalName;
        $imagePath = $tempDir . '/' . $tempFileName;
        
        if (!move_uploaded_file($tmpName, $imagePath)) {
            throw new Exception("Errore salvataggio immagine temporanea");
        }
        
        error_log("Image uploaded: {$imagePath} ({$fileSize} bytes)");
    }
    
    // Leggi input messaggio
    $message = trim($_POST['message'] ?? '');
    $resetConversation = isset($_POST['reset']) && $_POST['reset'] === 'true';
    
    // Reset conversazione se richiesto
    if ($resetConversation) {
        unset($_SESSION['assistant_state']);
        ob_end_clean();
        json_out([
            'success' => true,
            'status' => 'reset',
            'message' => 'Conversazione resettata. Come posso aiutarti? Puoi descrivermi un evento o caricare una foto! 📸',
            'data' => null
        ]);
    }
    
    // Se c'è immagine ma no messaggio, usa messaggio default
    if ($imagePath && empty($message)) {
        $message = "Ho caricato una foto di un documento";
    }
    
    if (empty($message) && !$imagePath) {
        ob_end_clean();
        json_out([
            'success' => false,
            'message' => 'Invia un messaggio o carica una foto'
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
        // Cleanup immagine temporanea
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        ob_end_clean();
        json_out([
            'success' => false,
            'message' => 'Limite chat giornaliero raggiunto'
        ], 403);
    }
    
    // Recupera stato conversazione da sessione
    $sessionState = $_SESSION['assistant_state'] ?? null;
    
    error_log("Assistant API v3.0 - User: {$userId}, Turn: " . ($sessionState['turn'] ?? 0) . ", Message: " . substr($message, 0, 50) . ($imagePath ? " [IMAGE]" : ""));
    
    // Inizializza agent
    $agent = new AssistantAgent($userId);
    
    // Processa messaggio (con o senza immagine)
    $response = $agent->processMessage($message, $sessionState, $imagePath);
    
    // Cleanup immagine temporanea dopo elaborazione
    if ($imagePath && file_exists($imagePath)) {
        unlink($imagePath);
    }
    
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
        'status' => $response['status'], // incomplete | ready_for_modal | complete | error
        'message' => $response['message'],
        'data' => $response['data'],
        'turn' => $response['state']['turn'] ?? 0,
        'intent' => $response['state']['intent'] ?? null
    ]);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Assistant API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Cleanup immagine temporanea in caso di errore
    if (isset($imagePath) && $imagePath && file_exists($imagePath)) {
        unlink($imagePath);
    }
    
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
