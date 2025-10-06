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
        // tipico se questo file è in .../_src/actions/ e vendor sta alla root del progetto
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
            // Se non c'è autoload, non possiamo parsare: fallback prudente
            error_log("pdfHasTextLayer: autoload non trovato, impossibile usare smalot/pdfparser.");
            return false;
        }

        // Parser PDF
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);

        // Testo estratto
        $text = $pdf->getText();
        if (!is_string($text)) $text = '';

        $text = trim($text);
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        // Soglia conservativa: >200 caratteri = presumiamo text layer presente
        // (riduci a 100 se vuoi essere più "permissivo" e consigliare OCR più spesso)
        return $len > 200;
    } catch (\Throwable $e) {
        // In caso di errore parsing: meglio consigliare OCR (false = "non ha testo")
        error_log("PDF text check failed: " . $e->getMessage());
        return false;
    }
}
