<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__.'/../_core/bootstrap.php';
    require_once __DIR__.'/../_core/helpers.php';
    require_login();

    $user = user();
    $code = trim($_POST['code'] ?? '');
    
    if (!$code) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Codice mancante'], 400);
    }
    
    // Codice promozionale: 123456789
    $validCodes = ['123456789', 'PROMO2025', 'TESTPRO'];
    
    if (!in_array($code, $validCodes)) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Codice non valido'], 400);
    }
    
    // Aggiorna utente a Pro
    $st = db()->prepare("UPDATE users SET role='pro' WHERE id=?");
    $st->bind_param("i", $user['id']);
    $st->execute();
    
    // Aggiorna sessione
    $_SESSION['role'] = 'pro';
    
    // Log dell'upgrade
    error_log("User {$user['id']} upgraded to Pro with code: $code");
    
    ob_end_clean();
    json_out(['success' => true, 'message' => 'Piano Pro attivato!']);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in upgrade.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
