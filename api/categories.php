<?php
/**
 * api/categories.php
 * ✅ VERSIONE MYSQLI CORRETTA
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__.'/../_core/helpers.php';
    require_once __DIR__.'/../_core/DocAnalyzerClient.php';
    require_login();

    $user = user();
    $action = $_GET['a'] ?? '';
    $db = db();

    if ($action === 'list') {
        $stmt = $db->prepare("SELECT id, name, docanalyzer_label_id, created_at FROM labels WHERE user_id=? AND name != 'master' ORDER BY name ASC");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        ob_end_clean();
        json_out(['success' => true, 'data' => $data]);
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
        $stmt = $db->prepare("SELECT id FROM labels WHERE user_id=? AND name=?");
        $stmt->bind_param("is", $user['id'], $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
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
        $stmt = $db->prepare("INSERT INTO labels(user_id, name, docanalyzer_label_id) VALUES(?,?,?)");
        $stmt->bind_param("iss", $user['id'], $name, $docanalyzer_label_id);
        $stmt->execute();

        $newId = $db->insert_id;
        error_log("Label created in DB: id=$newId");

        ob_end_clean();
        json_out(['success' => true, 'id' => $newId]);
    }
    
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID categoria mancante'], 400);
        }

        // Verifica che la label esista e sia dell'utente
        $stmt = $db->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!$row) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
        }

        // Verifica che non ci siano documenti
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM documents WHERE label_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc();

        if ($count['cnt'] > 0) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Impossibile eliminare: categoria contiene ' . $count['cnt'] . ' documento/i'], 400);
        }

        // Elimina su DocAnalyzer
        try {
            $docAnalyzer = new DocAnalyzerClient();
            
            // Trova la label
            $label = $docAnalyzer->findLabelByName($row['docanalyzer_label_id']);
            
            if ($label && isset($label['lid'])) {
                // Elimina usando l'endpoint corretto
                $docAnalyzer->request('DELETE', "/api/v1/label/{$label['lid']}");
                error_log("Label deleted from DocAnalyzer: lid={$label['lid']}");
            }
            
        } catch (Exception $e) {
            error_log("ERROR deleting label on DocAnalyzer: " . $e->getMessage());
            // Continua comunque con l'eliminazione locale
        }

        // Elimina dal DB locale
        $stmt = $db->prepare("DELETE FROM labels WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user['id']);
        $stmt->execute();

        ob_end_clean();
        json_out(['success' => true]);
    }

    else {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Azione non valida'], 400);
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
