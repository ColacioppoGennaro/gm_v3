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

        // ✅ NUOVO SISTEMA: area_id e tipo_id
        $areaId = isset($_POST['area_id']) ? (int)$_POST['area_id'] : null;
        $tipoId = isset($_POST['tipo_id']) ? (int)$_POST['tipo_id'] : null;
        
        if (!$areaId) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Area richiesta'], 400);
        }
        
        // Verifica che area esista
        $stmt = $db->prepare("SELECT id, nome FROM settori WHERE id=?");
        $stmt->bind_param("i", $areaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $area = $result->fetch_assoc();
        
        if (!$area) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Area non trovata'], 404);
        }

        // Salva file localmente
        $uploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $area['nome']);
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

        // ✅ LOGICA PESANTE/LEGGERO
        $fileSize = $file['size'];
        $heavyThreshold = 5 * 1024 * 1024; // 5MB
        $isHeavy = $fileSize > $heavyThreshold;
        
        $docAnalyzerDocId = null;
        $geminiEmbedding = null;
        $summary = null;
        $ocrText = null;
        $labelId = null; // Backward compatibility con vecchio sistema
        
        if ($isHeavy) {
            // ═══════════════════════════════════════════
            // DOCUMENTO PESANTE → DocAnalyzer
            // ═══════════════════════════════════════════
            try {
                require_once __DIR__ . '/../_core/DocAnalyzerClient.php';
                
                // Cerca/crea label DocAnalyzer corrispondente all'area
                $stmt = $db->prepare("SELECT id, docanalyzer_label_id FROM labels WHERE user_id=? AND name=?");
                $stmt->bind_param("is", $user['id'], $area['nome']);
                $stmt->execute();
                $result = $stmt->get_result();
                $label = $result->fetch_assoc();
                
                if (!$label) {
                    // Crea label nel DB locale (DocAnalyzer la creerà al primo upload)
                    $stmt = $db->prepare("INSERT INTO labels(user_id, name, docanalyzer_label_id) VALUES(?,?,?)");
                    $docAnalyzerLabelId = $area['nome']; // Usa nome area come label ID
                    $stmt->bind_param("iss", $user['id'], $area['nome'], $docAnalyzerLabelId);
                    $stmt->execute();
                    $labelId = $db->insert_id;
                } else {
                    $labelId = $label['id'];
                    $docAnalyzerLabelId = $label['docanalyzer_label_id'];
                }
                
                $docAnalyzer = new DocAnalyzerClient();
                $result = $docAnalyzer->uploadAndTag($localPath, $fileName, $docAnalyzerLabelId);
                $docAnalyzerDocId = $result['docid'];
                
                error_log("✅ Documento PESANTE uploadato su DocAnalyzer: docid={$docAnalyzerDocId}, label={$area['nome']}");
                
            } catch (Exception $e) {
                error_log("❌ Errore upload DocAnalyzer: " . $e->getMessage());
                @unlink($localPath);
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Errore upload DocAnalyzer: ' . $e->getMessage()], 500);
            }
            
        } else {
            // ═══════════════════════════════════════════
            // DOCUMENTO LEGGERO → Gemini Embedding
            // ═══════════════════════════════════════════
            
            if (!$tipoId) {
                @unlink($localPath);
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Tipo richiesto per documenti leggeri'], 400);
            }
            
            try {
                require_once __DIR__ . '/../_core/GeminiClient.php';
                require_once __DIR__ . '/../_core/AILimits.php';
                
                // Verifica limiti AI
                $limits = AILimits::checkAnalysisLimit($user['id']);
                if (!$limits['allowed']) {
                    @unlink($localPath);
                    ob_end_clean();
                    json_out([
                        'success' => false, 
                        'message' => "Limite analisi AI raggiunto ({$limits['used']}/{$limits['limit']})"
                    ], 403);
                }
                
                $gemini = new GeminiClient();
                
                // OCR/Vision
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/jpg'])) {
                    $ocrText = $gemini->extractTextFromImage($localPath);
                } else {
                    // Per PDF/doc usa metodo alternativo (TODO: implementare)
                    $ocrText = "Testo estratto da " . $fileName;
                }
                
                // Genera riassunto
                $summary = $gemini->summarizeText($ocrText, 200); // Max 200 caratteri
                
                // Genera embedding
                $embeddingResult = $gemini->embedText($ocrText);
                $geminiEmbedding = json_encode($embeddingResult['values'] ?? []);
                
                // Registra uso AI
                AILimits::recordAnalysis($user['id']);
                
                error_log("✅ Documento LEGGERO processato con Gemini: embedding_size=" . strlen($geminiEmbedding));
                
            } catch (Exception $e) {
                error_log("⚠️ Errore Gemini (non critico): " . $e->getMessage());
                // Non blocchiamo il salvataggio, solo logghiamo l'errore
                $summary = "Errore analisi: " . $e->getMessage();
            }
        }
        
        // Salva nel DB con nuovo schema
        $stmt = $db->prepare(
            "INSERT INTO documents(user_id, label_id, area_id, tipo_id, file_name, file_path, mime, size, 
                                   docanalyzer_doc_id, gemini_embedding, summary, ocr_text) 
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "iiiisssissss", 
            $user['id'], $labelId, $areaId, $tipoId, $fileName, $localPath, $mime, $fileSize,
            $docAnalyzerDocId, $geminiEmbedding, $summary, $ocrText
        );
        $stmt->execute();
        
        $documentId = $db->insert_id;
        
        ob_end_clean();
        json_out([
            'success' => true, 
            'document_id' => $documentId,
            'document_name' => $fileName,
            'is_heavy' => $isHeavy,
            'processor' => $isHeavy ? 'DocAnalyzer' : 'Gemini',
            'summary' => $summary
        ]);
        
    } catch (Exception $e) {
                
                // Verifica limiti AI
                $limits = AILimits::checkAnalysisLimit($user['id']);
                if (!$limits['allowed']) {
                    // Non blocchiamo l'upload, ma avvisiamo
                    $aiAnalysis = [
                        'error' => 'Limite analisi AI raggiunto',
                        'message' => "Hai usato {$limits['used']}/{$limits['limit']} analisi questo mese. Passa a Pro per più analisi!"
                    ];
                } else {
                    try {
                        $gemini = new GeminiClient();
                        $aiAnalysis = $gemini->analyzeDocumentForCalendar($localPath, $fileName);
                        
                        // TODO: Salva analisi nel DB quando avremo i campi
                        // $stmt = $db->prepare("UPDATE documents SET ai_extracted_data=?, analysis_status='completed', analyzed_at=NOW() WHERE id=?");
                        // $stmt->bind_param("si", json_encode($aiAnalysis), $documentId);
                        // $stmt->execute();
                        
                    } catch (Exception $e) {
                        error_log("Gemini analysis error: " . $e->getMessage());
                        $aiAnalysis = [
                            'error' => 'Analisi AI fallita',
                            'message' => 'Documento caricato ma analisi non riuscita: ' . $e->getMessage()
                        ];
                    }
                }
            }
            
            ob_end_clean();
            json_out([
                'success' => true, 
                'document_id' => $documentId,
                'document_name' => $fileName,
                'ai_analysis' => $aiAnalysis
            ]);
            
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
