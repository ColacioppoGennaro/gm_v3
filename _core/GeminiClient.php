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
}
