<?php
require_once __DIR__.'/../_core/helpers.php';
require_once __DIR__.'/../_core/db.php';
session_start();

$user = user();
$action = $_GET['a'] ?? '';
$db = db();

if ($action === 'list') {
    // Elenco documenti dell'utente
    $st = $db->prepare("SELECT d.id, d.file_name, d.file_path, d.label_id, d.docanalyzer_doc_id, l.name as category
                        FROM documents d
                        LEFT JOIN labels l ON d.label_id = l.id
                        WHERE d.user_id=? ORDER BY d.id DESC");
    $st->execute([$user['id']]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    json_out(['success' => true, 'data' => $docs]);
}

elseif ($action === 'add') {
    // Aggiungi documento
    $file_name = trim($_POST['file_name'] ?? '');
    $file_path = trim($_POST['file_path'] ?? '');
    $label_id = intval($_POST['label_id'] ?? 0);

    if (!$file_name || !$file_path || !$label_id) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Dati mancanti'], 400);
    }

    // Verifica che la label appartenga all'utente
    $st = $db->prepare("SELECT id FROM labels WHERE id=? AND user_id=?");
    $st->execute([$label_id, $user['id']]);
    if (!$st->fetch(PDO::FETCH_ASSOC)) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Categoria non valida'], 400);
    }

    // Inserisci documento
    $st = $db->prepare("INSERT INTO documents(user_id, file_name, file_path, label_id) VALUES(?,?,?,?)");
    $st->execute([$user['id'], $file_name, $file_path, $label_id]);
    $newId = $db->lastInsertId();

    ob_end_clean();
    json_out(['success' => true, 'id' => $newId]);
}

elseif ($action === 'delete') {
    // Elimina documento
    $doc_id = intval($_POST['doc_id'] ?? 0);

    if (!$doc_id) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'ID documento mancante'], 400);
    }

    // Ottieni documento
    $st = $db->prepare("SELECT id, file_path, docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?");
    $st->execute([$doc_id, $user['id']]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);

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
        // Si continua comunque
    }

    // Elimina file fisico
    @unlink($doc['file_path']);

    // Elimina dal DB
    $st = $db->prepare("DELETE FROM documents WHERE id=? AND user_id=?");
    $st->execute([$doc_id, $user['id']]);

    ob_end_clean();
    json_out(['success' => true]);
}

elseif ($action === 'change_category') {
    $doc_id = intval($_POST['doc_id'] ?? 0);
    $new_category = trim($_POST['category'] ?? '');

    if (!$doc_id || !$new_category) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'ID documento o categoria mancante'], 400);
    }

    // Ottieni il documento
    $st = $db->prepare("SELECT id, file_path, file_name, label_id, docanalyzer_doc_id FROM documents WHERE id=? AND user_id=?");
    $st->execute([$doc_id, $user['id']]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
    }

    // Ottieni la nuova label
    $st = $db->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE user_id=? AND name=?");
    $st->execute([$user['id'], $new_category]);
    $new_label = $st->fetch(PDO::FETCH_ASSOC);

    if (!$new_label) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
    }

    // Se la categoria è già corretta
    if ($doc['label_id'] == $new_label['id']) {
        ob_end_clean();
        json_out(['success' => true, 'message' => 'Già nella categoria corretta']);
    }

    // Verifica che il file fisico esista
    if (!file_exists($doc['file_path'])) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'File fisico non trovato sul server'], 404);
    }

    // Eliminazione dal DocAnalyzer
    try {
        $docAnalyzer = new DocAnalyzerClient();
        $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
    } catch (Exception $e) {
        error_log("Errore eliminazione da DocAnalyzer: " . $e->getMessage());
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Errore eliminazione da DocAnalyzer: ' . $e->getMessage()], 500);
    }

    // Ricarica su DocAnalyzer con la nuova label
    try {
        $result = $docAnalyzer->uploadAndTag(
            $doc['file_path'],
            $doc['file_name'],
            $new_label['docanalyzer_label_id']
        );
        $new_docid = $result['docid'];
    } catch (Exception $e) {
        error_log("Errore ricaricamento su DocAnalyzer: " . $e->getMessage());
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Errore ricaricamento su DocAnalyzer: ' . $e->getMessage()], 500);
    }

    // Sposta file locale
    $newUploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/{$new_category}";
    if (!is_dir($newUploadDir)) {
        mkdir($newUploadDir, 0755, true);
    }
    $newLocalPath = "$newUploadDir/" . basename($doc['file_path']);

    if ($doc['file_path'] !== $newLocalPath) {
        if (!@rename($doc['file_path'], $newLocalPath)) {
            @copy($doc['file_path'], $newLocalPath);
        }
    }

    // Aggiorna path e label nel DB
    $st = $db->prepare("UPDATE documents SET file_path=?, docanalyzer_doc_id=?, label_id=? WHERE id=?");
    $st->execute([$newLocalPath, $new_docid, $new_label['id'], $doc['id']]);

    ob_end_clean();
    json_out(['success' => true, 'message' => 'Categoria modificata con successo']);
}

// Se nessuna azione corrisponde
else {
    ob_end_clean();
    json_out(['success' => false, 'message' => 'Azione non valida'], 400);
}
