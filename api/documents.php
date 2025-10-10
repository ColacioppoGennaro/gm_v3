// ... codice precedente ...

// Esempio di sezione da convertire (intorno alla gestione delle categorie):

$st = db()->prepare("SELECT id, name, docanalyzer_label_id FROM labels WHERE user_id = ? AND name = ?");
$st->execute([$user['id'], $new_category]);
$r = $st->fetch(PDO::FETCH_ASSOC);

if (!($new_label = $r)) {
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

// ... gestione DocAnalyzer ...

// Sposta file locale (opzionale, per organizzazione)
$newUploadDir = __DIR__ . "/../_var/uploads/{$user['id']}/{$new_category}";
if (!is_dir($newUploadDir)) {
    mkdir($newUploadDir, 0755, true);
}

$newLocalPath = "$newUploadDir/" . basename($doc['file_path']);

if ($doc['file_path'] !== $newLocalPath) {
    if (!@rename($doc['file_path'], $newLocalPath)) {
        // Se rename fallisce, copia
        @copy($doc['file_path'], $newLocalPath);
    }
}

// Aggiorna path nel DB
$st2 = db()->prepare("UPDATE documents SET file_path=?, docanalyzer_doc_id=?, label_id=? WHERE id=?");
$st2->execute([$newLocalPath, $new_docid, $new_label['id'], $doc['id']]);

// ... resto del codice ...
