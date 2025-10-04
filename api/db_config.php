<?php
/**
 * GM_V3 - Database Configuration
 * 
 * Configurazione connessione database MariaDB per il progetto gm_v3
 * Hosting: Netsons SSD30
 */

// Previeni accesso diretto al file
if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

// ============================================
// CONFIGURAZIONE DATABASE
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ywrloefq_gm_v3_db');  // Database dedicato per gm_v3
define('DB_USER', 'ywrloefq_gm_user');
define('DB_PASS', '77453209**--Gm');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// FUNZIONE CONNESSIONE DATABASE
// ============================================

/**
 * Crea e restituisce la connessione al database
 * 
 * @return mysqli Oggetto connessione database
 * @throws Exception Se la connessione fallisce
 */
function getDatabaseConnection() {
    static $conn = null;
    
    // Singleton: riusa la connessione se già esiste
    if ($conn !== null) {
        return $conn;
    }
    
    // Crea nuova connessione
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Verifica errori di connessione
    if ($conn->connect_error) {
        error_log("Errore connessione database: " . $conn->connect_error);
        throw new Exception("Impossibile connettersi al database", 500);
    }
    
    // Imposta charset
    if (!$conn->set_charset(DB_CHARSET)) {
        error_log("Errore impostazione charset: " . $conn->error);
    }
    
    return $conn;
}

/**
 * Chiude la connessione al database
 */
function closeDatabaseConnection() {
    static $conn = null;
    if ($conn !== null && $conn instanceof mysqli) {
        $conn->close();
        $conn = null;
    }
}

// ============================================
// FUNZIONI UTILITY DATABASE
// ============================================

/**
 * Esegue una query preparata (sicura contro SQL injection)
 * 
 * @param string $sql Query SQL con placeholder (?)
 * @param string $types Tipi parametri (es. "ssi" = string, string, int)
 * @param array $params Array parametri da bindare
 * @return mysqli_result|bool Risultato query o false
 */
function executeQuery($sql, $types = "", $params = []) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Errore preparazione query: " . $conn->error);
        return false;
    }
    
    // Binda i parametri se presenti
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Errore esecuzione query: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

/**
 * Esegue un INSERT e restituisce l'ID inserito
 * 
 * @param string $sql Query INSERT
 * @param string $types Tipi parametri
 * @param array $params Parametri
 * @return int|false ID record inserito o false
 */
function executeInsert($sql, $types = "", $params = []) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Errore preparazione INSERT: " . $conn->error);
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Errore esecuzione INSERT: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $insertId = $stmt->insert_id;
    $stmt->close();
    
    return $insertId;
}

/**
 * Test connessione database
 * 
 * @return bool True se connessione OK
 */
function testDatabaseConnection() {
    try {
        $conn = getDatabaseConnection();
        $result = $conn->query("SELECT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// CONFIGURAZIONE ERRORI (solo in sviluppo)
// ============================================

// IMPORTANTE: In produzione impostare a false
define('DB_DEBUG_MODE', true);

if (DB_DEBUG_MODE) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    mysqli_report(MYSQLI_REPORT_OFF);
}
