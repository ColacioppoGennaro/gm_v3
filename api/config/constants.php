<?php
/**
 * GM_V3 - Costanti Applicazione
 * 
 * Definisce limiti, configurazioni e costanti globali
 */

if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

// ============================================
// LIMITI UTENTI
// ============================================

// Limiti tier FREE
define('FREE_MAX_DOCUMENTS', 5);
define('FREE_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('FREE_MAX_QUERIES_PER_DAY', 20);

// Limiti tier PRO
define('PRO_MAX_DOCUMENTS', 200);
define('PRO_MAX_FILE_SIZE', 150 * 1024 * 1024); // 150 MB
define('PRO_MAX_QUERIES_PER_DAY', 200);

// ============================================
// CONFIGURAZIONE UPLOAD
// ============================================

// Tipi di file permessi
define('ALLOWED_FILE_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/gif'
]);

// Estensioni permesse
define('ALLOWED_FILE_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif'
]);

// Cartella upload (relativa alla root del sito)
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// ============================================
// API ESTERNE - CHIAVI
// ============================================

// docAnalyzer.ai
define('DOCANALYZER_API_KEY', getenv('DOCANALYZER_API_KEY') ?: '');
define('DOCANALYZER_ENDPOINT', 'https://api.docanalyzer.ai/v1/'); // ⚠️ DA VERIFICARE URL REALE

// Gemini AI (fallback generico)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/');

// ============================================
// SESSIONI & AUTENTICAZIONE
// ============================================

// Durata sessione (7 giorni)
define('SESSION_LIFETIME', 7 * 24 * 60 * 60);

// Nome cookie sessione
define('SESSION_COOKIE_NAME', 'gm_v3_session');

// Secret key per JWT (⚠️ CAMBIARE IN PRODUZIONE)
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CHANGE_THIS_SECRET_KEY_IN_PRODUCTION_2024');

// ============================================
// RISPOSTE API
// ============================================

// Codici errore standard
define('ERROR_UNAUTHORIZED', 401);
define('ERROR_FORBIDDEN', 403);
define('ERROR_NOT_FOUND', 404);
define('ERROR_VALIDATION', 422);
define('ERROR_SERVER', 500);

// ============================================
// MODALITÀ DEBUG
// ============================================

// Attiva log dettagliati (solo sviluppo)
define('DEBUG_MODE', true); // ⚠️ Impostare a false in produzione

// Log file
define('ERROR_LOG_FILE', __DIR__ . '/../logs/error.log');
define('API_LOG_FILE', __DIR__ . '/../logs/api.log');

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Europe/Rome');

// ============================================
// FUNZIONI HELPER
// ============================================

/**
 * Ottiene i limiti per un tier specifico
 */
function getLimitsForTier($tier) {
    if ($tier === 'Pro') {
        return [
            'maxDocs' => PRO_MAX_DOCUMENTS,
            'maxFileSize' => PRO_MAX_FILE_SIZE,
            'maxQueries' => PRO_MAX_QUERIES_PER_DAY
        ];
    }
    
    return [
        'maxDocs' => FREE_MAX_DOCUMENTS,
        'maxFileSize' => FREE_MAX_FILE_SIZE,
        'maxQueries' => FREE_MAX_QUERIES_PER_DAY
    ];
}

/**
 * Verifica se un tipo di file è permesso
 */
function isFileTypeAllowed($mimeType, $extension) {
    return in_array($mimeType, ALLOWED_FILE_TYPES) && 
           in_array(strtolower($extension), ALLOWED_FILE_EXTENSIONS);
}

/**
 * Log personalizzato
 */
function logMessage($message, $level = 'INFO') {
    if (!DEBUG_MODE) return;
    
    $logDir = dirname(API_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(API_LOG_FILE, $logEntry, FILE_APPEND);
}
