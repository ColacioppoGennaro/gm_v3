<?php
/**
 * api/auth.php
 * Gestisce autenticazione: status, login, register, logout.
 */
require_once __DIR__.'/../_core/helpers.php';
session_start();

$action = $_GET['a'] ?? '';

// Nuovo endpoint per verificare lo stato della sessione in modo sicuro
if ($action === 'status') {
    header('Content-Type: application/json');
    
    $user_session = $_SESSION['user'] ?? null;

    if ($user_session) {
        // Utente autenticato, restituisci i suoi dati in un formato compatibile con il frontend
        echo json_encode([
            'success' => true, 
            'account' => [
                'id'    => $user_session['id'] ?? null, 
                'email' => $user_session['email'] ?? null,
                'role'  => $user_session['role'] ?? 'free'
            ]
        ]);
    } else {
        // Utente non autenticato
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    }
    // Termina lo script qui per l'azione 'status'
    exit;
}

/**
 * Il resto del file per gestire login, registrazione e logout
 */
switch ($action) {
    case 'login':
        // TODO: Inserire qui la logica per il login
        break;

    case 'register':
        // TODO: Inserire qui la logica per la registrazione
        break;

    case 'logout':
        // TODO: Inserire qui la logica per il logout
        break;

    default:
        // Se l'azione non è riconosciuta
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
        break;
}
