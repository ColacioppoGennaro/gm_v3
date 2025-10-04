<?php
/**
 * GM_V3 - Document Controller
 * 
 * Gestisce endpoint API per documenti
 */

if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

require_once __DIR__ . '/../models/Document.php';

class DocumentController {
    
    /**
     * GET /api/documents
     * Lista tutti i documenti dell'utente
     */
    public static function index() {
        // ⚠️ TODO: Implementare autenticazione vera
        // Per ora usiamo l'utente di test
        $userId = 'user-test-001'; // Mario Rossi
        
        try {
            $documents = Document::getAllByUser($userId);
            
            Response::success($documents, 'Documenti recuperati con successo');
            
        } catch (Exception $e) {
            Response::handleException($e);
        }
    }
    
    /**
     * POST /api/documents
     * Carica nuovo documento
     */
    public static function store() {
        // ⚠️ TODO: Implementare autenticazione vera
        $userId = 'user-test-001';
        $userTier = 'Free'; // ⚠️ Recuperare dal DB in autenticazione vera
        
        try {
            // Verifica file caricato
            if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                Response::validationError('Nessun file caricato', ['file' => 'File richiesto']);
            }
            
            $file = $_FILES['file'];
            
            // Validazione
            $validator = new Validator();
            if (!$validator->validateUpload($file, $userTier)) {
                Response::validationError('Validazione fallita', $validator->getErrors());
            }
            
            // Verifica limite documenti
            if (Document::hasReachedLimit($userId, $userTier)) {
                $limits = getLimitsForTier($userTier);
                Response::error(
                    "Hai raggiunto il limite di {$limits['maxDocs']} documenti. Elimina vecchi file o passa a Pro.",
                    403
                );
            }
            
            // Categoria (solo Pro)
            $categoryId = null;
            if ($userTier === 'Pro' && !empty($_POST['category'])) {
                $categoryId = (int)$_POST['category'];
            }
            
            // Crea cartella uploads se non esiste
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
            // Crea sottocartella per utente
            $userUploadDir = UPLOAD_DIR . $userId . '/';
            if (!is_dir($userUploadDir)) {
                mkdir($userUploadDir, 0755, true);
            }
            
            // Genera nome file sicuro
            $documentId = Document::generateId();
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeFileName = $documentId . '.' . $extension;
            $filePath = $userUploadDir . $safeFileName;
            
            // Sposta file caricato
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                Response::serverError('Errore durante il salvataggio del file');
            }
            
            // Salva nel database
            $documentData = [
                'id' => $documentId,
                'user_id' => $userId,
                'name' => $safeFileName,
                'original_name' => $file['name'],
                'file_path' => $filePath,
                'size' => $file['size'],
                'type' => $file['type'],
                'category_id' => $categoryId,
                'docanalyzer_id' => null // ⚠️ TODO: Integrare docAnalyzer.ai
            ];
            
            if (!Document::create($documentData)) {
                // Rimuovi file se inserimento DB fallisce
                unlink($filePath);
                Response::serverError('Errore durante il salvataggio dei dati');
            }
            
            // ⚠️ TODO: Inviare file a docAnalyzer.ai per vettorializzazione
            // $docanalyzerId = DocAnalyzerService::uploadDocument($filePath, $userId);
            // Document::updateDocAnalyzerId($documentId, $docanalyzerId);
            
            // Risposta successo
            Response::success([
                'id' => $documentId,
                'name' => $safeFileName,
                'size' => $file['size'],
                'type' => $file['type'],
                'uploadDate' => date('Y-m-d H:i:s'),
                'category' => $categoryId
            ], 'Documento caricato con successo', 201);
            
        } catch (Exception $e) {
            Response::handleException($e);
        }
    }
    
    /**
     * DELETE /api/documents/{id}
     * Elimina documento
     */
    public static function destroy($documentId) {
        // ⚠️ TODO: Implementare autenticazione vera
        $userId = 'user-test-001';
        
        try {
            // Verifica che documento esista e appartenga all'utente
            $document = Document::getById($documentId, $userId);
            
            if (!$document) {
                Response::notFound('Documento non trovato');
            }
            
            // ⚠️ TODO: Eliminare da docAnalyzer.ai
            // if ($document['docanalyzer_id']) {
            //     DocAnalyzerService::deleteDocument($document['docanalyzer_id']);
            // }
            
            // Elimina da DB e file system
            if (!Document::delete($documentId, $userId)) {
                Response::serverError('Errore durante l\'eliminazione del documento');
            }
            
            Response::success(null, 'Documento eliminato con successo');
            
        } catch (Exception $e) {
            Response::handleException($e);
        }
    }
    
    /**
     * GET /api/documents/{id}
     * Dettagli singolo documento
     */
    public static function show($documentId) {
        // ⚠️ TODO: Implementare autenticazione vera
        $userId = 'user-test-001';
        
        try {
            $document = Document::getById($documentId, $userId);
            
            if (!$document) {
                Response::notFound('Documento non trovato');
            }
            
            Response::success([
                'id' => $document['id'],
                'name' => $document['name'],
                'originalName' => $document['original_name'],
                'size' => (int)$document['size'],
                'type' => $document['type'],
                'uploadDate' => $document['upload_date'],
                'category' => $document['category_id']
            ]);
            
        } catch (Exception $e) {
            Response::handleException($e);
        }
    }
}
