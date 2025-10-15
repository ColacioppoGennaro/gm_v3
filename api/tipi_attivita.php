<?php
/**
 * FILE: api/tipi_attivita.php
 * API CRUD per tipi attività
 * 
 * GET    /api/tipi_attivita.php?a=list&settore_id=X (opzionale)
 * POST   /api/tipi_attivita.php?a=create   (settore_id, nome, icona?, colore?, puo_collegare_documento?)
 * PATCH  /api/tipi_attivita.php?a=update   (id, nome?, icona?, colore?, puo_collegare_documento?, ordine?)
 * DELETE /api/tipi_attivita.php?a=delete   (id)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/TipiAttivitaManager.php';

require_login();

$userId = user()['id'];
$action = $_GET['a'] ?? $_POST['a'] ?? 'list';

header('Content-Type: application/json; charset=utf-8');

try {
    $manager = new TipiAttivitaManager($userId);
    
    switch ($action) {
        
        // ===== LIST =====
        case 'list':
            $settoreId = $_GET['settore_id'] ?? null;
            $tipi = $manager->list($settoreId);
            
            json_out([
                'success' => true,
                'data' => $tipi
            ]);
            break;
        
        // ===== CREATE =====
        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $settoreId = $input['settore_id'] ?? null;
            $nome = $input['nome'] ?? null;
            $icona = $input['icona'] ?? '📌';
            $colore = $input['colore'] ?? null;
            $puoCollegareDoc = isset($input['puo_collegare_documento']) ? (bool)$input['puo_collegare_documento'] : false;
            
            if (!$settoreId || !$nome) {
                json_out(['success' => false, 'message' => 'settore_id e nome obbligatori'], 400);
            }
            
            $result = $manager->create($settoreId, $nome, $icona, $colore, $puoCollegareDoc);
            
            json_out([
                'success' => true,
                'message' => 'Tipo attività creato',
                'data' => $result
            ]);
            break;
        
        // ===== UPDATE =====
        case 'update':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $id = $input['id'] ?? null;
            
            if (!$id) {
                json_out(['success' => false, 'message' => 'ID obbligatorio'], 400);
            }
            
            unset($input['id'], $input['a']);
            
            $manager->update($id, $input);
            
            json_out([
                'success' => true,
                'message' => 'Tipo attività aggiornato'
            ]);
            break;
        
        // ===== DELETE =====
        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $id = $input['id'] ?? $_GET['id'] ?? null;
            
            if (!$id) {
                json_out(['success' => false, 'message' => 'ID obbligatorio'], 400);
            }
            
            $manager->delete($id);
            
            json_out([
                'success' => true,
                'message' => 'Tipo attività eliminato'
            ]);
            break;
        
        default:
            json_out(['success' => false, 'message' => 'Azione non valida'], 400);
    }
    
} catch (Exception $e) {
    error_log("TipiAttivita API Error: " . $e->getMessage());
    json_out([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
