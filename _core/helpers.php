<?php
/**
 * _core/helpers.php
 * 
 * Funzioni helper globali per gm_v3:
 * - env_get(): Carica e legge variabili .env
 * - db(): Connessione database MySQL (RIMOSSA)
 * - json_out(): Output JSON standard
 * - require_login(), user(), is_pro(), is_admin(): Gestione autenticazione
 * - ratelimit(): Rate limiting semplice
 * - hash_password(), verify_password(): Gestione password
 * - shouldRecommendOCR(), pdfHasTextLayer(): Logica OCR per documenti
 * - ensure_ocr_table(): Auto-creazione tabella ocr_logs
 */

// Legge .env una sola volta, con gestione BOM/virgolette e commenti
function env_get($key, $default = null) {
    static $E = null;
    if ($E === null) {
        $E = [];
        $paths = [
            dirname(__DIR__, 2) . "/config/gm_v3/.env",
            __DIR__ . "/../.env",
        ];
        foreach ($paths as $p) {
            if (!is_readable($p)) continue;
            $raw = file_get_contents($p);
            // rimuovi BOM se presente
            if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
            foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) continue;
                $k = trim($parts[0]);
                $v = trim($parts[1]);
                // togli eventuali virgolette avvolgenti
                if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                    (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                    $v = substr($v, 1, -1);
                }
                $E[$k] = $v;
            }
            break; // usa il primo .env trovato
        }
    }
    return array_key_exists($key, $E) ? $E[$key] : $default;
}

function json_out($a, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_login() {
    if (!isset($_SESSION['user_id'])) json_out(['success' => false, 'message' => 'Accesso negato'], 401);
}
function user() { return ['id' => $_SESSION['user_id'] ?? null, 'role' => $_SESSION['role'] ?? 'free', 'email' => $_SESSION['email'] ?? null]; }
function is_pro() { return (user()['role'] ?? 'free') === 'pro'; }
function is_admin() { return (user()['role'] ?? 'free') === 'admin'; }

function ratelimit($key, $limit, $window = 60) {
    $file = sys_get_temp_dir() . "/gmv3_rl_" . md5($key);
    $data = ['count' => 0, 'until' => time() + $window];
    if (file_exists($file)) $data = json_decode(file_get_contents($file), true) ?: $data;
    if (time() > ($data['until'] ?? 0)) $data = ['count' => 0, 'until' => time() + $window];
    $data['count']++;
    file_put_contents($file, json_encode($data));
    if ($data['count'] > $limit) json_out(['success' => false, 'message' => 'Troppi tentativi, riprova tra poco']);
}
function hash_password($p){ return password_hash($p, PASSWORD_BCRYPT); }
function verify_password($p,$h){ return password_verify($p,$h); }

function shouldRecommendOCR($filePath, $mime) {
    // Solo per PDF e immagini
    if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])) {
        return false;
    }
    
    // Per immagini: sempre consigliato
    if (strpos($mime, 'image/') === 0) {
        return true;
    }
    
    // Per PDF: controlla text layer
    if ($mime === 'application/pdf') {
        return !pdfHasTextLayer($filePath);
    }
    
    return false;
}

function pdfHasTextLayer($filePath) {
    if (!file_exists($filePath)) return false;
    
    try {
        // Leggi primi 50KB del PDF
        $content = file_get_contents($filePath, false, null, 0, 51200);
        
        // Cerca stringhe ASCII lunghe (testo estratto)
        $textMatches = preg_match_all('/[\x20-\x7E]{50,}/', $content, $matches);
        
        // Se trova >200 caratteri ASCII consecutivi → ha testo
        if ($textMatches > 0) {
            $totalChars = array_sum(array_map('strlen', $matches[0]));
            return $totalChars > 200;
        }
        
        return false;
    } catch (\Throwable $e) {
        error_log("PDF text check failed: " . $e->getMessage());
        return false; // default: consiglia OCR
    }
}

function ensure_ocr_table() {
    static $checked = false;
    if ($checked) return;
    
    // Questa funzione ora necessita di un'istanza DB passata come argomento
    // Esempio di modifica: function ensure_ocr_table($db_connection) { ... }
    // Poiché db() è stato rimosso, il codice qui sotto non funzionerà più
    // e dovrà essere adattato dove viene chiamato.
    /*
    db()->query("
        CREATE TABLE IF NOT EXISTS ocr_logs(
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          document_id INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user(user_id),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    */
    
    $checked = true;
}
