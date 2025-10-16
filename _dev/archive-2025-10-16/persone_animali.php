<?php
/**
 * FILE: api/persone_animali.php
 * API CRUD per persone e animali
 * 
 * GET    /api/persone_animali.php?a=list&tipo=persona|animale (opzionale)
 * POST   /api/persone_animali.php?a=create   (nome, tipo?, note?)
 * PATCH  /api/persone_animali.php?a=update   (id, nome?, tipo?, note?)
 * DELETE /api/persone_animali.php?a=delete   (id)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/PersoneAnimaliManager.php';

require_login();

$userId = user()['id'];
$action = $_GET['a'] ?? $_POST['a'] ?? 'list';

header('Content-Type: application/json; charset=utf-8');

try {
    $manager = new PersoneAnimaliManager($userId);
    
    switch ($action) {
        
        // ===== LIST =====
        case 'list':
            $tipo = $_GET['tipo'] ?? null;
            $items = $manager->list($tipo);
            
            json_out([
                'success' => true,
                'data' => $items
            ]);
            break;
        
        // ===== CREATE =====
        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $nome = $input['nome'] ?? null;
            $tipo = $input['tipo'] ?? 'persona';
            $note = $input['note'] ?? null;
            
            if (!$nome) {
                json_out(['success' => false, 'message' => 'Nome obbligatorio'], 400);
            }
            
            $result = $manager->create($nome, $tipo, $note);
            
            json_out([
                'success' => true,
                'message' => ucfirst($tipo) . ' creata',
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
                'message' => 'Aggiornato'
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
                'message' => 'Eliminato'
            ]);
            break;
        
        default:
            json_out(['success' => false, 'message' => 'Azione non valida'], 400);
    }
    
} catch (Exception $e) {
    error_log("PersoneAnimali API Error: " . $e->getMessage());
    json_out([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
