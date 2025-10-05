<?php
/**
 * DocAnalyzer.ai API Client
 * Gestisce upload documenti e tagging con label
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
     * HTTP request helper
     */
    private function request($method, $path, $data = null, $isFile = false) {
        $url = $this->apiBase . $path;
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout lungo per upload
        
        if ($data) {
            if ($isFile) {
                // Upload file multipart
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                // JSON data
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
            $errMsg = $result['error'] ?? "HTTP $httpCode: $response";
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
        
        $response = $this->request('POST', '/api/v1/label', $data);
        return $response['data'] ?? null;
    }
    
    /**
     * Upload documento SINCRONO (strategia che funziona)
     */
    public function uploadDocumentSync($filePath, $fileName) {
        if (!file_exists($filePath)) {
            throw new Exception("File non trovato: $filePath");
        }
        
        // Crea CURLFile per upload multipart
        $cfile = new CURLFile($filePath, mime_content_type($filePath), $fileName);
        
        $data = [
            'mydoc' => $cfile
        ];
        
        $response = $this->request('POST', '/api/v1/doc/upload/sync', $data, true);
        $docids = $response['data'] ?? [];
        
        if (empty($docids)) {
            throw new Exception('Upload riuscito ma nessun DocID ricevuto');
        }
        
        return $docids[0]; // Ritorna il primo docid
    }
    
    /**
     * Upload documento direttamente su una label esistente (strategia B)
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
     * FLUSSO COMPLETO: Upload e Tag (come nel tuo esempio funzionante)
     * Questo è il metodo principale da usare
     */
    public function uploadAndTag($filePath, $fileName, $labelName) {
        // 1. Cerca se la label esiste
        $existingLabel = $this->findLabelByName($labelName);
        
        if ($existingLabel) {
            // STRATEGIA B: Label esiste, upload diretto
            $labelId = $existingLabel['lid'];
            $docid = $this->uploadToLabelSync($labelId, $filePath, $fileName);
            
            return [
                'docid' => $docid,
                'label_id' => $labelId,
                'label_name' => $labelName,
                'strategy' => 'existing_label'
            ];
        } else {
            // STRATEGIA A: Label non esiste
            // 1. Upload documento separato
            $docid = $this->uploadDocumentSync($filePath, $fileName);
            
            // 2. Crea label e associa docid
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
     * Interroga i documenti di una label usando l'endpoint /label/{lid}/chat
     * @param string $question La domanda
     * @param string $labelName Nome della label da interrogare
     */
    public function queryLabel($question, $labelName) {
        // Trova la label per nome
        $label = $this->findLabelByName($labelName);
        
        if (!$label) {
            throw new Exception("Label '$labelName' non trovata");
        }
        
        $lid = $label['lid'];
        
        $data = [
            'prompt' => $question,
            'adherence' => 'balanced'
        ];
        
        error_log("DocAnalyzer Query Label: lid=$lid, label=$labelName, question=" . substr($question, 0, 50));
        
        $response = $this->request('POST', "/api/v1/label/{$lid}/chat", $data);
        
        return $response['data'] ?? null;
    }
    
    /**
     * Elimina un documento
     */
    public function deleteDocument($docid) {
        return $this->request('DELETE', "/api/v1/doc/{$docid}");
    }
}
