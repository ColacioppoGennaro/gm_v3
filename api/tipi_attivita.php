<?php
/**
 * FILE: api/tipi_attivita.php
 * API CRUD per tipi attività
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

// ✅ BUFFER OUTPUT
ob_start();

session_start();

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/TipiAttivitaManager.php';

require_login();

$userId = user()['id'];
$action = $_GET['a'] ?? $_POST['a'] ?? 'list';

try {
    $manager = new TipiAttivitaManager($userId);
    
    switch ($action) {
        
        // ===== LIST =====
        case 'list':
            $settoreId = $_GET['settore_id'] ?? null;
            $tipi = $manager->list($settoreId);
            
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $tipi
            ]);
            exit;
        
        // ===== CREATE =====
        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $settoreId = $input['settore_id'] ?? null;
            $nome = $input['nome'] ?? null;
            $icona = $input['icona'] ?? '📌';
            $colore = $input['colore'] ?? null;
            $puoCollegareDoc = isset($input['puo_collegare_documento']) ? (bool)$input['puo_collegare_documento'] : false;
            
            if (!$settoreId || !$nome) {
                ob_end_clean();
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'settore_id e nome obbligatori']);
                exit;
            }
            
            $result = $manager->create($settoreId, $nome, $icona, $colore, $puoCollegareDoc);
            
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Tipo attività creato',
                'data' => $result
            ]);
            exit;
        
        // ===== UPDATE =====
        case 'update':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $id = $input['id'] ?? null;
            
            if (!$id) {
                ob_end_clean();
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'ID obbligatorio']);
                exit;
            }
            
            unset($input['id'], $input['a']);
            
            $manager->update($id, $input);
            
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Tipo attività aggiornato'
            ]);
            exit;
        
        // ===== DELETE =====
        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $id = $input['id'] ?? $_GET['id'] ?? null;
            
            if (!$id) {
                ob_end_clean();
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'ID obbligatorio']);
                exit;
            }
            
            $manager->delete($id);
            
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Tipo attività eliminato'
            ]);
            exit;
        
        default:
            ob_end_clean();
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            exit;
    }
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("TipiAttivita API Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
