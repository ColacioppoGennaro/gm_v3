<?php
/**
 * google_connect.php
 * Pagina per collegare Google Calendar
 */
session_start();
require_once __DIR__.'/_core/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user = user();
$db = db();

// Verifica se già collegato
$stmt = $db->prepare("SELECT google_oauth_token FROM users WHERE id=?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$userRow = $result->fetch_assoc();

$isConnected = !empty($userRow['google_oauth_token']);

// Gestione disconnessione
if (isset($_POST['disconnect'])) {
    $stmt = $db->prepare("UPDATE users SET google_oauth_token=NULL, google_oauth_refresh=NULL, google_oauth_expiry=NULL WHERE id=?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    header('Location: google_connect.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collega Google Calendar - gm_v3</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>📅 Google Calendar</h1>
            
            <?php if ($isConnected): ?>
                <div style="background: #10b981; color: white; padding: 16px; border-radius: 10px; margin-bottom: 20px;">
                    ✅ Account Google collegato!
                </div>
                
                <p style="color: var(--muted); margin-bottom: 24px;">
                    Il tuo account Google Calendar è collegato correttamente.
                </p>
                
                <form method="POST">
                    <button type="submit" name="disconnect" class="btn warn" style="width: 100%;">
                        🔌 Scollega Google Calendar
                    </button>
                </form>
                
                <a href="index.php#/calendar" class="btn" style="width: 100%; margin-top: 12px; display: block; text-align: center; text-decoration: none;">
                    📅 Vai al Calendario
                </a>
            <?php else: ?>
                <p style="color: var(--muted); margin-bottom: 24px;">
                    Per usare Google Calendar devi collegare il tuo account Google.
                </p>
                
                <div style="background: #1f2937; padding: 16px; border-radius: 10px; margin-bottom: 24px; font-size: 13px; color: var(--muted);">
                    <strong>⚠️ Nota:</strong> Per collegare Google Calendar serve configurare le credenziali OAuth in <code>.env</code>:
                    <ul style="margin-top: 8px; padding-left: 20px;">
                        <li>GOOGLE_CLIENT_ID</li>
                        <li>GOOGLE_CLIENT_SECRET</li>
                        <li>GOOGLE_REDIRECT_URI</li>
                    </ul>
                </div>
                
                <?php 
                $clientId = env_get('GOOGLE_CLIENT_ID') ?: getenv('GOOGLE_CLIENT_ID') ?: $_ENV['GOOGLE_CLIENT_ID'] ?? '';
                if ($clientId): 
                ?>
                    <a href="oauth/google/login.php" class="btn" style="width: 100%; display: block; text-align: center; text-decoration: none;">
                        🔗 Collega Google Calendar
                    </a>
                <?php else: ?>
                    <div style="color: var(--danger); padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 10px;">
                        ❌ Credenziali OAuth Google non configurate. Contatta l'amministratore.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="index.php" style="display: block; text-align: center; margin-top: 24px; color: var(--accent); text-decoration: none;">
                ← Torna alla Dashboard
            </a>
        </div>
    </div>
</body>
</html>
