<?php
/**
 * GM_V3 - CORS Configuration
 * 
 * Configurazione Cross-Origin Resource Sharing
 * per permettere chiamate dal frontend React
 */

if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

// ============================================
// ORIGINI PERMESSE
// ============================================

// In sviluppo: permetti tutto
// In produzione: specifica domini esatti
$allowedOrigins = [
    'http://localhost:3000',           // Vite dev server
    'http://localhost:5173',           // Vite alternativo
    'https://gruppogea.net',           // Produzione
    'https://www.gruppogea.net',       // Produzione con www
];

// Ottieni origin della richiesta
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Se origin è nella whitelist, permettilo
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // In sviluppo permetti tutti
    // ⚠️ In produzione commentare questa riga
    header("Access-Control-Allow-Origin: *");
}

// ============================================
// HEADERS PERMESSI
// ============================================

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 ore

// ============================================
// GESTIONE PREFLIGHT
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
