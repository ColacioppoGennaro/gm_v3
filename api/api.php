<?php
/**
 * GM_V3 - API Router Principale
 * 
 * Gestisce routing delle richieste HTTP
 */

// Inizializza applicazione
define('GM_V3_INIT', true);

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Non mostrare errori all'utente

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carica dipendenze
require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/controllers/DocumentController.php';

// Exception handler globale
set_exception_handler(function($e) {
    Response::handleException($e);
});

// ============================================
// ROUTING
// ============================================

// Ottieni metodo HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Ottieni path dalla URI
$uri = $_SERVER['REQUEST_URI'];

// Rimuovi query string
$uri = parse_url($uri, PHP_URL_PATH);

// Rimuovi /api/ dal path
$uri = preg_replace('#^/gm_v3/api/?#', '', $uri);

// Dividi path in segmenti
$segments = array_filter(explode('/', $uri));
$segments = array_values($segments); // Re-index array

// ============================================
// ROUTES
// ============================================

try {
    
    // Test endpoint
    if ((empty($segments) || (count($segments) === 1 && empty($segments[0]))) && $method === 'GET') {
        Response::success([
            'version' => '1.0.0',
            'status' => 'online',
            'timestamp' => date('Y-m-d H:i:s')
        ], 'GM_V3 API is running');
    }
    
    // ============================================
    // DOCUMENTS ROUTES
    // ============================================
    
    if ($segments[0] === 'documents') {
        
        switch ($method) {
            
            // GET /api/documents - Lista documenti
            case 'GET':
                if (count($segments) === 1) {
                    DocumentController::index();
                }
                // GET /api/documents/{id} - Dettagli documento
                elseif (count($segments) === 2) {
                    DocumentController::show($segments[1]);
                }
                break;
            
            // POST /api/documents - Carica documento
            case 'POST':
                if (count($segments) === 1) {
                    DocumentController::store();
                }
                break;
            
            // DELETE /api/documents/{id} - Elimina documento
            case 'DELETE':
                if (count($segments) === 2) {
                    DocumentController::destroy($segments[1]);
                }
                break;
            
            default:
                Response::error('Metodo non permesso', 405);
        }
    }
    
    // ============================================
    // CHAT ROUTES (TODO)
    // ============================================
    
    elseif ($segments[0] === 'chat') {
        Response::error('Endpoint chat non ancora implementato', 501);
    }
    
    // ============================================
    // CALENDAR ROUTES (TODO)
    // ============================================
    
    elseif ($segments[0] === 'calendar') {
        Response::error('Endpoint calendario non ancora implementato', 501);
    }
    
    // ============================================
    // AUTH ROUTES (TODO)
    // ============================================
    
    elseif ($segments[0] === 'auth') {
        Response::error('Endpoint autenticazione non ancora implementato', 501);
    }
    
    // ============================================
    // USER ROUTES (TODO)
    // ============================================
    
    elseif ($segments[0] === 'user') {
        Response::error('Endpoint utente non ancora implementato', 501);
    }
    
    // ============================================
    // ROUTE NON TROVATA
    // ============================================
    
    else {
        Response::notFound('Endpoint non trovato');
    }
    
} catch (Exception $e) {
    Response::handleException($e);
}

// Chiudi connessione database
closeDatabaseConnection();


