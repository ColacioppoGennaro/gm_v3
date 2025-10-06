<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__.'/../_core/bootstrap.php';
    require_once __DIR__.'/../_core/helpers.php';
    require_once __DIR__.'/../_core/DocAnalyzerClient.php';
    require_login();

    $user = user();
    $action = $_GET['a'] ?? $_POST['a'] ?? '';
    
    // Solo Pro può gestire categorie
    if (!is_pro() && $action !== 'list') {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Funzione riservata a utenti Pro'], 403);
    }

    if ($action === 'list') {
        // Lista categorie (escludi master)
        $st = db()->prepare("SELECT id, name, docanalyzer_label_id, created_at FROM labels WHERE user_id=? AND name != 'master' ORDER BY name ASC");
        $st->bind_param("i", $user['id']);
        $st->execute();
        $r = $st->get_result();
        
        ob_end_clean();
        json_out(['success' => true, 'data' => $r->fetch_all(MYSQLI_ASSOC)]);
    }
    elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        
        if (!$name) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Nome categoria mancante'], 400);
        }
        
        if (strlen($name) > 50) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Nome troppo lungo (max 50 caratteri)'], 400);
        }
        
        // Verifica che non esista già
        $st = db()->prepare("SELECT id FROM labels WHERE user_id=? AND name=?");
        $st->bind_param("is", $user['id'], $name);
        $st->execute();
        if ($st->get_result()->num_rows > 0) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Categoria già esistente'], 400);
        }
        
        // Crea label DocAnalyzer unica
        $docanalyzer_label_id = 'user_' . $user['id'] . '_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($name));
        
        error_log("=== CREATE CATEGORY ===");
        error_log("Category name: $name");
        error_log("DocAnalyzer label: $docanalyzer_label_id");
        
        // CREA LABEL SU DOCANALYZER PRIMA DEL DB
        try {
            $docAnalyzer = new DocAnalyzerClient();
            
            // Verifica se esiste già
            $existing = $docAnalyzer->findLabelByName($docanalyzer_label_id);
            
            if (!$existing) {
                // Crea label vuota su DocAnalyzer
                error_log("Creating label on DocAnalyzer: $docanalyzer_label_id");
                $result = $docAnalyzer->createLabel($docanalyzer_label_id, []);
                
                if (!$result || !isset($result['lid'])) {
                    throw new Exception('DocAnalyzer non ha ritornato lid');
                }
                
                error_log("Label created on DocAnalyzer: lid={$result['lid']}");
            } else {
                error_log("Label already exists on DocAnalyzer: lid={$existing['lid']}");
            }
            
        } catch (Exception $e) {
            error_log("ERROR creating label on DocAnalyzer: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore DocAnalyzer: ' . $e->getMessage()], 500);
        }
        
        // Inserisci nel DB locale
        $st = db()->prepare("INSERT INTO labels(user_id, name, docanalyzer_label_id) VALUES(?,?,?)");
        $st->bind_param("iss", $user['id'], $name, $docanalyzer_label_id);
        $st->execute();
        
        $newId = db()->insert_id;
        error_log("Label created in DB: id=$newId");
        
        ob_end_clean();
        json_out(['success' => true, 'id' => $newId]);
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID mancante'], 400);
        }
        
        // Verifica che non sia la master
        $st = db()->prepare("SELECT name, docanalyzer_label_id FROM labels WHERE id=? AND user_id=?");
        $st->bind_param("ii", $id, $user['id']);
        $st->execute();
        $r = $st->get_result();
        
        if (!($row = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
        }
        
        if ($row['name'] === 'master') {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Non puoi eliminare la categoria master'], 400);
        }
        
        // Verifica che non ci siano documenti associati
        $st = db()->prepare("SELECT COUNT(*) as cnt FROM documents WHERE label_id=?");
        $st->bind_param("i", $id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        
        if ($r['cnt'] > 0) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Impossibile eliminare: ci sono ' . $r['cnt'] . ' documenti in questa categoria'], 400);
        }
        
        // Elimina da DocAnalyzer (opzionale, ma pulito)
        try {
            $docAnalyzer = new DocAnalyzerClient();
            $label = $docAnalyzer->findLabelByName($row['docanalyzer_label_id']);
            
            if ($label && isset($label['lid'])) {
                // Elimina label su DocAnalyzer
                $docAnalyzer->request('DELETE', "/api/v1/label/{$label['lid']}");
                error_log("Label deleted from DocAnalyzer: {$row['docanalyzer_label_id']}");
            }
        } catch (Exception $e) {
            error_log("Warning: Could not delete label from DocAnalyzer: " . $e->getMessage());
            // Continua comunque con eliminazione DB
        }
        
        // Elimina dal DB
        $st = db()->prepare("DELETE FROM labels WHERE id=? AND user_id=?");
        $st->bind_param("ii", $id, $user['id']);
        $st->execute();
        
        ob_end_clean();
        json_out(['success' => true]);
    }
    else {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Azione non valida'], 404);
    }
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in categories.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
