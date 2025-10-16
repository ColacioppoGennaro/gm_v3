<?php
/**
 * DocAnalyzer.ai API Client
 * Gestisce upload documenti, tagging con label e OCR
 */
class DocAnalyzerClient {
    private $apiKey;
    private $apiBase = 'https://api.docanalyzer.ai';
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: env_get('DOCANALYZER_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('DocAnalyzer API Key mancante');
        }
    }
    
    /**
     * HTTP request helper (PUBBLICO per permettere chiamate custom)
     */
    public function request($method, $path, $data = null, $isFile = false) {
        $url = $this->apiBase . $path;
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        if ($data) {
            if ($isFile) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            // MIGLIORE GESTIONE ERRORI
            if (isset($result['error'])) {
                $errMsg = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
            } else {
                $errMsg = "HTTP $httpCode: " . substr($response, 0, 200);
            }
            
            error_log("DocAnalyzer API Error: $errMsg");
            error_log("Full response: $response");
            
            throw new Exception("DocAnalyzer API Error: $errMsg");
        }
        
        return $result;
    }
    
    /**
     * Lista tutte le label dell'utente
     */
    public function listLabels() {
        $response = $this->request('GET', '/api/v1/label');
        return $response['data'] ?? [];
    }
    
    /**
     * Trova una label per nome
     */
    public function findLabelByName($name) {
        $labels = $this->listLabels();
        foreach ($labels as $label) {
            if (($label['name'] ?? '') === $name) {
                return $label;
            }
        }
        return null;
    }
    
    /**
     * Crea una nuova label
     */
    public function createLabel($name, $docids = []) {
        $data = [
            'name' => $name,
            'color' => '#7c3aed',
            'docids' => $docids
        ];
        
        error_log("DocAnalyzer createLabel: name=$name, docids=" . json_encode($docids));
        
        $response = $this->request('POST', '/api/v1/label', $data);
        return $response['data'] ?? null;
    }
    
    /**
     * Aggiorna una label (nome, colore, documenti)
     * @param string $labelName Nome della label (verrà cercato il lid)
     * @param array $updates ['name' => '...', 'color' => '...', 'docids' => ['tag' => [...], 'untag' => [...]]]
     */
    public function updateLabel($labelName, $updates) {
        // Trova label per nome
        $label = $this->findLabelByName($labelName);
        
        if (!$label) {
            throw new Exception("Label '$labelName' non trovata su DocAnalyzer");
        }
        
        $lid = $label['lid'];
        
        error_log("DocAnalyzer updateLabel: lid=$lid, labelName=$labelName, updates=" . json_encode($updates));
        
        $response = $this->request('PUT', "/api/v1/label/{$lid}", $updates);
        return $response['data'] ?? null;
    }
    
    /**
     * Upload documento SINCRONO
     */
    public function uploadDocumentSync($filePath, $fileName) {
        if (!file_exists($filePath)) {
            throw new Exception("File non trovato: $filePath");
        }
        
        $cfile = new CURLFile($filePath, mime_content_type($filePath), $fileName);
        
        $data = [
            'mydoc' => $cfile
        ];
        
        $response = $this->request('POST', '/api/v1/doc/upload/sync', $data, true);
        $docids = $response['data'] ?? [];
        
        if (empty($docids)) {
            throw new Exception('Upload riuscito ma nessun DocID ricevuto');
        }
        
        return $docids[0];
    }
    
    /**
     * Upload documento su una label esistente
     */
    public function uploadToLabelSync($labelId, $filePath, $fileName) {
        if (!file_exists($filePath)) {
            throw new Exception("File non trovato: $filePath");
        }
        
        $cfile = new CURLFile($filePath, mime_content_type($filePath), $fileName);
        
        $data = [
            'mydoc' => $cfile
        ];
        
        $response = $this->request('POST', "/api/v1/label/{$labelId}/upload/sync", $data, true);
        $docids = $response['data'] ?? [];
        
        if (empty($docids)) {
            throw new Exception('Upload su label fallito');
        }
        
        return $docids[0];
    }
    
    /**
     * FLUSSO COMPLETO: Upload e Tag
     */
    public function uploadAndTag($filePath, $fileName, $labelName) {
        // 1. Cerca se la label esiste
        $existingLabel = $this->findLabelByName($labelName);
        
        if ($existingLabel) {
            // Label esiste, upload diretto
            $labelId = $existingLabel['lid'];
            $docid = $this->uploadToLabelSync($labelId, $filePath, $fileName);
            
            return [
                'docid' => $docid,
                'label_id' => $labelId,
                'label_name' => $labelName,
                'strategy' => 'existing_label'
            ];
        } else {
            // Label non esiste - upload separato poi crea label
            $docid = $this->uploadDocumentSync($filePath, $fileName);
            
            $newLabel = $this->createLabel($labelName, [$docid]);
            
            if (!$newLabel || !isset($newLabel['lid'])) {
                throw new Exception('Creazione label fallita');
            }
            
            return [
                'docid' => $docid,
                'label_id' => $newLabel['lid'],
                'label_name' => $labelName,
                'strategy' => 'new_label'
            ];
        }
    }
    
    /**
     * Interroga i documenti di una label
     */
    public function queryLabel($question, $labelName) {
        $label = $this->findLabelByName($labelName);
        
        if (!$label) {
            throw new Exception("Label '$labelName' non trovata");
        }
        
        $lid = $label['lid'];
        
        $data = [
            'prompt' => $question,
            'adherence' => 'balanced'
        ];
        
        error_log("DocAnalyzer Query: lid=$lid, label=$labelName, question=" . substr($question, 0, 50));
        
        $response = $this->request('POST', "/api/v1/label/{$lid}/chat", $data);
        
        return $response['data'] ?? null;
    }
    
    /**
     * Ottiene dettagli di un documento
     */
    public function getDocumentDetails($docid) {
        error_log("DocAnalyzer getDocumentDetails: docid=$docid");
        
        $response = $this->request('GET', "/api/v1/doc/{$docid}");
        
        return $response['data'] ?? null;
    }
    
    /**
     * Esegue OCR su un documento (1 credito per pagina)
     */
    public function ocrDocument($docid) {
        error_log("DocAnalyzer OCR: docid=$docid");
        
        $response = $this->request('POST', "/api/v1/doc/{$docid}/ocr");
        
        return $response['data'] ?? null;
    }
    
    /**
     * Elimina un documento
     */
    public function deleteDocument($docid) {
        return $this->request('DELETE', "/api/v1/doc/{$docid}");
    }
}
