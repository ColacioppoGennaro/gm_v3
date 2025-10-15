<?php
/**
 * FILE: _core/AssistantAgent.php
 * 
 * Orchestratore AI conversazionale INTELLIGENTE
 * - Conversazione progressiva (max 2 domande alla volta)
 * - Supporto analisi immagini via Gemini 2.0 Flash
 * - Apertura modal calendario precompilato per conferma
 * 
 * @version 3.0.0 - Intelligent Conversation
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/GeminiClient.php';
require_once __DIR__ . '/DocAnalyzerClient.php';
require_once __DIR__ . '/SettoriManager.php';
require_once __DIR__ . '/TipiAttivitaManager.php';

class AssistantAgent {
    
    private $userId;
    private $gemini;
    private $db;
    private $userContext;
    
    // Campi obbligatori per creare evento
    const REQUIRED_FIELDS = ['title', 'date', 'settore_id', 'tipo_attivita_id'];
    
    // Campi opzionali suggeriti
    const OPTIONAL_FIELDS = ['time', 'amount', 'reminder_days_before', 'description'];
    
    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception("User ID non valido");
        }
        
        $this->userId = intval($userId);
        $this->gemini = new GeminiClient();
        $this->db = db();
        
        $this->loadUserContext();
        
        error_log("AssistantAgent v3.0 initialized for user: {$this->userId}");
    }
    
    /**
     * Carica settori e tipi attività utente per context AI
     */
    private function loadUserContext() {
        $settoriMgr = new SettoriManager($this->userId);
        $tipiMgr = new TipiAttivitaManager($this->userId);
        
        $settori = $settoriMgr->list();
        $tipi = $tipiMgr->list();
        
        // Organizza tipi per settore
        $settoriConTipi = [];
        foreach ($settori as $settore) {
            $settoriConTipi[$settore['id']] = [
                'nome' => $settore['nome'],
                'icona' => $settore['icona'],
                'tipi' => []
            ];
        }
        
        foreach ($tipi as $tipo) {
            if (isset($settoriConTipi[$tipo['settore_id']])) {
                $settoriConTipi[$tipo['settore_id']]['tipi'][] = [
                    'id' => $tipo['id'],
                    'nome' => $tipo['nome'],
                    'icona' => $tipo['icona']
                ];
            }
        }
        
        // Crea stringa contesto per AI
        $contextLines = ["SETTORI E TIPI UTENTE:"];
        foreach ($settoriConTipi as $sid => $settore) {
            $contextLines[] = "- {$settore['icona']} {$settore['nome']} (settore_id: {$sid})";
            foreach ($settore['tipi'] as $tipo) {
                $contextLines[] = "  → {$tipo['icona']} {$tipo['nome']} (tipo_attivita_id: {$tipo['id']})";
            }
        }
        
        $this->userContext = implode("\n", $contextLines);
        
        error_log("User context loaded: " . strlen($this->userContext) . " chars");
    }
    
    /**
     * ENTRY POINT: Processa messaggio utente (con o senza immagine)
     */
    public function processMessage($message, $sessionState = null, $imagePath = null) {
        try {
            $message = trim($message);
            
            // Inizializza stato se primo messaggio
            if (!$sessionState) {
                $sessionState = [
                    'intent' => null,
                    'partial_data' => [],
                    'missing_fields' => [],
                    'asked_fields' => [], // Campi già chiesti
                    'turn' => 0,
                    'image_analyzed' => false
                ];
            }
            
            $sessionState['turn']++;
            
            error_log("Processing message (turn {$sessionState['turn']}): " . substr($message, 0, 50) . ($imagePath ? " [WITH IMAGE]" : ""));
            
            // Se c'è un'immagine, analizzala PRIMA
            if ($imagePath && !$sessionState['image_analyzed']) {
                return $this->handleImageAnalysis($imagePath, $sessionState);
            }
            
            // Primo turno: detect intent
            if ($sessionState['turn'] === 1 && !$sessionState['intent']) {
                $intent = $this->detectIntent($message);
                $sessionState['intent'] = $intent;
                
                error_log("Intent detected: {$intent}");
                
                // Se non è creazione evento, gestisci subito
                if ($intent !== 'create_event') {
                    return $this->handleGeneric($message, $sessionState);
                }
            }
            
            // Gestione creazione evento (conversazionale)
            if ($sessionState['intent'] === 'create_event') {
                return $this->handleEventCreation($message, $sessionState);
            }
            
            return $this->handleGeneric($message, $sessionState);
            
        } catch (Exception $e) {
            error_log("AssistantAgent Error: " . $e->getMessage());
            return $this->errorResponse("Si è verificato un errore: " . $e->getMessage());
        }
    }
    
    /**
     * ANALISI IMMAGINE: Estrae dati da foto bolletta/documento
     */
    private function handleImageAnalysis($imagePath, $state) {
        try {
            error_log("Analyzing image: {$imagePath}");
            
            // Leggi immagine e converti in base64
            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = mime_content_type($imagePath);
            
            // Prompt per Gemini con immagine
            $prompt = <<<PROMPT
Analizza questa immagine di una bolletta/fattura/documento e estrai TUTTE le informazioni possibili.

Ritorna SOLO un oggetto JSON valido con questi campi:
{
  "tipo_documento": "bolletta|fattura|ricevuta|altro",
  "fornitore": "nome azienda/ente",
  "descrizione": "cosa è (es: Bolletta Luce, Fattura Telefono)",
  "importo": "importo in euro (solo numero, es: 89.50)",
  "data_scadenza": "YYYY-MM-DD se presente, altrimenti null",
  "data_emissione": "YYYY-MM-DD se presente, altrimenti null",
  "numero_documento": "codice/numero documento se presente",
  "note": "altre info rilevanti"
}

REGOLE:
- Se non trovi un campo, metti null
- Per importo usa solo numeri (es: 89.50, non €89,50)
- Per date usa formato YYYY-MM-DD
- Sii preciso ma non inventare dati

JSON:
PROMPT;

            // Chiama Gemini con immagine
            $response = $this->gemini->analyzeImage($prompt, $base64Image, $mimeType);
            
            // Parse JSON response
            $response = trim($response);
            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/\s*```$/i', '', $response);
            
            $extractedData = json_decode($response, true);
            
            if (!is_array($extractedData)) {
                throw new Exception("Errore parsing risposta AI");
            }
            
            error_log("Image analysis result: " . json_encode($extractedData));
            
            // Salva documento in DB (NON inviare a DocAnalyzer)
            $documentId = $this->saveImageDocument($imagePath, $extractedData);
            
            // Prepara dati parziali da immagine
            $partialData = [
                'document_id' => $documentId
            ];
            
            // Mappa dati estratti
            if (!empty($extractedData['descrizione'])) {
                $partialData['title'] = $extractedData['descrizione'];
            }
            
            if (!empty($extractedData['data_scadenza'])) {
                $partialData['date'] = $extractedData['data_scadenza'];
            }
            
            if (!empty($extractedData['importo'])) {
                $partialData['amount'] = floatval($extractedData['importo']);
            }
            
            if (!empty($extractedData['note'])) {
                $partialData['description'] = $extractedData['note'];
            }
            
            // Aggiorna stato
            $state['partial_data'] = $partialData;
            $state['image_analyzed'] = true;
            $state['extracted_data'] = $extractedData;
            
            // Genera messaggio riepilogo
            $summary = $this->generateImageSummary($extractedData);
            
            // Chiedi conferma + settore (SEMPRE necessario)
            $question = $summary . "\n\n" . $this->askNextQuestions($state);
            
            return [
                'status' => 'incomplete',
                'message' => $question,
                'data' => $partialData,
                'state' => $state
            ];
            
        } catch (Exception $e) {
            error_log("Image analysis failed: " . $e->getMessage());
            return $this->errorResponse("Non sono riuscito a leggere l'immagine. Prova a descrivermi il documento a parole.");
        }
    }
    
    /**
     * Salva immagine in DB (NON invia a DocAnalyzer)
     */
    private function saveImageDocument($imagePath, $extractedData) {
        $fileName = basename($imagePath);
        $fileSize = filesize($imagePath);
        
        // Sposta file in cartella uploads permanente
        $uploadsDir = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        $newFileName = uniqid('img_') . '_' . $fileName;
        $newPath = $uploadsDir . '/' . $newFileName;
        
        if (!copy($imagePath, $newPath)) {
            throw new Exception("Errore salvataggio immagine");
        }
        
        // Inserisci in DB
        $title = $extractedData['descrizione'] ?? 'Documento fotografato';
        
        $stmt = $this->db->prepare("
            INSERT INTO documents (user_id, filename, original_name, file_size, mime_type, upload_date, docanalyzer_docid)
            VALUES (?, ?, ?, ?, ?, NOW(), NULL)
        ");
        
        $mimeType = mime_content_type($newPath);
        
        $stmt->bind_param("issds", 
            $this->userId, 
            $newFileName, 
            $fileName, 
            $fileSize, 
            $mimeType
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Errore inserimento documento: " . $stmt->error);
        }
        
        $documentId = $this->db->insert_id;
        
        error_log("Image saved as document ID: {$documentId}");
        
        return $documentId;
    }
    
    /**
     * Genera riepilogo dati estratti da immagine
     */
    private function generateImageSummary($data) {
        $lines = ["📸 Ho analizzato l'immagine:"];
        
        if (!empty($data['descrizione'])) {
            $lines[] = "📄 **{$data['descrizione']}**";
        }
        
        if (!empty($data['fornitore'])) {
            $lines[] = "🏢 Fornitore: {$data['fornitore']}";
        }
        
        if (!empty($data['importo'])) {
            $lines[] = "💰 Importo: €" . number_format($data['importo'], 2, ',', '.');
        }
        
        if (!empty($data['data_scadenza'])) {
            $lines[] = "📅 Scadenza: " . date('d/m/Y', strtotime($data['data_scadenza']));
        }
        
        if (!empty($data['numero_documento'])) {
            $lines[] = "🔢 N. {$data['numero_documento']}";
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Detect intent del messaggio
     */
    private function detectIntent($message) {
        $prompt = <<<PROMPT
Analizza il messaggio e rispondi con UNA SOLA PAROLA.

INTENTI:
- "create_event" → vuole creare evento/promemoria/scadenza
- "generic" → saluto/chiacchiera/altro

KEYWORDS CREATE_EVENT: bolletta, fattura, scadenza, pagamento, manutenzione, tagliando, ricorda, promemoria, devo pagare, ho ricevuto

MESSAGGIO:
{$message}

INTENT (una sola parola):
PROMPT;

        try {
            $response = $this->gemini->ask($prompt);
            $intent = strtolower(trim($response));
            
            if (!in_array($intent, ['create_event', 'generic'])) {
                // Fallback: cerca keywords
                $keywords = ['bolletta', 'fattura', 'scadenza', 'pagamento', 'manutenzione', 'tagliando', 'ricorda', 'promemoria', 'devo', 'ricevuto'];
                $messageLower = strtolower($message);
                
                foreach ($keywords as $kw) {
                    if (strpos($messageLower, $kw) !== false) {
                        return 'create_event';
                    }
                }
                
                return 'generic';
            }
            
            return $intent;
            
        } catch (Exception $e) {
            error_log("Intent detection failed: " . $e->getMessage());
            return 'generic';
        }
    }
    
    /**
     * GESTIONE CREAZIONE EVENTO - Conversazione intelligente
     */
    private function handleEventCreation($message, $state) {
        // Estrai nuovi dati dal messaggio
        $extractedData = $this->extractEventData($message);
        
        // Merge con dati parziali esistenti
        $partialData = $state['partial_data'];
        foreach ($extractedData as $key => $value) {
            if ($value !== null && $value !== '') {
                $partialData[$key] = $value;
            }
        }
        
        error_log("Partial data: " . json_encode($partialData));
        
        // Valida completezza
        $validation = $this->validateEventData($partialData);
        
        // Se completo, APRI MODAL CALENDARIO
        if ($validation['complete']) {
            return [
                'status' => 'ready_for_modal',
                'message' => "✅ Perfetto! Ho tutte le informazioni.\n\n" . $this->formatEventPreview($partialData) . "\n\n**Apro il calendario per la conferma...**",
                'data' => $this->prepareEventForModal($partialData),
                'state' => null
            ];
        }
        
        // Altrimenti, chiedi SOLO i campi mancanti (max 2 alla volta)
        $question = $this->askNextQuestions($state);
        
        return [
            'status' => 'incomplete',
            'message' => $question,
            'data' => $partialData,
            'state' => [
                'intent' => 'create_event',
                'partial_data' => $partialData,
                'missing_fields' => $validation['missing'],
                'asked_fields' => $state['asked_fields'] ?? [],
                'turn' => $state['turn'],
                'image_analyzed' => $state['image_analyzed'] ?? false,
                'extracted_data' => $state['extracted_data'] ?? null
            ]
        ];
    }
    
    /**
     * ESTRAE dati evento da messaggio (AI intelligente)
     */
    private function extractEventData($message) {
        $currentDate = date('Y-m-d');
        
        $prompt = <<<PROMPT
Data corrente: {$currentDate}

{$this->userContext}

Analizza il messaggio e estrai TUTTE le informazioni possibili.
Ritorna SOLO JSON valido:

{
  "title": "titolo evento (descrittivo)",
  "date": "YYYY-MM-DD (null se non specificata)",
  "time": "HH:MM (null se non specificata)",
  "settore_id": numero_id (null se non determinabile),
  "tipo_attivita_id": numero_id (null se non determinabile),
  "amount": numero_decimale (null se non menzionato),
  "reminder_days_before": numero_intero (null se non richiesto),
  "description": "note extra (null se assenti)"
}

REGOLE MATCHING:
- "bolletta luce/gas/acqua" → Casa + Bollette Utenze
- "fattura/stipendio" → Lavoro
- "manutenzione auto/mezzo" → Lavoro + Manutenzione Mezzi
- "medico/visita" → Persone + Salute
- "palestra/sport" → Persone + Sport

DATE:
- "tra 15 giorni" → calcola da oggi
- "25 novembre" → 2025-11-25
- "domani" → {$currentDate} + 1 giorno

IMPORTI:
- "100 euro" → 100.00
- "€89,50" → 89.50

MESSAGGIO:
{$message}

JSON:
PROMPT;

        try {
            $response = $this->gemini->ask($prompt);
            
            // Pulisci response
            $response = trim($response);
            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/\s*```$/i', '', $response);
            
            $data = json_decode($response, true);
            
            if (!is_array($data)) {
                error_log("JSON parsing failed: {$response}");
                return $this->getEmptyEventData();
            }
            
            // Cast numerici
            if (isset($data['settore_id'])) $data['settore_id'] = intval($data['settore_id']);
            if (isset($data['tipo_attivita_id'])) $data['tipo_attivita_id'] = intval($data['tipo_attivita_id']);
            if (isset($data['amount'])) $data['amount'] = floatval($data['amount']);
            if (isset($data['reminder_days_before'])) $data['reminder_days_before'] = intval($data['reminder_days_before']);
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Extraction failed: " . $e->getMessage());
            return $this->getEmptyEventData();
        }
    }
    
    /**
     * VALIDA completezza dati evento
     */
    private function validateEventData($data) {
        $missing = [];
        
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        return [
            'complete' => empty($missing),
            'missing' => $missing
        ];
    }
    
    /**
     * CHIEDI campi mancanti (MAX 2 alla volta)
     */
    private function askNextQuestions($state) {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($state['partial_data'][$field]) || $state['partial_data'][$field] === null) {
                if (!in_array($field, $state['asked_fields'] ?? [])) {
                    $missing[] = $field;
                }
            }
        }
        
        if (empty($missing)) {
            // Tutti i campi obbligatori ci sono
            // Chiedi facoltativi utili
            $optional = [];
            if (!isset($state['partial_data']['amount'])) $optional[] = 'amount';
            if (!isset($state['partial_data']['reminder_days_before'])) $optional[] = 'reminder_days_before';
            
            if (!empty($optional)) {
                $missing = array_slice($optional, 0, 2);
            }
        }
        
        if (empty($missing)) {
            return "Ho tutte le informazioni!";
        }
        
        // Prendi max 2 campi
        $toAsk = array_slice($missing, 0, 2);
        
        // Genera domanda naturale
        $questions = [];
        
        foreach ($toAsk as $field) {
            switch ($field) {
                case 'title':
                    $questions[] = "Come vuoi chiamare questo evento?";
                    break;
                case 'date':
                    $questions[] = "Quando scade/è previsto?";
                    break;
                case 'settore_id':
                    $questions[] = "È per **Lavoro**, **Casa** o **Persone**?";
                    break;
                case 'tipo_attivita_id':
                    $settoreId = $state['partial_data']['settore_id'] ?? null;
                    if ($settoreId) {
                        $tipi = $this->getTipiBySettore($settoreId);
                        $nomiTipi = array_map(fn($t) => $t['nome'], $tipi);
                        $questions[] = "Che tipo? (" . implode(', ', $nomiTipi) . ")";
                    } else {
                        $questions[] = "Di che tipo è? (Bolletta, Manutenzione, Salute...)";
                    }
                    break;
                case 'amount':
                    $questions[] = "Qual è l'importo?";
                    break;
                case 'reminder_days_before':
                    $questions[] = "Vuoi un promemoria? (es: 3 giorni prima)";
                    break;
            }
            
            // Marca come chiesto
            $state['asked_fields'][] = $field;
        }
        
        return implode("\n", $questions);
    }
    
    /**
     * Ottieni tipi per settore
     */
    private function getTipiBySettore($settoreId) {
        $stmt = $this->db->prepare("
            SELECT id, nome, icona
            FROM tipi_attivita
            WHERE user_id = ? AND settore_id = ?
            ORDER BY ordine ASC
        ");
        $stmt->bind_param("ii", $this->userId, $settoreId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Formatta preview evento
     */
    private function formatEventPreview($data) {
        $lines = ["📋 **Riepilogo Evento:**"];
        
        $lines[] = "📄 " . ($data['title'] ?? 'Senza titolo');
        $lines[] = "📅 " . date('d/m/Y', strtotime($data['date']));
        
        if (!empty($data['time'])) {
            $lines[] = "🕒 " . $data['time'];
        }
        
        $settoreNome = $this->getSettoreNome($data['settore_id']);
        $tipoNome = $this->getTipoNome($data['tipo_attivita_id']);
        $lines[] = "📂 {$settoreNome} → {$tipoNome}";
        
        if (!empty($data['amount'])) {
            $lines[] = "💰 €" . number_format($data['amount'], 2, ',', '.');
        }
        
        if (!empty($data['reminder_days_before'])) {
            $reminderDate = date('d/m/Y', strtotime($data['date'] . " -{$data['reminder_days_before']} days"));
            $lines[] = "🔔 Promemoria: {$reminderDate}";
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Prepara dati per modal calendario
     */
    private function prepareEventForModal($data) {
        return [
            'title' => $data['title'],
            'date' => $data['date'],
            'time' => $data['time'] ?? null,
            'settore_id' => $data['settore_id'],
            'tipo_attivita_id' => $data['tipo_attivita_id'],
            'amount' => $data['amount'] ?? null,
            'reminder_days_before' => $data['reminder_days_before'] ?? null,
            'description' => $data['description'] ?? '',
            'document_id' => $data['document_id'] ?? null,
            'status' => 'pending',
            'show_in_dashboard' => true
        ];
    }
    
    /**
     * Gestione messaggi generici
     */
    private function handleGeneric($message, $state) {
        try {
            $prompt = "Rispondi in modo breve e amichevole (max 2 frasi).\n\nMessaggio: {$message}\n\nRisposta:";
            $response = $this->gemini->ask($prompt);
            
            return [
                'status' => 'complete',
                'message' => $response,
                'data' => null,
                'state' => null
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'complete',
                'message' => "Ciao! Sono il tuo assistente AI. Posso aiutarti a creare eventi nel calendario. Prova a dirmi 'Ho ricevuto una bolletta' oppure carica una foto! 📸",
                'data' => null,
                'state' => null
            ];
        }
    }
    
    // ========== HELPER METHODS ==========
    
    private function getSettoreNome($settoreId) {
        $stmt = $this->db->prepare("SELECT nome FROM settori WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $settoreId, $this->userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['nome'] ?? 'Sconosciuto';
    }
    
    private function getTipoNome($tipoId) {
        $stmt = $this->db->prepare("SELECT nome FROM tipi_attivita WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $tipoId, $this->userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['nome'] ?? 'Sconosciuto';
    }
    
    private function getEmptyEventData() {
        return [
            'title' => null,
            'date' => null,
            'time' => null,
            'settore_id' => null,
            'tipo_attivita_id' => null,
            'amount' => null,
            'reminder_days_before' => null,
            'description' => null
        ];
    }
    
    private function errorResponse($message) {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'state' => null
        ];
    }
}
