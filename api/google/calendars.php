<?php
// ✅ CONFIGURAZIONE SESSIONE E CARICAMENTO DIPENDENZE
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

session_start();
ob_start();

require_once __DIR__.'/../../_core/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ VERIFICA AUTENTICAZIONE
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = db(); // ✅ ORA FUNZIONA

// Funzione per ottenere i dati OAuth dell'utente
function getOauth($db, $user_id) {
    $st = $db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id=?");
    $st->execute([$user_id]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

// Funzione per salvare/aggiornare i dati OAuth
function setOauth($db, $user_id, $token, $refresh, $expiry) {
    $st = $db->prepare("UPDATE users SET google_oauth_token=?, google_oauth_refresh=?, google_oauth_expiry=? WHERE id=?");
    return $st->execute([$token, $refresh, $expiry, $user_id]);
}

// Funzione per eliminare i dati OAuth dal DB
function deleteOauth($db, $user_id) {
    $st = $db->prepare("UPDATE users SET google_oauth_token=NULL, google_oauth_refresh=NULL, google_oauth_expiry=NULL WHERE id=?");
    return $st->execute([$user_id]);
}

// ---- API ----

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Restituisci i dati OAuth dell'utente
        $oauth = getOauth($db, $user_id);

        if (!$oauth || !$oauth['google_oauth_token']) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Token Google non trovato']);
            exit;
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'token' => $oauth['google_oauth_token'],
            'refresh' => $oauth['google_oauth_refresh'],
            'expiry' => $oauth['google_oauth_expiry']
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Aggiorna/Salva i dati OAuth dell'utente
        $token = trim($_POST['token'] ?? '');
        $refresh = trim($_POST['refresh'] ?? '');
        $expiry = trim($_POST['expiry'] ?? '');

        if (!$token || !$refresh || !$expiry) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dati OAuth mancanti']);
            exit;
        }

        if (setOauth($db, $user_id, $token, $refresh, $expiry)) {
            ob_end_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Errore salvataggio token']);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Elimina i dati OAuth dell'utente
        if (deleteOauth($db, $user_id)) {
            ob_end_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Errore eliminazione token']);
        }
        exit;
    }

    // Se arriva altro metodo
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Google Calendar API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore server: ' . $e->getMessage()]);
}
