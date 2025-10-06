<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__.'/../_core/bootstrap.php';
    require_once __DIR__.'/../_core/helpers.php';
    require_once __DIR__.'/../_core/DocAnalyzerClient.php';
    require_once __DIR__.'/../_core/GeminiClient.php';
    require_login();

    $user = user(); 
    $q = trim($_POST['q'] ?? ''); 
    $category = trim($_POST['category'] ?? '');
    $mode = trim($_POST['mode'] ?? 'docs'); // 'docs' o 'ai'
    $adherence = trim($_POST['adherence'] ?? 'balanced'); // strict, high, balanced, low, free
    $showRefs = isset($_POST['show_refs']) ? (bool)$_POST['show_refs'] : true;
    
    if ($q === '') {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Domanda vuota'], 400);
    }
    
    // Verifica quota
    $max = is_pro() ? 200 : 20; 
    $day = (new DateTime())->format('Y-m-d');
    $st = db()->prepare("INSERT INTO quotas(user_id,day,uploads_count,chat_count) VALUES(?, ?, 0, 0) ON DUPLICATE KEY UPDATE day=day"); 
    $st->bind_param("is", $user['id'], $day); 
    $st->execute();
    
    db()->query("UPDATE quotas SET chat_count=chat_count+1 WHERE user_id={$user['id']} AND day='$day'");
    $r = db()->query("SELECT chat_count FROM quotas WHERE user_id={$user['id']} AND day='$day'")->fetch_assoc();
    
    if (intval($r['chat_count']) > $max) {
        ob_end_clean();
        json_out(['success' => false, 'message' => 'Limite chat giornaliero raggiunto'], 403);
    }

    $answer = null;
    $source = 'llm';
    
    if ($mode === 'docs') {
        // === INTERROGA DOCANALYZER ===
        
        // Determina quali label interrogare
        $labelName = null;
        
        if (is_pro()) {
            if (!$category) {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Seleziona una categoria'], 400);
            }
            
            // Verifica che la categoria esista
            $st = db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name=?"); 
            $st->bind_param("is", $user['id'], $category); 
            $st->execute(); 
            $r = $st->get_result(); 
            if ($row = $r->fetch_assoc()) {
                $labelName = $row['docanalyzer_label_id'];
            } else {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Categoria non valida'], 400);
            }
        } else {
            // Free: usa label master
            $st = db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'"); 
            $st->bind_param("i", $user['id']); 
            $st->execute(); 
            $r = $st->get_result(); 
            if ($row = $r->fetch_assoc()) {
                $labelName = $row['docanalyzer_label_id'];
            } else {
                ob_end_clean();
                json_out(['success' => false, 'message' => 'Label master non trovata'], 500);
            }
        }
        
        try {
            $docAnalyzer = new DocAnalyzerClient();
            
            // Trova label
            $label = $docAnalyzer->findLabelByName($labelName);
            
            if (!$label) {
                throw new Exception("Label '$labelName' non trovata su DocAnalyzer");
            }
            
            $lid = $label['lid'];
            
            // Prepara richiesta con parametri
            $data = [
                'prompt' => $q,
                'adherence' => $adherence,
                'page' => $showRefs
            ];
            
            error_log("DocAnalyzer Query: lid=$lid, label=$labelName, adherence=$adherence, refs=$showRefs, question=" . substr($q, 0, 50));
            
            $response = $docAnalyzer->request('POST', "/api/v1/label/{$lid}/chat", $data);
            $docResult = $response['data'] ?? null;
            
            if ($docResult && isset($docResult['answer']) && !empty(trim($docResult['answer']))) {
                $answer = $docResult['answer'];
                $source = 'docs';
                
                error_log("DocAnalyzer SUCCESS: " . substr($answer, 0, 100));
            } else {
                error_log("DocAnalyzer: Nessuna risposta trovata nei documenti");
                $answer = null;
            }
        } catch (Exception $e) {
            error_log("DocAnalyzer Query Error: " . $e->getMessage());
            $answer = null;
        }
        
        // Se DocAnalyzer non trova risposta, ritorna comunque
        if (!$answer) {
            ob_end_clean();
            json_out([
                'success' => true,
                'source' => 'none',
                'answer' => 'Non ho trovato informazioni nei tuoi documenti. Vuoi che chieda a un\'AI generica?',
                'can_ask_ai' => true
            ]);
        }
        
    } else {
        // === INTERROGA GEMINI ===
        
        try {
            $gemini = new GeminiClient();
            
            // Ottieni contesto dai documenti (opzionale)
            $context = null;
            if (is_pro() && $category) {
                // Pro: usa categoria specifica come contesto
                $context = "L'utente ha documenti nella categoria '$category'.";
            }
            
            error_log("Gemini Query: question=" . substr($q, 0, 50));
            
            $answer = $gemini->ask($q, $context);
            $source = 'ai';
            
            error_log("Gemini SUCCESS: " . substr($answer, 0, 100));
            
        } catch (Exception $e) {
            error_log("Gemini Error: " . $e->getMessage());
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Errore AI: ' . $e->getMessage()], 500);
        }
    }

    // Salva nel log
    $st = db()->prepare("INSERT INTO chat_logs(user_id,source,question,answer) VALUES(?,?,?,?)"); 
    $st->bind_param("isss", $user['id'], $source, $q, $answer); 
    $st->execute();
    
    ob_end_clean();
    json_out(['success' => true, 'source' => $source, 'answer' => $answer]);
    
} catch (Throwable $e) {
    ob_end_clean();
    error_log("API Error in chat.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
    exit;
}
