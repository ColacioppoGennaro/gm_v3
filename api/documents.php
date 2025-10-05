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

    function get_master_label($uid) {
        $st = db()->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'");
        $st->bind_param("i", $uid); 
        $st->execute(); 
        $r = $st->get_result(); 
        if ($row = $r->fetch_assoc()) return $row; 
        throw new Exception('Label master assente');
    }

    if ($action === 'list') {
        // Query con JOIN per includere la categoria
        $st = db()->prepare("
            SELECT d.id, d.file_name, d.size, d.mime, d.created_at, 
                   l.name as category, d.docanalyzer_doc_id
            FROM documents d 
            JOIN labels l ON d.label_id = l.id 
            WHERE d.user_id = ? 
            ORDER BY d.created_at DESC
        ");
        $st->bind_param("i", $user['id']); 
        $st->execute(); 
        $r = $st->get_result(); 
        ob_end_clean();
        json_out(['success' => true, 'data' => $r->fetch_all(MYSQLI_ASSOC)]);
    }
    elseif ($action === 'upload') {
        // Verifica limiti
        $max = is_pro() ? 200 : 5; 
        $r = db()->query("SELECT COUNT(*) c FROM documents WHERE user_id=" . $user['id'])->fetch_assoc(); 
        if (intval($r['c']) >= $max) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Limite documenti raggiunto'], 403);
        }
        
        if (!isset($_FILES['file'])) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Nessun file'], 400);
        }
        
        $f = $_FILES['file']; 
        if ($f['error'] !== UPLOAD_ERR_OK) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore upload'], 400);
        }
        
        $max_size = (is_pro() ? 150 : 50) * 1024 * 1024; 
        if ($f['size'] > $max_size) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'File troppo grande'], 400);
        }
        
        $allowed = ['pdf', 'doc', 'docx', 'txt', 'csv', 'xlsx', 'png', 'jpg', 'jpeg']; 
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)); 
        if (!in_array($ext, $allowed)) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Tipo file non ammesso'], 400);
        }
        
        // Determina la label (master o categoria)
        $master = get_master_label($user['id']); 
        $label_id = $master['id'];
        $label_name = $master['name'];
        $docanalyzer_label_id = $master['docanalyzer_label_id'];
        
        if (isset($_POST['category']) && is_pro()) { 
            $cat = trim($_POST['category']); 
            $st = db()->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE user_id=? AND name=?"); 
            $st->bind_param("is", $user['id'], $cat); 
            $st->execute(); 
            $rr = $st->get_result(); 
            if ($row = $rr->fetch_assoc()) {
                $label_id = $row['id'];
                $label_name = $row['name'];
                $docanalyzer_label_id = $row['docanalyzer_label_id'];
            }
        }
        
        // === INTEGRAZIONE DOCANALYZER ===
        try {
            $docAnalyzer = new DocAnalyzerClient();
            
            // Il docanalyzer_label_id nel DB è il NOME della label su DocAnalyzer
            // Dobbiamo usarlo come nome per trovare/creare la label
            $result = $docAnalyzer->uploadAndTag(
                $f['tmp_name'],
                $f['name'],
                $docanalyzer_label_id // Questo è il nome della label (es. "user_123" o "user_123_fatture")
            );
            
            $docanalyzer_doc_id = $result['docid'];
            
            // Log per debug
            error_log("DocAnalyzer Upload Success: docid={$docanalyzer_doc_id}, label={$docanalyzer_label_id}, strategy={$result['strategy']}");
            
        } catch (Exception $e) {
            error_log("DocAnalyzer Error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore caricamento su DocAnalyzer: ' . $e->getMessage()], 500);
        }
        
        // Salva nel DB locale
        $st = db()->prepare("INSERT INTO documents(user_id, label_id, file_name, mime, size, docanalyzer_doc_id) VALUES(?,?,?,?,?,?)"); 
        $st->bind_param("iissis", $user['id'], $label_id, $f['name'], $f['type'], $f['size'], $docanalyzer_doc_id); 
        $st->execute();
        
        ob_end_clean();
        json_out(['success' => true, 'docid' => $docanalyzer_doc_id]);
    }
    elseif ($action === 'change_category') {
        // Cambia categoria di un documento
        $doc_id = intval($_POST['id'] ?? 0);
        $new_category = trim($_POST['category'] ?? '');
        
        if (!$doc_id || !$new_category) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Dati mancanti'], 400);
        }
        
        // Solo Pro può usare questa funzione (tranne per master)
        if (!is_pro() && $new_category !== 'master') {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Funzione riservata a Pro'], 403);
        }
        
        // 1. Ottieni documento con vecchia label
        $st = db()->prepare("
            SELECT d.docanalyzer_doc_id, d.label_id, l.name as old_category, l.docanalyzer_label_id as old_label_name
            FROM documents d
            JOIN labels l ON d.label_id = l.id
            WHERE d.id = ? AND d.user_id = ?
        ");
        $st->bind_param("ii", $doc_id, $user['id']);
        $st->execute();
        $r = $st->get_result();
        
        if (!($doc = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
        }
        
        // 2. Ottieni nuova label
        $st = db()->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE user_id = ? AND name = ?");
        $st->bind_param("is", $user['id'], $new_category);
        $st->execute();
        $r = $st->get_result();
        
        if (!($new_label = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
        }
        
        // Se è la stessa categoria, ignora
        if ($doc['old_category'] === $new_category) {
            ob_end_clean();
            json_out(['success' => true, 'message' => 'Già nella categoria corretta']);
        }
        
        // 3. SPOSTA su DocAnalyzer
        try {
            $docAnalyzer = new DocAnalyzerClient();
            $docid = $doc['docanalyzer_doc_id'];
            
            // UNTAG dalla vecchia label
            error_log("UNTAG from old label: {$doc['old_label_name']}, docid: $docid");
            $docAnalyzer->updateLabel($doc['old_label_name'], [
                'docids' => ['untag' => [$docid]]
            ]);
            
            // TAG sulla nuova label
            error_log("TAG to new label: {$new_label['docanalyzer_label_id']}, docid: $docid");
            $docAnalyzer->updateLabel($new_label['docanalyzer_label_id'], [
                'docids' => ['tag' => [$docid]]
            ]);
            
            error_log("DocAnalyzer: Documento $docid spostato da {$doc['old_category']} a {$new_category}");
            
        } catch (Exception $e) {
            error_log("Errore spostamento DocAnalyzer: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore spostamento su DocAnalyzer: ' . $e->getMessage()], 500);
        }
        
        // 4. Update DB locale
        $st = db()->prepare("UPDATE documents SET label_id = ? WHERE id = ? AND user_id = ?");
        $st->bind_param("iii", $new_label['id'], $doc_id, $user['id']);
        $st->execute();
        
        ob_end_clean();
        json_out(['success' => true, 'message' => 'Documento spostato correttamente']);
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0); 
        if (!$id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID mancante'], 400);
        }
        
        $st = db()->prepare("SELECT docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?"); 
        $st->bind_param("ii", $id, $user['id']); 
        $st->execute(); 
        $r = $st->get_result(); 
        if (!($row = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Non trovato'], 404);
        }
        
        $docanalyzer_doc_id = $row['docanalyzer_doc_id'];
        
        // Elimina da DocAnalyzer
        if ($docanalyzer_doc_id) {
            try {
                $docAnalyzer = new DocAnalyzerClient();
                $docAnalyzer->deleteDocument($docanalyzer_doc_id);
            } catch (Exception $e) {
                error_log("Errore eliminazione DocAnalyzer: " . $e->getMessage());
                // Continua comunque con l'eliminazione locale
            }
        }
        
        // Elimina dal DB
        $st = db()->prepare("DELETE FROM documents WHERE id=? AND user_id=?"); 
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
    error_log("API Error in documents.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
