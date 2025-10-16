<?php
/**
 * test_assistant.php
 * 
 * Script di test per verificare funzionamento Assistente AI
 * 
 * USAGE:
 * 1. Carica questo file nella root di gm_v3/
 * 2. Accedi da browser: https://tuodominio.com/gm_v3/test_assistant.php
 * 3. Login con credenziali valide
 * 4. Esegui test conversazione
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/_core/bootstrap.php';
require_once __DIR__ . '/_core/helpers.php';
require_once __DIR__ . '/_core/AssistantAgent.php';

// Verifica login
if (!isset($_SESSION['user_id'])) {
    die('⚠️ <b>Login richiesto</b><br><br><a href="index.php">Vai al login</a>');
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'] ?? 'N/A';
$userRole = $_SESSION['role'] ?? 'free';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Assistente AI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #7c3aed;
            margin-bottom: 20px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #7c3aed;
        }
        .success { color: #22c55e; font-weight: 600; }
        .error { color: #ef4444; font-weight: 600; }
        .info { color: #6b7280; font-size: 14px; }
        .test-result {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }
        .badge-success { background: #22c55e; color: white; }
        .badge-error { background: #ef4444; color: white; }
        .badge-info { background: #3b82f6; color: white; }
        pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
        }
        button {
            padding: 10px 20px;
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin: 5px;
        }
        button:hover { background: #6d28d9; }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            margin: 10px 0;
        }
        .chat-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            margin: 15px 0;
        }
        .chat-message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 8px;
        }
        .chat-message.user {
            background: #7c3aed;
            color: white;
            text-align: right;
        }
        .chat-message.bot {
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Test Assistente AI</h1>
        
        <div class="section">
            <h3>👤 Utente Corrente</h3>
            <p><strong>ID:</strong> <?= $userId ?></p>
            <p><strong>Email:</strong> <?= $userEmail ?></p>
            <p><strong>Piano:</strong> <span class="badge badge-info"><?= strtoupper($userRole) ?></span></p>
        </div>

        <?php
        // ========================================
        // TEST 1: Verifica dipendenze
        // ========================================
        echo '<div class="section">';
        echo '<h3>🔍 Test 1: Verifica Dipendenze</h3>';
        
        $dependencies = [
            'GeminiClient' => __DIR__ . '/_core/GeminiClient.php',
            'DocAnalyzerClient' => __DIR__ . '/_core/DocAnalyzerClient.php',
            'helpers.php' => __DIR__ . '/_core/helpers.php',
            'AssistantAgent' => __DIR__ . '/_core/AssistantAgent.php',
            'api/assistant.php' => __DIR__ . '/api/assistant.php',
            'assets/js/assistant.js' => __DIR__ . '/assets/js/assistant.js',
            'assets/css/assistant.css' => __DIR__ . '/assets/css/assistant.css'
        ];
        
        $allGood = true;
        foreach ($dependencies as $name => $path) {
            $exists = file_exists($path);
            $status = $exists ? '✅' : '❌';
            $class = $exists ? 'success' : 'error';
            echo "<div class='test-result'><span class='$class'>$status $name</span> <span class='info'>$path</span></div>";
            if (!$exists) $allGood = false;
        }
        
        echo $allGood 
            ? "<p class='success'>✅ Tutte le dipendenze presenti!</p>" 
            : "<p class='error'>❌ Alcuni file mancano. Verifica installazione.</p>";
        echo '</div>';

        // ========================================
        // TEST 2: Inizializzazione Agent
        // ========================================
        echo '<div class="section">';
        echo '<h3>⚙️ Test 2: Inizializzazione AssistantAgent</h3>';
        
        try {
            $agent = new AssistantAgent($userId);
            echo "<p class='success'>✅ AssistantAgent inizializzato correttamente</p>";
            
            // Test intent detection
            $testMessage = "Mi è arrivata una bolletta";
            $response = $agent->processMessage($testMessage, null);
            
            echo "<div class='test-result'>";
            echo "<strong>Test Messaggio:</strong> \"$testMessage\"<br><br>";
            echo "<strong>Response:</strong><br>";
            echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            
            if ($response['status'] === 'incomplete' || $response['status'] === 'complete') {
                echo "<p class='success'>✅ Assistente ha risposto correttamente</p>";
            } else {
                echo "<p class='error'>❌ Risposta inattesa</p>";
            }
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Errore inizializzazione: " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        echo '</div>';

        // ========================================
        // TEST 3: Chat Interattiva
        // ========================================
        ?>
        
        <div class="section">
            <h3>💬 Test 3: Chat Interattiva</h3>
            <p class="info">Testa una conversazione completa con l'assistente</p>
            
            <div id="chatBox" class="chat-box">
                <div class="chat-message bot">
                    <strong>🤖 Assistente:</strong><br>
                    Ciao! Scrivi un messaggio per iniziare una conversazione.
                </div>
            </div>
            
            <input 
                type="text" 
                id="chatInput" 
                placeholder="Es: Mi è arrivata una bolletta"
                onkeypress="if(event.key==='Enter') sendTestMessage()"
            />
            <button onclick="sendTestMessage()">Invia Messaggio</button>
            <button onclick="resetTestChat()">🔄 Reset Chat</button>
            
            <div id="chatDebug" style="margin-top:15px;display:none">
                <strong>Debug Response:</strong>
                <pre id="chatDebugContent"></pre>
            </div>
        </div>

        <?php
        // ========================================
        // TEST 4: Verifica API Endpoint
        // ========================================
        echo '<div class="section">';
        echo '<h3>🔌 Test 4: Verifica API Endpoint</h3>';
        
        $apiUrl = 'api/assistant.php';
        $apiPath = __DIR__ . '/' . $apiUrl;
        
        if (file_exists($apiPath)) {
            echo "<p class='success'>✅ API endpoint trovato: $apiUrl</p>";
            echo "<p class='info'>Test chiamata API via AJAX nel browser (vedi Test 3)</p>";
        } else {
            echo "<p class='error'>❌ API endpoint non trovato: $apiPath</p>";
        }
        echo '</div>';

        // ========================================
        // TEST 5: Verifica Gemini Config
        // ========================================
        echo '<div class="section">';
        echo '<h3>🔑 Test 5: Verifica Configurazione Gemini</h3>';
        
        $geminiKey = env_get('GEMINI_API_KEY');
        if ($geminiKey && strlen($geminiKey) > 10) {
            echo "<p class='success'>✅ Gemini API Key configurata (lunghezza: " . strlen($geminiKey) . " caratteri)</p>";
        } else {
            echo "<p class='error'>❌ Gemini API Key mancante o non valida</p>";
            echo "<p class='info'>Verifica il file .env e assicurati che GEMINI_API_KEY sia impostata</p>";
        }
        echo '</div>';
        ?>

        <div class="section">
            <h3>📖 Prossimi Step</h3>
            <ol style="line-height:2">
                <li>Verifica che tutti i test siano ✅</li>
                <li>Apri la webapp: <a href="index.php">index.php</a></li>
                <li>Vai alla Dashboard</li>
                <li>Clicca su "🤖 Assistente AI"</li>
                <li>Testa conversazione completa</li>
            </ol>
        </div>

        <div class="section" style="border-left-color: #ef4444">
            <h3>⚠️ Troubleshooting</h3>
            <p><strong>Se qualcosa non funziona:</strong></p>
            <ul style="line-height:2; margin-left:20px">
                <li>Controlla error_log PHP</li>
                <li>Verifica console browser (F12 > Console)</li>
                <li>Controlla Network tab (F12 > Network)</li>
                <li>Leggi README_INSTALLAZIONE.md</li>
            </ul>
        </div>
    </div>

    <script>
        let sessionState = null;

        async function sendTestMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (!message) {
                alert('Inserisci un messaggio');
                return;
            }

            // Aggiungi messaggio utente
            addChatMessage(message, 'user');
            input.value = '';

            try {
                const response = await fetch('api/assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: message })
                });

                const data = await response.json();

                // Mostra debug
                document.getElementById('chatDebug').style.display = 'block';
                document.getElementById('chatDebugContent').textContent = 
                    JSON.stringify(data, null, 2);

                if (data.success) {
                    addChatMessage(data.message, 'bot');
                    sessionState = data.state || null;
                } else {
                    addChatMessage('❌ ' + (data.message || 'Errore'), 'error');
                }

            } catch (error) {
                console.error('Chat error:', error);
                addChatMessage('❌ Errore connessione', 'error');
            }
        }

        function addChatMessage(text, sender) {
            const chatBox = document.getElementById('chatBox');
            const msgDiv = document.createElement('div');
            msgDiv.className = 'chat-message ' + sender;
            
            const label = sender === 'user' ? '👤 Tu' : '🤖 Assistente';
            msgDiv.innerHTML = `<strong>${label}:</strong><br>${text}`;
            
            chatBox.appendChild(msgDiv);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        async function resetTestChat() {
            try {
                await fetch('api/assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reset: true })
                });

                document.getElementById('chatBox').innerHTML = `
                    <div class="chat-message bot">
                        <strong>🤖 Assistente:</strong><br>
                        Conversazione resettata. Scrivi un messaggio per iniziare.
                    </div>
                `;
                
                sessionState = null;
                document.getElementById('chatDebug').style.display = 'none';
                
            } catch (error) {
                console.error('Reset error:', error);
            }
        }
    </script>
</body>
</html>
