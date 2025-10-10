<?php
require_once __DIR__.'/../_core/helpers.php';
require_once __DIR__.'/../_core/db.php';
session_start();

$user = user();
$action = $_GET['a'] ?? '';

if ($action === 'downgrade') {
    ob_start();

    $db = db();

    // Ottieni la label master
    $master = $db->prepare("SELECT id FROM labels WHERE user_id = ? AND name = 'master'");
    $master->execute([$user['id']]);
    $masterRow = $master->fetch(PDO::FETCH_ASSOC);

    if (!$masterRow) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Label master non trovata'], 404);
    }

    // Ottieni i documenti da spostare
    $st = $db->prepare("
        SELECT d.id, d.docanalyzer_doc_id, d.file_path, d.file_name, l.docanalyzer_label_id as old_label
        FROM documents d
        JOIN labels l ON d.label_id = l.id
        WHERE d.user_id = ? AND l.name != 'master'
    ");
    $st->execute([$user['id']]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC);

    // Sposta ogni documento su master (via DocAnalyzer)
    $docAnalyzer = new DocAnalyzerClient();
    $masterLabel = $db->prepare("SELECT docanalyzer_label_id FROM labels WHERE id=?");
    $masterLabel->execute([$masterRow['id']]);
    $masterLabelRow = $masterLabel->fetch(PDO::FETCH_ASSOC);

    foreach ($docs as $doc) {
        try {
            error_log("Moving doc {$doc['id']} to master");

            // Elimina da DocAnalyzer
            $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);

            // Ricarica su label master
            $result = $docAnalyzer->uploadAndTag(
                $doc['file_path'],
                $doc['file_name'],
                $masterLabelRow['docanalyzer_label_id']
            );

            // Update DB
            $st2 = $db->prepare("UPDATE documents SET label_id=?, docanalyzer_doc_id=? WHERE id=?");
            $st2->execute([$masterRow['id'], $result['docid'], $doc['id']]);

            // Sposta file locale
            $newPath = __DIR__ . "/../_var/uploads/{$user['id']}/master/" . basename($doc['file_path']);
            $newDir = dirname($newPath);
            if (!is_dir($newDir)) mkdir($newDir, 0755, true);

            @rename($doc['file_path'], $newPath);

            // Update path nel DB
            $st3 = $db->prepare("UPDATE documents SET file_path=? WHERE id=?");
            $st3->execute([$newPath, $doc['id']]);

        } catch (Exception $e) {
            error_log("Error moving doc {$doc['id']}: " . $e->getMessage());
        }
    }

    // Downgrade utente
    $st4 = $db->prepare("UPDATE users SET role='free' WHERE id=?");
    $st4->execute([$user['id']]);

    $_SESSION['role'] = 'free';

    error_log("Downgrade completed for user {$user['id']}");

    ob_end_clean();
    json_out(['success' => true, 'message' => 'Downgrade completato. Ora sei Free.']);
}
elseif ($action === 'delete') {
    ob_start();

    $db = db();

    error_log("=== DELETE ACCOUNT: user_id={$user['id']}, email={$user['email']} ===");

    // 1. Ottieni tutti i documenti
    $st = $db->prepare("SELECT id, docanalyzer_doc_id, file_path FROM documents WHERE user_id=?");
    $st->execute([$user['id']]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC);

    // 2. Elimina da DocAnalyzer
    $docAnalyzer = new DocAnalyzerClient();
    foreach ($docs as $doc) {
        try {
            if ($doc['docanalyzer_doc_id']) {
                $docAnalyzer->deleteDocument($doc['docanalyzer_doc_id']);
            }
        } catch (Exception $e) {
            error_log("Error deleting doc {$doc['id']}: " . $e->getMessage());
        }
    }

    // 3. Elimina file locale
    foreach ($docs as $doc) {
        @unlink($doc['file_path']);
    }

    // 4. Elimina documenti dal DB
    $db->prepare("DELETE FROM documents WHERE user_id=?")->execute([$user['id']]);

    // 5. Elimina labels dal DB
    $db->prepare("DELETE FROM labels WHERE user_id=?")->execute([$user['id']]);

    // 6. Elimina utente dal DB
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$user['id']]);

    // 7. Distruggi la sessione
    session_destroy();

    error_log("Account deleted: user_id={$user['id']}");

    ob_end_clean();
    json_out(['success' => true, 'message' => 'Account eliminato con successo']);
}
