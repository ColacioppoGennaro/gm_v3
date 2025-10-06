<?php
/**
 * Google Gemini API Client
 * Fallback AI quando DocAnalyzer non trova risposta
 */
class GeminiClient {
    private $apiKey;
    // Lasciamo v1 come default, ma con fallback automatico a v1beta se il modello non è supportato
    private $apiBase = 'https://generativelanguage.googleapis.com/v1';
    
    // Modello richiesto
    private $model = 'gemini-1.5-flash-latest';

    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: env_get('GEMINI_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('Gemini API Key mancante');
        }
    }

    /**
     * Chiedi a Gemini
     */
    public function ask($question, $context = null) {
        $prompt = $context
            ? "Contesto: $context\n\nDomanda: $question"
            : $question;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ]
        ];

        // 1) Primo tentativo con apiBase corrente (v1)
        try {
            return $this->callGenerateContent($this->apiBase, $this->model, $payload);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // 2) Se l’errore indica modello/endpoint non supportato, ritenta in v1beta
            if (stripos($msg, 'not found') !== false
                || stripos($msg, 'is not supported') !== false
                || stripos($msg, '404') !== false) {

                $fallbackBase = 'https://generativelanguage.googleapis.com/v1beta';
                try {
                    // Aggiorno anche la proprietà per le prossime chiamate
                    $this->apiBase = $fallbackBase;
                    return $this->callGenerateContent($fallbackBase, $this->model, $payload);
                } catch (Exception $e2) {
                    // Se fallisce anche in v1beta, rilancio l’errore originale arricchito
                    throw new Exception("Gemini API Error dopo fallback a v1beta: " . $e2->getMessage());
                }
            }
            // Altri errori: rilancio
            throw $e;
        }
    }

    /**
     * Esegue la chiamata :generateContent e restituisce il testo
     */
    private function callGenerateContent($apiBase, $model, array $data) {
        $url = "{$apiBase}/models/{$model}:generateContent?key={$this->apiKey}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("cURL Error: $curlErr");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errMsg = $result['error']['message'] ?? "HTTP $httpCode";
            // Log utile in debugging, non rimuovo nulla del tuo flusso
            error_log("Gemini API Error: $errMsg");
            throw new Exception("Gemini API Error: $errMsg");
        }

        // Estrazione robusta del testo (gestisce anche possibili varianti del payload)
        $answer = $this->extractText($result);
        if (!$answer) {
            throw new Exception('Gemini non ha ritornato una risposta');
        }
        return $answer;
    }

    /**
     * Estrae il testo dalla risposta Gemini in modo robusto
     */
    private function extractText(array $result) {
        // Percorso standard v1/v1beta
        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text']) && is_string($part['text']) && $part['text'] !== '') {
                    return $part['text'];
                }
            }
        }
        // Alcuni SDK/varianti possono usare 'candidates[].content' o 'candidates[].output'
        if (isset($result['candidates'][0]['output']) && is_string($result['candidates'][0]['output'])) {
            return $result['candidates'][0]['output'];
        }
        // Fallback nullo
        return null;
    }
}
