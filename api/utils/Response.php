<?php
/**
 * GM_V3 - Response Helper
 * 
 * Gestisce risposte JSON standardizzate per l'API
 */

if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

class Response {
    
    /**
     * Invia risposta JSON di successo
     * 
     * @param mixed $data Dati da restituire
     * @param string $message Messaggio opzionale
     * @param int $statusCode Codice HTTP (default 200)
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Invia risposta JSON di errore
     * 
     * @param string $message Messaggio errore
     * @param int $statusCode Codice HTTP errore
     * @param array $errors Dettagli errori opzionali
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'message' => $message,
            'statusCode' => $statusCode
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        if (DEBUG_MODE && isset($GLOBALS['last_exception'])) {
            $response['debug'] = [
                'file' => $GLOBALS['last_exception']->getFile(),
                'line' => $GLOBALS['last_exception']->getLine(),
                'trace' => $GLOBALS['last_exception']->getTraceAsString()
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Errore 401 - Non autorizzato
     */
    public static function unauthorized($message = 'Non autorizzato') {
        self::error($message, 401);
    }
    
    /**
     * Errore 403 - Accesso negato
     */
    public static function forbidden($message = 'Accesso negato') {
        self::error($message, 403);
    }
    
    /**
     * Errore 404 - Risorsa non trovata
     */
    public static function notFound($message = 'Risorsa non trovata') {
        self::error($message, 404);
    }
    
    /**
     * Errore 422 - Validazione fallita
     */
    public static function validationError($message = 'Errore validazione', $errors = []) {
        self::error($message, 422, $errors);
    }
    
    /**
     * Errore 500 - Errore server
     */
    public static function serverError($message = 'Errore interno del server') {
        self::error($message, 500);
    }
    
    /**
     * Risposta paginata
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Success') {
        $totalPages = ceil($total / $perPage);
        
        self::success([
            'items' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'hasMore' => $page < $totalPages
            ]
        ], $message);
    }
    
    /**
     * Log e cattura eccezioni
     */
    public static function handleException(Exception $e) {
        $GLOBALS['last_exception'] = $e;
        
        logMessage(
            "Exception: " . $e->getMessage() . 
            " in " . $e->getFile() . 
            " line " . $e->getLine(),
            'ERROR'
        );
        
        if (DEBUG_MODE) {
            self::serverError($e->getMessage());
        } else {
            self::serverError('Si è verificato un errore. Riprova più tardi.');
        }
    }
}
