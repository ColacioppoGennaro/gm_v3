<?php
/**
 * FILE: api/settori.php
 * API CRUD per settori utente
 * 
 * GET    /api/settori.php?a=list
 * POST   /api/settori.php?a=create   (nome, icona?, colore?)
 * PATCH  /api/settori.php?a=update   (id, nome?, icona?, colore?, ordine?)
 * DELETE /api/settori.php?a=delete   (id)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/SettoriManager.php';

require_login();

$userId = user()['id'];
$action = $_GET['a'] ?? $_POST['a'] ?? 'list';

header('Content-Type: application/json; charset=utf-8');

try {
    $manager = new SettoriManager($userId);
    
    switch ($action) {
        
        // ===== LIST =====
        case 'list':
            $settori = $manager->list();
            json_out([
                'success' => true,
                'data' => $settori
            ]);
            break;
        
        // ===== CREATE =====
        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $nome = $input['nome'] ?? null;
            $icona = $input['icona'] ?? '📁';
            $colore = $input['colore'] ?? '#7c3aed';
            
            if (!$nome) {
                json_out(['success' => false, 'message' => 'Nome obbligatorio'], 400);
            }
            
            $result = $manager->create($nome, $icona, $colore);
            
            json_out([
                'success' => true,
                'message' => 'Settore creato',
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
                'message' => 'Settore aggiornato'
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
                'message' => 'Settore eliminato'
            ]);
            break;
        
        default:
            json_out(['success' => false, 'message' => 'Azione non valida'], 400);
    }
    
} catch (Exception $e) {
    error_log("Settori API Error: " . $e->getMessage());
    json_out([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
