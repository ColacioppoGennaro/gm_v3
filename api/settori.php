<?php
/**
 * FILE: api/settori.php
 * API CRUD per settori utente
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

// ✅ BUFFER OUTPUT per evitare output accidentale
ob_start();

session_start();

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/SettoriManager.php';

require_login();

$userId = user()['id'];
$action = $_GET['a'] ?? $_POST['a'] ?? 'list';

try {
    $manager = new SettoriManager($userId);
    
    switch ($action) {
        
        // ===== LIST =====
        case 'list':
            $settori = $manager->list();
            
            // ✅ PULISCI BUFFER PRIMA DI JSON
            ob_end_clean();
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $settori
            ]);
            exit;
        
        // ===== CREATE =====
        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $nome = $input['nome'] ?? null;
            $icona = $input['icona'] ?? '📁';
            $colore = $input['colore'] ?? '#7c3aed';
            
            if (!$nome) {
                ob_end_clean();
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Nome obbligatorio']);
                exit;
            }
            
            $result = $manager->create($nome, $icona, $colore);
            
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Settore creato',
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
                'message' => 'Settore aggiornato'
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
                'message' => 'Settore eliminato'
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
    error_log("Settori API Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
