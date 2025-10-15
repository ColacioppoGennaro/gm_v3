<?php
/**
 * api/documents.php
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
        // Elenco documenti dell'utente
        $stmt = $db->prepare("SELECT d.id, d.file_name, d.file_path, d.label_id, d.docanalyzer_doc_id, d.size, d.mime, d.created_at, l.name as category
                            FROM documents d
                            LEFT JOIN labels l ON d.label_id = l.id
                            WHERE d.user_id=? ORDER BY d.id DESC");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $docs = [];
        while ($row = $result->fetch_assoc()) {
            // Calcola OCR raccomandato
            $ocrRecommended = false;
            if ($row['file_path'] && file_exists($row['file_path'])) {
                $ocrRecommended = shouldRecommendOCR($row['file_path'], $row['mime']);
            }
            
            $row['ocr_recommended'] = $ocrRecommended;
            $docs[] = $row;
        }

        ob_end_clean();
        json_out(['success' => true, 'data' => $docs]);
    }

    elseif ($action === 'upload') {
        // Verifica quota
        $max = is_pro() ? 200 : 5;
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM documents WHERE user_id=?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] >= $max) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Limite documenti raggiunto'], 403);
        }

        if (!isset($_FILES['file'])) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Nessun file caricato'], 400);
        }

        $file = $_FILES['file'];
        $category = trim($_POST['category'] ?? '');

        // Validazione
        if ($file['error'] !== UPLOAD_ERR_OK) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore upload file'], 400);
        }

        $maxSize = (is_pro() ? 150 : 50) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'File troppo grande'], 400);
        }

        $allowedMimes = [
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'text/csv', 
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg', 'image/png', 'image/jpg'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes)) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Tipo file non supportato'], 400);
        }

        // Determina categoria
        if (is_pro()) {
            if (!$category) {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Categoria richiesta'], 400);
            }
            
            $stmt = $db->prepare("SELECT id, docanalyzer_label_id FROM labels WHERE user_id=? AND name=?");
            $stmt->bind_param("is", $user['id'], $category);
            $stmt->execute();
            $result = $stmt->get_result();
            $label = $result->fetch_assoc();
            
            if (!$label) {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
            }
        } else {
            $stmt = $db->prepare("SELECT id, docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $label = $result->fetch_assoc();
            
            if (!$label) {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Label master non trovata'], 500);
            }
        }

        // Salva file localmente
        $uploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/" . ($category ?: 'master');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = basename($file['name']);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        $localPath = $uploadDir . '/' . time() . '_' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $localPath)) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore salvataggio file'], 500);
        }

        // Upload su DocAnalyzer
        try {
            $docAnalyzer = new DocAnalyzerClient();
            $result = $docAnalyzer->uploadAndTag($localPath, $fileName, $label['docanalyzer_label_id']);
            
            // Salva nel DB
            $stmt = $db->prepare("INSERT INTO documents(user_id, label_id, file_name, file_path, mime, size, docanalyzer_doc_id) VALUES(?,?,?,?,?,?,?)");
            $stmt->bind_param("iisssis", $user['id'], $label['id'], $fileName, $localPath, $mime, $file['size'], $result['docid']);
            $stmt->execute();
            
            ob_end_clean();
            json_out(['success' => true, 'id' => $db->insert_id]);
            
        } catch (Exception $e) {
            @unlink($localPath);
            error_log("Upload error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore DocAnalyzer: ' . $e->getMessage()], 500);
        }
    }
    
    // Ottieni singolo documento (per visualizzazione da calendario)
    elseif ($action === 'get') {    
        $docId = intval($_GET['id'] ?? 0);
    
        if (!$docId) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID documento mancante'], 400);
        }
    
        // Verifica ownership
        $stmt = $db->prepare("SELECT id, file_name, file_name as original_name, size, mime, created_at FROM documents WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $docId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
        }
    
        $document = $result->fetch_assoc();
    
        ob_end_clean();
        json_out([
            'success' => true,
            'document' => $document
        ]);
    }

    elseif ($action === 'delete') {
        $docId = intval($_POST['id'] ?? 0);

        if (!$docId) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID documento mancante'], 400);
        }

        $stmt = $db->prepare("SELECT id, file_path, docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $docId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();

        if (!$doc) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
        }

        // Elimina da DocAnalyzer
        try {
            $docAnalyzer = new DocAnalyzerClient();
            if ($doc['docanalyzer_doc_id']) {
                $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
            }
        } catch (Exception $e) {
            error_log("Errore eliminazione da DocAnalyzer: " . $e->getMessage());
        }

        // Elimina file fisico
        @unlink($doc['file_path']);

        // Elimina dal DB
        $stmt = $db->prepare("DELETE FROM documents WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $docId, $user['id']);
        $stmt->execute();

        ob_end_clean();
        json_out(['success' => true]);
    }

    elseif ($action === 'ocr') {
        ensure_ocr_table();
        
        $docId = intval($_POST['id'] ?? 0);
        
        if (!$docId) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID documento mancante'], 400);
        }

        // Verifica quota OCR (1 per Free)
        if (!is_pro()) {
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ocr_logs WHERE user_id=?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['cnt'] >= 1) {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Limite OCR raggiunto (Free: 1 OCR)'], 403);
            }
        }

        $stmt = $db->prepare("SELECT id, docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $docId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();

        if (!$doc) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
        }

        try {
            $docAnalyzer = new DocAnalyzerClient();
            $docAnalyzer->ocrDocument($doc['docanalyzer_doc_id']);
            
            // Log OCR
            $stmt = $db->prepare("INSERT INTO ocr_logs(user_id, document_id) VALUES(?,?)");
            $stmt->bind_param("ii", $user['id'], $docId);
            $stmt->execute();
            
            ob_end_clean();
            json_out(['success' => true]);
            
        } catch (Exception $e) {
            error_log("OCR error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore OCR: ' . $e->getMessage()], 500);
        }
    }

    elseif ($action === 'change_category') {
        $docId = intval($_POST['id'] ?? 0);
        $newCategory = trim($_POST['category'] ?? '');

        if (!$docId || !$newCategory) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'ID documento o categoria mancante'], 400);
        }

        $stmt = $db->prepare("SELECT id, file_path, file_name, label_id, docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $docId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();

        if (!$doc) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
        }

        $stmt = $db->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE user_id=? AND name=?");
        $stmt->bind_param("is", $user['id'], $newCategory);
        $stmt->execute();
        $result = $stmt->get_result();
        $newLabel = $result->fetch_assoc();

        if (!$newLabel) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
        }

        if ($doc['label_id'] == $newLabel['id']) {
            ob_end_clean();
            json_out(['success' => true, 'message' => 'Già nella categoria corretta']);
        }

        if (!file_exists($doc['file_path'])) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'File fisico non trovato sul server'], 404);
        }

        try {
            $docAnalyzer = new DocAnalyzerClient();
            $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
            
            $result = $docAnalyzer->uploadAndTag(
                $doc['file_path'],
                $doc['file_name'],
                $newLabel['docanalyzer_label_id']
            );
            $newDocid = $result['docid'];
            
            // Sposta file locale
            $newUploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/{$newCategory}";
            if (!is_dir($newUploadDir)) {
                mkdir($newUploadDir, 0755, true);
            }
            $newLocalPath = "$newUploadDir/" . basename($doc['file_path']);

            if ($doc['file_path'] !== $newLocalPath) {
                if (!@rename($doc['file_path'], $newLocalPath)) {
                    @copy($doc['file_path'], $newLocalPath);
                }
            }

            // Aggiorna DB
            $stmt = $db->prepare("UPDATE documents SET file_path=?, docanalyzer_doc_id=?, label_id=? WHERE id=?");
            $stmt->bind_param("ssii", $newLocalPath, $newDocid, $newLabel['id'], $docId);
            $stmt->execute();

            ob_end_clean();
            json_out(['success' => true, 'message' => 'Categoria modificata con successo']);
            
        } catch (Exception $e) {
            error_log("Change category error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    elseif ($action === 'download') {
        $docId = intval($_GET['id'] ?? 0);
        
        if (!$docId) {
            http_response_code(400);
            exit('ID documento mancante');
        }

        $stmt = $db->prepare("SELECT file_path, file_name, mime FROM documents WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $docId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();

        if (!$doc || !file_exists($doc['file_path'])) {
            http_response_code(404);
            exit('Documento non trovato');
        }

        header('Content-Type: ' . $doc['mime']);
        header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
        header('Content-Length: ' . filesize($doc['file_path']));
        readfile($doc['file_path']);
        exit;
    }

    else {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Azione non valida'], 400);
    }

} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in documents.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
