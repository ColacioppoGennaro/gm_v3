<?php
/**
 * Google Gemini API Client
 * Fallback AI quando DocAnalyzer non trova risposta
 * + SUPPORTO IMMAGINI MULTIMODALI per analisi documenti
 */
class GeminiClient {
    private $apiKey;
    // v1 come default (stabile); fallback a v1beta se necessario
    private $apiBase = 'https://generativelanguage.googleapis.com/v1';

    // CAMBIA QUI con un modello reale disponibile per la tua key/area:
    // Esempi: 'gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.0-pro'
    private $model = 'gemini-2.5-flash-lite';

    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: env_get('GEMINI_API_KEY');
        if (!$this->apiKey) throw new Exception('Gemini API Key mancante');
    }

    public function ask($question, $context = null) {
        $prompt = $context ? "Contesto: $context\n\nDomanda: $question" : $question;

        $payload = [
            'contents' => [[ 'parts' => [['text' => $prompt]] ]],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ]
        ];

        try {
            return $this->callGenerateContent($this->apiBase, $this->model, $payload);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'not found') !== false
             || stripos($msg, 'is not supported') !== false
             || stripos($msg, '404') !== false) {
                $fallbackBase = 'https://generativelanguage.googleapis.com/v1beta';
                $this->apiBase = $fallbackBase;
                return $this->callGenerateContent($fallbackBase, $this->model, $payload);
            }
            throw $e;
        }
    }

    /**
     * ✨ NUOVO: Analizza immagine con Gemini 2.0 Flash (multimodale)
     * 
     * @param string $prompt Prompt testuale per guidare l'analisi
     * @param string $base64Image Immagine codificata in base64
     * @param string $mimeType MIME type dell'immagine (es: image/jpeg, image/png)
     * @return string Risposta testuale dell'AI
     */
    public function analyzeImage($prompt, $base64Image, $mimeType = 'image/jpeg') {
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64Image
                        ]
                    ]
                ]
            ]],
            'generationConfig' => [
                'temperature' => 0.4, // Più bassa per dati strutturati (OCR/estrazione)
                'maxOutputTokens' => 2048,
            ]
        ];

        error_log("Gemini Image Analysis: mime={$mimeType}, prompt_length=" . strlen($prompt));

        try {
            return $this->callGenerateContent($this->apiBase, $this->model, $payload);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // Fallback a v1beta se necessario
            if (stripos($msg, 'not found') !== false
             || stripos($msg, 'is not supported') !== false
             || stripos($msg, '404') !== false) {
                $fallbackBase = 'https://generativelanguage.googleapis.com/v1beta';
                $this->apiBase = $fallbackBase;
                error_log("Gemini: Falling back to v1beta for image analysis");
                return $this->callGenerateContent($fallbackBase, $this->model, $payload);
            }
            throw $e;
        }
    }

    private function callGenerateContent($apiBase, $model, array $data) {
        $url = "{$apiBase}/models/{$model}:generateContent?key={$this->apiKey}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60, // Aumentato per immagini
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new Exception("cURL Error: $curlErr");

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errMsg = $result['error']['message'] ?? "HTTP $httpCode";
            error_log("Gemini API Error: $errMsg");
            // Hint utile quando il modello non esiste più in quella versione/area
            if (stripos($errMsg, 'not found') !== false || stripos($errMsg, 'is not supported') !== false) {
                $errMsg .= " — Chiama /models per vedere i modelli disponibili.";
            }
            throw new Exception("Gemini API Error: $errMsg");
        }

        $answer = $this->extractText($result);
        if (!$answer) throw new Exception('Gemini non ha ritornato una risposta');
        return $answer;
    }

    private function extractText(array $result) {
        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (!empty($part['text'])) return $part['text'];
            }
        }
        if (!empty($result['candidates'][0]['output'])) {
            return $result['candidates'][0]['output'];
        }
        return null;
    }

    // (Opzionale) per debug rapido da browser/CLI: stampa modelli attivi
    public function listModels($beta = false) {
        $base = $beta ? 'https://generativelanguage.googleapis.com/v1beta'
                      : 'https://generativelanguage.googleapis.com/v1';
        $url = $base . "/models?key={$this->apiKey}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        if ($err = curl_error($ch)) throw new Exception("cURL Error: $err");
        curl_close($ch);
        return $response; // JSON string
    }
    
    /**
     * Analizza documento per estrazione dati calendario
     * Estrae date, scadenze, importi, periodicità per creare eventi automatici
     */
    public function analyzeDocumentForCalendar($filePath, $fileName) {
        $mimeType = mime_content_type($filePath);
        
        // Prompt specifico per estrazione dati calendario
        $prompt = "Analizza questo documento e estrai SOLO i dati utili per creare eventi nel calendario.
        
FORMATO OUTPUT richiesto (JSON):
{
  \"title\": \"Titolo evento da creare\",
  \"description\": \"Descrizione dettagliata\", 
  \"due_date\": \"YYYY-MM-DD o YYYY-MM-DDTHH:MM se c'è orario\",
  \"reminder_date\": \"YYYY-MM-DD per promemoria anticipato\",
  \"category\": \"bolletta|multa|tagliando|assicurazione|medico|altro\",
  \"amount\": \"importo se presente\",
  \"recurring\": \"none|monthly|yearly|custom\",
  \"recurring_details\": \"descrizione periodicità se custom\",
  \"extracted_data\": {
    \"scadenze\": [\"lista date scadenza\"],
    \"importi\": [\"lista importi trovati\"],
    \"riferimenti\": \"codici/numeri importanti\"
  }
}

