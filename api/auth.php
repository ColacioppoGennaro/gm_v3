<?php
/**
 * api/auth.php
 * ✅ VERSIONE MYSQLI
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

ob_start();

require_once __DIR__.'/../_core/helpers.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['a'] ?? '';

try {
    $db = db(); // ✅ mysqli object
    
    if ($action === 'status') {
        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id) {
            $st = $db->prepare("SELECT email, role FROM users WHERE id=?");
            $st->bind_param("i", $user_id);
            $st->execute();
            $r = $st->get_result();
            
            if ($row = $r->fetch_assoc()) {
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'account' => [
                        'id' => $user_id,
                        'email' => $row['email'],
                        'role' => $row['role']
                    ]
                ]);
                exit;
            }
        }
        
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non autenticato']);
        exit;
    }
    
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$password) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email e password richieste']);
            exit;
        }
        
        ratelimit("login_$email", 5, 300);
        
        $st = $db->prepare("SELECT id, email, pass_hash, role FROM users WHERE email=?");
        $st->bind_param("s", $email);
        $st->execute();
        $r = $st->get_result();
        
        if (!($row = $r->fetch_assoc())) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenziali non valide']);
            exit;
        }
        
        if (!verify_password($password, $row['pass_hash'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenziali non valide']);
            exit;
        }
        
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['role'] = $row['role'];
        
        error_log("Login successful: user_id={$row['id']}, email={$email}");
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'role' => $row['role'],
            'email' => $row['email']
        ]);
        exit;
    }
    
    if ($action === 'register') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$password) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email e password richieste']);
            exit;
        }
        
        if (strlen($password) < 6) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password troppo corta (min 6 caratteri)']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email non valida']);
            exit;
        }
        
        $st = $db->prepare("SELECT id FROM users WHERE email=?");
        $st->bind_param("s", $email);
        $st->execute();
        if ($st->get_result()->num_rows > 0) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email già registrata']);
            exit;
        }
        
        $hash = hash_password($password);
        $st = $db->prepare("INSERT INTO users(email, pass_hash, role) VALUES(?, ?, 'free')");
        $st->bind_param("ss", $email, $hash);
        $st->execute();
        $userId = $db->insert_id;
        
        $masterLabelId = 'user_' . $userId . '_master';
        $st = $db->prepare("INSERT INTO labels(user_id, name, docanalyzer_label_id) VALUES(?, 'master', ?)");
        $st->bind_param("is", $userId, $masterLabelId);
        $st->execute();
        
        error_log("Registration successful: user_id=$userId, email=$email");
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Registrazione completata']);
        exit;
    }
    
    if ($action === 'logout') {
        $user_id = $_SESSION['user_id'] ?? null;
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        error_log("Logout successful: user_id=$user_id");
        
        ob_end_clean();
        echo json_encode(['success' => true]);
        exit;
    }
    
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Auth API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore server: ' . $e->getMessage()]);
}
