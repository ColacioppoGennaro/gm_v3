<?php
/**
 * api/event_categories.php
 * CRUD categorie evento per Tipo (limite 50 per tipo)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();
require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/EventCategoriesManager.php';

try {
    require_login();
    $userId = user()['id'];
    $mgr = new EventCategoriesManager($userId);

    $a = $_GET['a'] ?? $_POST['a'] ?? 'list';

    if ($a === 'list') {
        $tipoId = intval($_GET['tipo_id'] ?? 0);
        if (!$tipoId) json_out(['success'=>false,'message'=>'tipo_id mancante'],400);
        $rows = $mgr->list($tipoId);
        json_out(['success'=>true,'data'=>$rows]);
    }
    elseif ($a === 'create') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $tipoId = intval($input['tipo_id'] ?? 0);
        $nome = trim($input['nome'] ?? '');
        if (!$tipoId || $nome==='') json_out(['success'=>false,'message'=>'Parametri mancanti'],400);
        $row = $mgr->create($tipoId, $nome);
        json_out(['success'=>true,'data'=>$row]);
    }
    elseif ($a === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = intval($input['id'] ?? 0);
        if (!$id) json_out(['success'=>false,'message'=>'ID mancante'],400);
        $res = $mgr->delete($id);
        json_out(['success'=>true]);
    }
    else {
        json_out(['success'=>false,'message'=>'Azione non valida'],400);
    }
}
catch (Throwable $e) {
    error_log('EventCategories API error: '.$e->getMessage());
    json_out(['success'=>false,'message'=>$e->getMessage()],500);
}