ESEMPI di cosa estrarre:
- Bollette: scadenza pagamento, importo, tipo utenza
- Documenti auto: scadenza revisione/assicurazione, targa
- Ricette mediche: date visite/esami, farmaci con posologia
- Manuali: date manutenzioni programmate, intervalli km/ore
- Contratti: scadenze rinnovo, disdetta

Rispondi SOLO con il JSON, niente altro testo.";

        if (str_starts_with($mimeType, 'image/')) {
            // Documento immagine - usa analisi multimodale
            return $this->analyzeImage($prompt, $filePath);
        } else {
            // Documento testo - estrai testo e analizza
            $text = $this->extractTextFromFile($filePath, $mimeType);
            if (!$text) {
                throw new Exception('Impossibile estrarre testo dal documento');
            }
            
            $fullPrompt = $prompt . "\n\nCONTENUTO DOCUMENTO:\n" . substr($text, 0, 8000); // Limita lunghezza
            $response = $this->ask($fullPrompt);
            
            // Parsing JSON dalla risposta
            return $this->parseAIResponse($response);
        }
    }
    
    /**
     * Estrae testo da file PDF/DOC/DOCX
     */
    private function extractTextFromFile($filePath, $mimeType) {
        switch ($mimeType) {
            case 'application/pdf':
                // TODO: implementare estrazione PDF se necessario
                return "PDF content extraction not implemented yet";
                
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/msword':
                // TODO: implementare estrazione Word se necessario
                return "Word content extraction not implemented yet";
                
            case 'text/plain':
                return file_get_contents($filePath);
                
            default:
                return null;
        }
    }
    
    /**
     * Parsing risposta AI in formato JSON
     */
    private function parseAIResponse($response) {
        try {
            // La risposta potrebbe contenere altro testo, cerchiamo il JSON
            $text = is_array($response) ? ($response['text'] ?? '') : $response;
            
            // Cerca pattern JSON nella risposta
            if (preg_match('/\{.*\}/s', $text, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                }
            }
            
            // Fallback: estrai dati con regex se JSON non funziona
            return [
                'title' => 'Documento analizzato',
                'description' => substr($text, 0, 200),
                'category' => 'altro',
                'error' => 'Formato risposta AI non valido'
            ];
            
        } catch (Exception $e) {
            return [
                'title' => 'Errore analisi',
                'description' => 'Documento caricato ma analisi fallita',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NUOVO: Estrae testo da immagine usando Vision API
     */
    public function extractTextFromImage($imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception("Immagine non trovata: $imagePath");
        }
        
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);
        
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => 'Estrai tutto il testo presente in questa immagine. Restituisci solo il testo estratto, senza commenti aggiuntivi.'],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $imageData
                        ]
                    ]
                ]
            ]],
            'generationConfig' => [
                'temperature' => 0.1, // Bassa temperatura per OCR preciso
                'maxOutputTokens' => 2048,
            ]
        ];
        
        $response = $this->callGenerateContent($this->apiBase, $this->model, $payload);
        return is_array($response) ? ($response['text'] ?? '') : $response;
    }
    
    /**
     * ✅ NUOVO: Genera embedding del testo usando text-embedding-004
     */
    public function embedText($text) {
        if (empty($text)) {
            throw new Exception("Testo vuoto per embedding");
        }
        
        // Usa il modello di embedding specializzato
        $embeddingModel = 'text-embedding-004';
        $url = "{$this->apiBase}/models/{$embeddingModel}:embedContent?key={$this->apiKey}";
        
        $payload = [
            'model' => "models/{$embeddingModel}",
            'content' => [
                'parts' => [
                    ['text' => substr($text, 0, 10000)] // Max 10k caratteri
                ]
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Gemini Embedding Error: HTTP $httpCode - $response");
            throw new Exception("Gemini Embedding Error: HTTP $httpCode");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['embedding']['values'])) {
            throw new Exception("Embedding response malformato");
        }
        
        return [
            'values' => $result['embedding']['values'],
            'dimension' => count($result['embedding']['values'])
        ];
    }
    
    /**
     * ✅ NUOVO: Genera riassunto conciso del testo
     */
    public function summarizeText($text, $maxLength = 200) {
        if (empty($text)) {
            return "Nessun contenuto da riassumere";
        }
        
        $prompt = "Riassumi il seguente testo in massimo $maxLength caratteri. Sii conciso e preciso.\n\n$text";
        
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 100,
            ]
        ];
        
        $response = $this->callGenerateContent($this->apiBase, $this->model, $payload);
        $summary = is_array($response) ? ($response['text'] ?? '') : $response;
        
        // Trunca se ancora troppo lungo
        return substr($summary, 0, $maxLength);
    }
}
