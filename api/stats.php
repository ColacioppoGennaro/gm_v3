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
    $day = (new DateTime())->format('Y-m-d');
    
    // Chat count oggi
    $st = db()->prepare("SELECT chat_count FROM quotas WHERE user_id=? AND day=?");
    $st->bind_param("is", $user['id'], $day);
    $st->execute();
    $r = $st->get_result();
    $chatToday = 0;
    if ($row = $r->fetch_assoc()) {
        $chatToday = intval($row['chat_count']);
    }
    
    // Spazio usato
    $st = db()->prepare("SELECT SUM(size) as total FROM documents WHERE user_id=?");
    $st->bind_param("i", $user['id']);
    $st->execute();
    $r = $st->get_result();
    $totalSize = 0;
    if ($row = $r->fetch_assoc()) {
        $totalSize = intval($row['total']);
    }
    
    ob_end_clean();
    json_out([
        'success' => true,
        'data' => [
            'chatToday' => $chatToday,
            'totalSize' => $totalSize
        ]
    ]);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in stats.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
