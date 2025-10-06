<?php
// --- OCR recommendation helpers ------------------------------------------------

/**
 * Decide se consigliare l'OCR per un file caricato.
 */
function shouldRecommendOCR($filePath, $mime) {
    if (!is_string($filePath) || $filePath === '' || !file_exists($filePath)) {
        return false;
    }

    $mime = strtolower(trim((string)$mime));
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($mime, $allowed, true)) {
        return false;
    }

    if (str_starts_with($mime, 'image/')) {
        return true; // immagini sempre OCR
    }

    if ($mime === 'application/pdf') {
        return !pdfHasTextLayer($filePath);
    }

    return false;
}

/**
 * Rileva se un PDF contiene un layer testuale "sufficiente".
 */
function pdfHasTextLayer($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }

    $autoloadCandidates = [
        dirname(__DIR__, 2) . '/vendor/autoload.php',
        dirname(__DIR__, 1) . '/vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
    ];

    $autoloadLoaded = false;
    foreach ($autoloadCandidates as $auto) {
        if (is_readable($auto)) {
            require_once $auto;
            $autoloadLoaded = true;
            break;
        }
    }

    try {
        if (!$autoloadLoaded) {
            error_log("pdfHasTextLayer: autoload non trovato.");
            return false;
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);

        $text = $pdf->getText();
        if (!is_string($text)) $text = '';
        $text = trim($text);
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        return $len > 200; // soglia conservativa
    } catch (\Throwable $e) {
        error_log("PDF text check failed: " . $e->getMessage());
        return false;
    }
}

// --- LISTA DOCUMENTI + flag OCR ------------------------------------------------
if (($action ?? null) === 'list') {
    require_login();
    $u = user();
    $uid = (int)($u['id'] ?? 0);
    if ($uid <= 0) {
        json_out(['success' => false, 'message' => 'Accesso negato'], 401);
    }

    $sql = "
        SELECT 
            d.id,
            d.file_name,
            d.size,
            d.mime,
            d.created_at,
            d.file_path,
            l.name AS category,
            d.docanalyzer_docid
        FROM documents d
        LEFT JOIN labels l ON l.id = d.label_id
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC
    ";

    $st = db()->prepare($sql);
    if (!$st) {
        json_out(['success' => false, 'message' => 'DB prepare fallita']);
    }
    $st->bind_param('i', $uid);
    $st->execute();
    $res = $st->get_result();
    $docs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($docs as &$doc) {
        $path = $doc['file_path'] ?? '';
        $mime = $doc['mime'] ?? '';
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            $doc['ocr_recommended'] = false;
        } else {
            $doc['ocr_recommended'] = shouldRecommendOCR($path, $mime);
        }
    }
    unset($doc);

    if (function_exists('ob_get_level') && ob_get_level() > 0) {
        @ob_end_clean();
    }
    json_out(['success' => true, 'data' => $docs]);
}

// --- OCR AVVIO ------------------------------------------------
elseif (($action ?? null) === 'ocr') {
    require_login();
    $u = user();

    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'ID mancante'], 400);
    }

    // Limite Free: 1 OCR
    if (!is_pro()) {
        $ocrCount = db()->query("
            SELECT COUNT(*) as cnt FROM ocr_logs 
            WHERE user_id={$u['id']}
        ")->fetch_assoc();

        if (intval($ocrCount['cnt']) >= 1) {
            ob_end_clean();
            json_out([
                'success' => false,
                'message' => 'Limite OCR Free raggiunto (1). Passa a Pro per OCR illimitato.'
            ], 403);
        }
    }

    // Ottieni documento
    $st = db()->prepare("SELECT docanalyzer_docid, file_name FROM documents WHERE id=? AND user_id=?");
    $st->bind_param("ii", $id, $u['id']);
    $st->execute();
    $r = $st->get_result();

    if (!($doc = $r->fetch_assoc())) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Documento non trovato'], 404);
    }

    try {
        $docAnalyzer = new DocAnalyzerClient();
        $result = $docAnalyzer->ocrDocument($doc['docanalyzer_docid']);

        // Log OCR
        $st = db()->prepare("INSERT INTO ocr_logs(user_id, document_id, created_at) VALUES(?,?,NOW())");
        $st->bind_param("ii", $u['id'], $id);
        $st->execute();

        ob_end_clean();
        json_out([
            'success' => true,
            'message' => 'OCR avviato. Costo: 1 credito per pagina.',
            'workflow_id' => $result['queue'][0] ?? null
        ]);

    } catch (Exception $e) {
        error_log("OCR Error: " . $e->getMessage());
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Errore OCR: ' . $e->getMessage()], 500);
    }
}
