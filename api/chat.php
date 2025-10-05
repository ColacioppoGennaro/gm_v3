<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    session_start();
    require_once __DIR__.'/../_core/bootstrap.php';
    require_once __DIR__.'/../_core/helpers.php';
    require_once __DIR__.'/../_core/DocAnalyzerClient.php';
    require_login();

    $user = user(); 
    $q = trim($_POST['q'] ?? ''); 
    $category = trim($_POST['category'] ?? '');
    
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

    // Determina quali label interrogare
    $labelIds = [];
    
    if (is_pro()) {
        // Pro: label master (user) + categoria specifica
        if (!$category) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Seleziona una categoria'], 400);
        }
        
        // 1. Label master dell'utente (sempre inclusa)
        $st = db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'"); 
        $st->bind_param("i", $user['id']); 
        $st->execute(); 
        $rr = $st->get_result(); 
        if ($row = $rr->fetch_assoc()) {
            $labelIds[] = $row['docanalyzer_label_id'];
        }
        
        // 2. Label categoria scelta
        $st = db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name=?"); 
        $st->bind_param("is", $user['id'], $category); 
        $st->execute(); 
        $rr = $st->get_result(); 
        if ($row = $rr->fetch_assoc()) {
            $labelIds[] = $row['docanalyzer_label_id'];
        }
        
        if (empty($labelIds)) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Categoria non valida'], 400);
        }
    } else {
        // Free: usa SOLO la label master (user)
        $st = db()->prepare("SELECT docanalyzer_label_id FROM labels WHERE user_id=? AND name='master'"); 
        $st->bind_param("i", $user['id']); 
        $st->execute(); 
        $rr = $st->get_result(); 
        if ($row = $rr->fetch_assoc()) {
            $labelIds = [$row['docanalyzer_label_id']];
        }
        
        if (empty($labelIds)) {
            ob_end_clean();
            json_out(['success' => false, 'message' => 'Label master non trovata'], 500);
        }
    }

    // === QUERY DOCANALYZER ===
    $answer = null;
    $source = 'llm'; // Default fallback
    
    try {
        $docAnalyzer = new DocAnalyzerClient();
        $docResult = $docAnalyzer->query($q, $labelIds);
        
        // Verifica se DocAnalyzer ha trovato una risposta utile
        if ($docResult && isset($docResult['answer']) && !empty(trim($docResult['answer']))) {
            $answer = $docResult['answer'];
            $source = 'docs';
            
            error_log("DocAnalyzer Query Success: " . substr($answer, 0, 100));
        } else {
            error_log("DocAnalyzer: Nessuna risposta nei documenti");
        }
    } catch (Exception $e) {
        error_log("DocAnalyzer Query Error: " . $e->getMessage());
    }
    
    // === FALLBACK LLM (ChatGPT/Gemini) ===
    if (!$answer) {
        // TODO: Implementare chiamata a OpenAI/Gemini
        // Per ora placeholder
        $answer = "Non ho trovato informazioni specifiche nei tuoi documenti. " .
                  "Questa è una risposta generica dell'AI: per rispondere meglio alla tua domanda " .
                  "avrei bisogno di più documenti o informazioni di contesto.";
        $source = 'llm';
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
