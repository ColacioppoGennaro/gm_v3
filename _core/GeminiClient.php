<?php
/**
 * Google Gemini API Client
 * Fallback AI quando DocAnalyzer non trova risposta
 */
class GeminiClient {
    private $apiKey;
    private $apiBase = 'https://generativelanguage.googleapis.com/v1beta';
    private $model = 'gemini-1.5-flash';
    
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
        $url = "{$this->apiBase}/models/{$this->model}:generateContent?key={$this->apiKey}";
        
        // Costruisci prompt con contesto se disponibile
        $prompt = $question;
        if ($context) {
            $prompt = "Contesto: $context\n\nDomanda: $question";
        }
        
        $data = [
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
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errMsg = $result['error']['message'] ?? "HTTP $httpCode";
            error_log("Gemini API Error: $errMsg");
            throw new Exception("Gemini API Error: $errMsg");
        }
        
        // Estrai risposta
        $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        
        if (!$answer) {
            throw new Exception('Gemini non ha ritornato una risposta');
        }
        
        return $answer;
    }
}
