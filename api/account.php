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
    
    if ($action === 'info') {
        // Informazioni account
        $st = db()->prepare("SELECT email, role, created_at FROM users WHERE id=?");
        $st->bind_param("i", $user['id']);
        $st->execute();
        $r = $st->get_result();
        
        $info = $r->fetch_assoc();
        
        // Conteggi
        $docs = db()->query("SELECT COUNT(*) as cnt FROM documents WHERE user_id={$user['id']}")->fetch_assoc();
        $storage = db()->query("SELECT SUM(size) as total FROM documents WHERE user_id={$user['id']}")->fetch_assoc();
        $categories = db()->query("SELECT COUNT(*) as cnt FROM labels WHERE user_id={$user['id']} AND name!='master'")->fetch_assoc();
        
        // Quota chat oggi
        $day = (new DateTime())->format('Y-m-d');
        $chat = db()->query("SELECT chat_count FROM quotas WHERE user_id={$user['id']} AND day='$day'")->fetch_assoc();
        
        ob_end_clean();
        json_out([
            'success' => true,
            'account' => $info,
            'usage' => [
                'documents' => intval($docs['cnt']),
                'storage_bytes' => intval($storage['total'] ?? 0),
                'categories' => intval($categories['cnt']),
                'chat_today' => intval($chat['chat_count'] ?? 0)
            ]
        ]);
    }
    elseif ($action === 'downgrade') {
        // Downgrade da Pro a Free
        if ($user['role'] !== 'pro') {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Non sei Pro'], 400);
        }
        
        // Verifica limiti Free
        $docs = db()->query("SELECT COUNT(*) as cnt FROM documents WHERE user_id={$user['id']}")->fetch_assoc();
        $docCount = intval($docs['cnt']);
        
        if ($docCount > 5) {
            ob_end_clean();
            json_out([
                'success' => false, 
                'message' => "Hai $docCount documenti. Elimina documenti fino ad averne massimo 5 prima di fare downgrade."
            ], 400);
        }
        
        error_log("=== DOWNGRADE TO FREE: user_id={$user['id']} ===");
        
        // Sposta tutti i documenti su label master
        $st = db()->prepare("SELECT id FROM labels WHERE user_id=? AND name='master'");
        $st->bind_param("i", $user['id']);
        $st->execute();
        $master = $st->get_result()->fetch_assoc();
        
        if (!$master) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Label master non trovata'], 500);
        }
        
        // Ottieni tutti i documenti con le loro label
        $st = db()->prepare("
            SELECT d.id, d.docanalyzer_doc_id, d.file_path, d.file_name, l.docanalyzer_label_id as old_label
            FROM documents d
            JOIN labels l ON d.label_id = l.id
            WHERE d.user_id = ? AND l.name != 'master'
        ");
        $st->bind_param("i", $user['id']);
        $st->execute();
        $docs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Sposta ogni documento su master (via DocAnalyzer)
        $docAnalyzer = new DocAnalyzerClient();
        $masterLabel = db()->query("SELECT docanalyzer_label_id FROM labels WHERE id={$master['id']}")->fetch_assoc();
        
        foreach ($docs as $doc) {
            try {
                error_log("Moving doc {$doc['id']} to master");
                
                // Elimina da DocAnalyzer
                $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
                
                // Ricarica su label master
                $result = $docAnalyzer->uploadAndTag(
                    $doc['file_path'],
                    $doc['file_name'],
                    $masterLabel['docanalyzer_label_id']
                );
                
                // Update DB
                $st = db()->prepare("UPDATE documents SET label_id=?, docanalyzer_doc_id=? WHERE id=?");
                $st->bind_param("isi", $master['id'], $result['docid'], $doc['id']);
                $st->execute();
                
                // Sposta file locale
                $newPath = __DIR__ . "/../_var/uploads/{$user['id']}/master/" . basename($doc['file_path']);
                $newDir = dirname($newPath);
                if (!is_dir($newDir)) mkdir($newDir, 0755, true);
                
                @rename($doc['file_path'], $newPath);
                
                // Update path nel DB
                db()->query("UPDATE documents SET file_path='$newPath' WHERE id={$doc['id']}");
                
            } catch (Exception $e) {
                error_log("Error moving doc {$doc['id']}: " . $e->getMessage());
            }
        }
        
        // Downgrade utente
        $st = db()->prepare("UPDATE users SET role='free' WHERE id=?");
        $st->bind_param("i", $user['id']);
        $st->execute();
        
        $_SESSION['role'] = 'free';
        
        error_log("Downgrade completed for user {$user['id']}");
        
        ob_end_clean();
        json_out(['success' => true, 'message' => 'Downgrade completato. Ora sei Free.']);
    }
    elseif ($action === 'delete') {
        // Eliminazione account
        error_log("=== DELETE ACCOUNT: user_id={$user['id']}, email={$user['email']} ===");
        
        // 1. Ottieni tutti i documenti
        $st = db()->prepare("SELECT id, docanalyzer_doc_id, file_path FROM documents WHERE user_id=?");
        $st->bind_param("i", $user['id']);
        $st->execute();
        $docs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // 2. Elimina da DocAnalyzer
        $docAnalyzer = new DocAnalyzerClient();
        foreach ($docs as $doc) {
            try {
                if ($doc['docanalyzer_doc_id']) {
                    $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
                }
            } catch (Exception $e) {
                error_log("Error deleting doc from DocAnalyzer: " . $e->getMessage());
            }
        }
        
        // 3. Elimina file locali
        $uploadDir = __DIR__ . "/../_var/uploads/{$user['id']}";
        if (is_dir($uploadDir)) {
            // Elimina ricorsivamente
            function deleteDir($dir) {
                if (!is_dir($dir)) return;
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $path = "$dir/$file";
                    is_dir($path) ? deleteDir($path) : unlink($path);
                }
                rmdir($dir);
            }
            deleteDir($uploadDir);
        }
        
        // 4. Elimina label da DocAnalyzer
        try {
            $labels = db()->query("SELECT docanalyzer_label_id FROM labels WHERE user_id={$user['id']}")->fetch_all(MYSQLI_ASSOC);
            foreach ($labels as $label) {
                $l = $docAnalyzer->findLabelByName($label['docanalyzer_label_id']);
                if ($l && isset($l['lid'])) {
                    $docAnalyzer->request('DELETE', "/api/v1/label/{$l['lid']}");
                }
            }
        } catch (Exception $e) {
            error_log("Error deleting labels from DocAnalyzer: " . $e->getMessage());
        }
        
        // 5. Elimina dal DB (CASCADE elimina tutto il resto)
        $st = db()->prepare("DELETE FROM users WHERE id=?");
        $st->bind_param("i", $user['id']);
        $st->execute();
        
        // 6. Logout
        session_destroy();
        
        error_log("Account deleted successfully: user_id={$user['id']}");
        
        ob_end_clean();
        json_out(['success' => true, 'message' => 'Account eliminato']);
    }
    else {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Azione non valida'], 404);
    }
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in account.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
