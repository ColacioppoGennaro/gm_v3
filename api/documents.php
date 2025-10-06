<?php
// --- OCR recommendation helpers ------------------------------------------------

/**
 * Decide se consigliare l'OCR per un file caricato.
 * Regole:
 * - solo PDF e immagini
 * - immagini: sempre sì (non hanno text layer)
 * - PDF: sì se NON ha un text layer rilevante
 */
function shouldRecommendOCR($filePath, $mime) {
    if (!is_string($filePath) || $filePath === '' || !file_exists($filePath)) {
        return false; // file non valido -> non suggerisco nulla
    }

    // Normalizza MIME
    $mime = strtolower(trim((string)$mime));

    // Solo PDF e immagini
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($mime, $allowed, true)) {
        return false;
    }

    // Immagini: sempre consigliato
    if (str_starts_with($mime, 'image/')) {
        return true;
    }

    // PDF: consiglia OCR solo se NON ha text layer
    if ($mime === 'application/pdf') {
        return !pdfHasTextLayer($filePath);
    }

    return false;
}

/**
 * Rileva se un PDF contiene un layer testuale "sufficiente".
 * Usa smalot/pdfparser (composer require smalot/pdfparser).
 * In caso di errore/parsing fallito, ritorna false (meglio consigliare OCR).
 */
function pdfHasTextLayer($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }

    // Autoload: prova vari percorsi comuni al progetto
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
            error_log("pdfHasTextLayer: autoload non trovato, impossibile usare smalot/pdfparser.");
            return false;
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);

        $text = $pdf->getText();
        if (!is_string($text)) $text = '';

        $text = trim($text);
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        // Soglia conservativa: >200 caratteri = presumiamo text layer presente
        return $len > 200;
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

    // NB: se la colonna si chiama "docanalyzer_docid" o "docanalyzer_doc_id" 
    //    adegua qui sotto in base al tuo schema.
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
