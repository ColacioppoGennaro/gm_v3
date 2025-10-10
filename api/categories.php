<?php
require_once __DIR__.'/../_core/helpers.php';
require_once __DIR__.'/../_core/db.php';
session_start();

$user = user();
$action = $_GET['a'] ?? '';

if ($action === 'list') {
    $db = db();
    $st = $db->prepare("SELECT id, name, docanalyzer_label_id, created_at FROM labels WHERE user_id=? AND name != 'master' ORDER BY name ASC");
    $st->execute([$user['id']]);
    $data = $st->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    json_out(['success' => true, 'data' => $data]);
}
elseif ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $db = db();

    if (!$name) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Nome categoria mancante'], 400);
    }

    if (strlen($name) > 50) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Nome troppo lungo (max 50 caratteri)'], 400);
    }

    // Verifica che non esista già
    $st = $db->prepare("SELECT id FROM labels WHERE user_id=? AND name=?");
    $st->execute([$user['id'], $name]);
    if ($st->fetch(PDO::FETCH_ASSOC)) {
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
            // Crea label vuota su DocAnalyzer
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
    $st = $db->prepare("INSERT INTO labels(user_id, name, docanalyzer_label_id) VALUES(?,?,?)");
    $st->execute([$user['id'], $name, $docanalyzer_label_id]);

    $newId = $db->lastInsertId();
    error_log("Label created in DB: id=$newId");

    ob_end_clean();
    json_out(['success' => true, 'id' => $newId]);
}
elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    $db = db();

    if (!$id) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'ID categoria mancante'], 400);
    }

    // Verifica che la label esista e sia dell'utente
    $st = $db->prepare("SELECT id, docanalyzer_label_id FROM labels WHERE id=? AND user_id=?");
    $st->execute([$id, $user['id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Categoria non trovata'], 404);
    }

    // Elimina su DocAnalyzer
    try {
        $docAnalyzer = new DocAnalyzerClient();
        $docAnalyzer->deleteLabel($row['docanalyzer_label_id']);
    } catch (Exception $e) {
        error_log("ERROR deleting label on DocAnalyzer: " . $e->getMessage());
        // Continua comunque con l'eliminazione locale
    }

    // Elimina dal DB locale
    $st = $db->prepare("DELETE FROM labels WHERE id=? AND user_id=?");
    $st->execute([$id, $user['id']]);

    ob_end_clean();
    json_out(['success' => true]);
}
