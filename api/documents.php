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
        // Query con JOIN per includere la categoria e stato OCR
        $st = db()->prepare("
            SELECT d.id, d.file_name, d.size, d.mime, d.created_at, 
                   l.name as category, d.docanalyzer_doc_id, d.ocr_status
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
        
        // === STORAGE LOCALE DEL FILE ===
        $uploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/{$label_name}";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Nome sicuro con timestamp per evitare conflitti
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($f['name']));
        $localPath = "$uploadDir/$safeName";
        
        if (!move_uploaded_file($f['tmp_name'], $localPath)) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore salvataggio file locale'], 500);
        }
        
        error_log("=== UPLOAD START ===");
        error_log("File: {$f['name']}, Local: $localPath, Category: $label_name, DocAnalyzer Label: $docanalyzer_label_id");
        
        // === INTEGRAZIONE DOCANALYZER ===
        try {
            $docAnalyzer = new DocAnalyzerClient();
            
            $result = $docAnalyzer->uploadAndTag(
                $localPath,
                $f['name'],
                $docanalyzer_label_id
            );
            
            $docanalyzer_doc_id = $result['docid'];
            
            error_log("Upload SUCCESS: docid=$docanalyzer_doc_id, strategy={$result['strategy']}");
            
        } catch (Exception $e) {
            // Se fallisce DocAnalyzer, elimina file locale
            @unlink($localPath);
            error_log("DocAnalyzer Upload Error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore DocAnalyzer: ' . $e->getMessage()], 500);
        }
        
        // Salva nel DB locale CON PATH e OCR status
        $ocr_status = 'none';
        $st = db()->prepare("INSERT INTO documents(user_id, label_id, file_name, mime, size, docanalyzer_doc_id, file_path, ocr_status) VALUES(?,?,?,?,?,?,?,?)"); 
        $st->bind_param("iississ", $user['id'], $label_id, $f['name'], $f['type'], $f['size'], $docanalyzer_doc_id, $localPath, $ocr_status); 
        $st->execute();
        
        ob_end_clean();
        json_out(['success' => true, 'docid' => $docanalyzer_doc_id]);
    }
    elseif ($action === 'download') {
        // Download file
        $doc_id = intval($_GET['id'] ?? 0);
        
        if (!$doc_id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID mancante'], 400);
        }
        
        $st = db()->prepare("SELECT file_name, file_path, mime FROM documents WHERE id=? AND user_id=?");
        $st->bind_param("ii", $doc_id, $user['id']);
        $st->execute();
        $r = $st->get_result();
        
        if (!($doc = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'File non trovato'], 404);
        }
        
        if (!file_exists($doc['file_path'])) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'File fisico non trovato'], 404);
        }
        
        // Download
        ob_end_clean();
        header('Content-Type: ' . $doc['mime']);
        header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
        header('Content-Length: ' . filesize($doc['file_path']));
        readfile($doc['file_path']);
        exit;
    }
    elseif ($action === 'change_category') {
        // Cambia categoria di un documento (con eliminazione e ricaricamento)
        $doc_id = intval($_POST['id'] ?? 0);
        $new_category = trim($_POST['category'] ?? '');
        
        if (!$doc_id || !$new_category) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Dati mancanti'], 400);
        }
        
        if (!is_pro() && $new_category !== 'master') {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Funzione riservata a Pro'], 403);
        }
        
        // 1. Ottieni documento con vecchia label
        $st = db()->prepare("
            SELECT d.*, l.name as old_category, l.docanalyzer_label_id as old_label_name
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
        
        if ($doc['old_category'] === $new_category) {
            ob_end_clean();
            json_out(['success' => true, 'message' => 'Già nella categoria corretta']);
        }
        
        // Verifica che il file fisico esista
        if (!file_exists($doc['file_path'])) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'File fisico non trovato sul server'], 404);
        }
        
        error_log("=== CHANGE CATEGORY START (ELIMINA E RICARICA) ===");
        error_log("Doc ID: {$doc['id']}, DocAnalyzer ID: {$doc['docanalyzer_doc_id']}");
        error_log("Old: {$doc['old_category']}, New: {$new_category}");
        error_log("File path: {$doc['file_path']}");
        
        // 3. ELIMINA DA DOCANALYZER
        try {
            $docAnalyzer = new DocAnalyzerClient();
            
            error_log("Deleting from DocAnalyzer: {$doc['docanalyzer_doc_id']}");
            $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
            
            error_log("Document deleted successfully");
            
        } catch (Exception $e) {
            error_log("ERROR deleting from DocAnalyzer: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore eliminazione da DocAnalyzer: ' . $e->getMessage()], 500);
        }
        
        // 4. RICARICA CON NUOVA LABEL
        try {
            error_log("Re-uploading to DocAnalyzer with new label: {$new_label['docanalyzer_label_id']}");
            
            $result = $docAnalyzer->uploadAndTag(
                $doc['file_path'],
                $doc['file_name'],
                $new_label['docanalyzer_label_id']
            );
            
            $new_docid = $result['docid'];
            
            error_log("Re-upload SUCCESS: new docid=$new_docid");
            
        } catch (Exception $e) {
            error_log("ERROR re-uploading to DocAnalyzer: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore ricaricamento su DocAnalyzer: ' . $e->getMessage()], 500);
        }
        
        // 5. SPOSTA FILE LOCALE (opzionale, per organizzazione)
        $newUploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/{$new_category}";
        if (!is_dir($newUploadDir)) {
            mkdir($newUploadDir, 0755, true);
        }
        
        $newLocalPath = "$newUploadDir/" . basename($doc['file_path']);
        
        if ($doc['file_path'] !== $newLocalPath) {
            if (!@rename($doc['file_path'], $newLocalPath)) {
                // Se rename fallisce, copia
                @copy($doc['file_path'], $newLocalPath);
                @unlink($doc['file_path']);
            }
        }
        
        // 6. UPDATE DB (reset OCR status perché è un nuovo documento)
        $st = db()->prepare("UPDATE documents SET label_id = ?, docanalyzer_doc_id = ?, file_path = ?, ocr_status = 'none' WHERE id = ? AND user_id = ?");
        $st->bind_param("issii", $new_label['id'], $new_docid, $newLocalPath, $doc_id, $user['id']);
        $st->execute();
        
        error_log("=== CHANGE CATEGORY SUCCESS ===");
        
        ob_end_clean();
        json_out(['success' => true, 'message' => 'Documento spostato correttamente']);
    }
    elseif ($action === 'ocr') {
        // Esegue OCR su un documento
        $doc_id = intval($_POST['id'] ?? 0);
        
        if (!$doc_id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID mancante'], 400);
        }
        
        // Verifica che il documento esista e appartenga all'utente
        $st = db()->prepare("SELECT docanalyzer_doc_id, file_name, ocr_status FROM documents WHERE id=? AND user_id=?");
        $st->bind_param("ii", $doc_id, $user['id']);
        $st->execute();
        $r = $st->get_result();
        
        if (!($doc = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
        }
        
        // Controlla se OCR già fatto/in corso
        if ($doc['ocr_status'] === 'completed') {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'OCR già eseguito su questo documento'], 400);
        }
        
        if ($doc['ocr_status'] === 'pending') {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'OCR già in corso su questo documento'], 400);
        }
        
        error_log("=== OCR REQUEST ===");
        error_log("Doc ID: {$doc_id}, DocAnalyzer ID: {$doc['docanalyzer_doc_id']}, File: {$doc['file_name']}");
        
        try {
            $docAnalyzer = new DocAnalyzerClient();
            $result = $docAnalyzer->ocrDocument($doc['docanalyzer_doc_id']);
            
            if ($result && isset($result['queue'])) {
                // Aggiorna stato a "pending"
                $st = db()->prepare("UPDATE documents SET ocr_status='pending' WHERE id=?");
                $st->bind_param("i", $doc_id);
                $st->execute();
                
                error_log("OCR queued successfully: " . json_encode($result['queue']));
                ob_end_clean();
                json_out([
                    'success' => true, 
                    'message' => 'OCR avviato con successo. Il documento verrà processato nei prossimi minuti.',
                    'queue' => $result['queue'],
                    'status' => 'pending'
                ]);
            } else {
                throw new Exception('Risposta OCR non valida');
            }
            
        } catch (Exception $e) {
            error_log("OCR Error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore OCR: ' . $e->getMessage()], 500);
        }
    }
    elseif ($action === 'ocr_complete') {
        // Segna OCR come completato (chiamata manuale o webhook futuro)
        $doc_id = intval($_POST['id'] ?? 0);
        
        if (!$doc_id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID mancante'], 400);
        }
        
        $st = db()->prepare("UPDATE documents SET ocr_status='completed' WHERE id=? AND user_id=?");
        $st->bind_param("ii", $doc_id, $user['id']);
        $st->execute();
        
        if ($st->affected_rows > 0) {
            error_log("OCR marked as completed for doc_id: $doc_id");
            ob_end_clean();
            json_out(['success' => true, 'message' => 'OCR segnato come completato']);
        } else {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato o già completato'], 404);
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0); 
        if (!$id) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID mancante'], 400);
        }
        
        $st = db()->prepare("SELECT docanalyzer_doc_id, file_path FROM documents WHERE id=? AND user_id=?"); 
        $st->bind_param("ii", $id, $user['id']); 
        $st->execute(); 
        $r = $st->get_result(); 
        if (!($row = $r->fetch_assoc())) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Non trovato'], 404);
        }
        
        $docanalyzer_doc_id = $row['docanalyzer_doc_id'];
        $file_path = $row['file_path'];
        
        // Elimina da DocAnalyzer
        if ($docanalyzer_doc_id) {
            try {
                $docAnalyzer = new DocAnalyzerClient();
                $docAnalyzer->deleteDocument($docanalyzer_doc_id);
            } catch (Exception $e) {
                error_log("Errore eliminazione DocAnalyzer: " . $e->getMessage());
            }
        }
        
        // Elimina file locale
        if ($file_path && file_exists($file_path)) {
            @unlink($file_path);
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
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Errore server: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}
