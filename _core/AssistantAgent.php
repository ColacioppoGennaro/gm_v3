<?php
/**
 * _core/AssistantAgent.php
 * 
 * Orchestratore AI conversazionale per creazione eventi calendario tramite dialogo naturale.
 * Gestisce conversazioni multi-turno con state management e integrazione Gemini + Google Calendar.
 * 
 * @author gm_v3 Assistant
 * @version 1.0.0
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/GeminiClient.php';
require_once __DIR__ . '/DocAnalyzerClient.php';

class AssistantAgent {
    
    private $userId;
    private $gemini;
    private $db;
    
    // Tipi evento validi
    const VALID_TYPES = ['payment', 'maintenance', 'document', 'personal'];
    
    // Campi obbligatori per creare evento
    const REQUIRED_FIELDS = ['title', 'date', 'type'];
    
    /**
     * Constructor
     * 
     * @param int $userId ID utente corrente
     * @throws Exception se user_id non valido
     */
    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception("User ID non valido");
        }
        
        $this->userId = intval($userId);
        $this->gemini = new GeminiClient();
        $this->db = db();
        
        error_log("AssistantAgent initialized for user: {$this->userId}");
    }
    
    /**
     * Processa messaggio utente e gestisce conversazione multi-turno
     * 
     * @param string $message Messaggio utente
     * @param array|null $sessionState Stato conversazione da sessione
     * @return array Response con status, message, data, state
     */
    public function processMessage($message, $sessionState = null) {
        try {
            $message = trim($message);
            
            if (empty($message)) {
                return $this->errorResponse("Messaggio vuoto");
            }
            
            // Inizializza stato se non esiste
            if (!$sessionState) {
                $sessionState = [
                    'intent' => null,
                    'partial_data' => [],
                    'missing_fields' => [],
                    'turn' => 0
                ];
            }
            
            $sessionState['turn']++;
            
            error_log("Processing message (turn {$sessionState['turn']}): " . substr($message, 0, 50));
            
            // Se è il primo turno, detecta intent
            if ($sessionState['turn'] === 1) {
                $intent = $this->detectIntent($message);
                $sessionState['intent'] = $intent;
                
                error_log("Intent detected: {$intent}");
                
                // Per ora gestiamo solo create_event
                if ($intent === 'create_event') {
                    return $this->handleEventCreation($message, $sessionState);
                } elseif ($intent === 'query_calendar') {
                    return $this->handleCalendarQuery($message, $sessionState);
                } else {
                    // Risposta generica
                    return $this->handleGeneric($message, $sessionState);
                }
            }
            
            // Turni successivi: continua flow basato su intent
            if ($sessionState['intent'] === 'create_event') {
                return $this->handleEventCreation($message, $sessionState);
            } elseif ($sessionState['intent'] === 'query_calendar') {
                return $this->handleCalendarQuery($message, $sessionState);
            }
            
            // Fallback
            return $this->handleGeneric($message, $sessionState);
            
        } catch (Exception $e) {
            error_log("AssistantAgent Error: " . $e->getMessage());
            return $this->errorResponse("Si è verificato un errore: " . $e->getMessage());
        }
    }
    
    /**
     * Detecta intent del messaggio usando Gemini
     * 
     * @param string $message Messaggio utente
     * @return string Intent: 'create_event' | 'query_calendar' | 'generic'
     */
    private function detectIntent($message) {
        $prompt = <<<PROMPT
Sei un assistente AI che analizza messaggi utente per capire l'intento.

INTENTI POSSIBILI:
- "create_event": Utente vuole creare un evento/promemoria (es: "mi è arrivata una bolletta", "devo fare il tagliando", "ricordami di chiamare")
- "query_calendar": Utente chiede info sul calendario (es: "quando va mia figlia in palestra?", "che eventi ho domani?")
- "generic": Altro (saluti, domande generiche, conversazione)

REGOLE:
- Rispondi SOLO con una delle 3 parole: create_event, query_calendar, generic
- Se c'è minimo dubbio tra create_event e query_calendar, scegli create_event
- Parole chiave create_event: bolletta, scadenza, tagliando, manutenzione, ricorda, promemoria, devo, ho ricevuto

MESSAGGIO UTENTE:
{$message}

INTENT:
PROMPT;

        try {
            $response = $this->gemini->ask($prompt);
            $intent = strtolower(trim($response));
            
            // Validazione intent
            $validIntents = ['create_event', 'query_calendar', 'generic'];
            if (!in_array($intent, $validIntents)) {
                // Fallback: cerca parole chiave
                $createKeywords = ['bolletta', 'scadenza', 'tagliando', 'manutenzione', 'ricorda', 'promemoria', 'devo', 'ricevuto'];
                $messageLower = strtolower($message);
                
                foreach ($createKeywords as $kw) {
                    if (strpos($messageLower, $kw) !== false) {
                        return 'create_event';
                    }
                }
                
                return 'generic';
            }
            
            return $intent;
            
        } catch (Exception $e) {
            error_log("Intent detection failed: " . $e->getMessage());
            // Fallback basato su keyword
            $createKeywords = ['bolletta', 'scadenza', 'tagliando', 'manutenzione', 'ricorda', 'promemoria'];
            $messageLower = strtolower($message);
            
            foreach ($createKeywords as $kw) {
                if (strpos($messageLower, $kw) !== false) {
                    return 'create_event';
                }
            }
            
            return 'generic';
        }
    }
    
    /**
     * Gestisce creazione evento con conversazione multi-turno
     * 
     * @param string $message Messaggio utente
     * @param array $state Stato conversazione
     * @return array Response
     */
    private function handleEventCreation($message, $state) {
        // Estrai dati dal messaggio corrente
        $extractedData = $this->extractEventData($message);
        
        // Merge con dati parziali esistenti
        $partialData = $state['partial_data'];
        foreach ($extractedData as $key => $value) {
            if ($value !== null && $value !== '') {
                $partialData[$key] = $value;
            }
        }
        
        error_log("Partial data after extraction: " . json_encode($partialData));
        
        // Valida e trova campi mancanti
        $validation = $this->validateAndCompleteEvent($partialData);
        
        if ($validation['complete']) {
            // Tutti i campi presenti: crea evento
            try {
                $eventId = $this->createCalendarEvent($validation['data']);
                
                return [
                    'status' => 'complete',
                    'message' => $this->generateSuccessMessage($validation['data'], $eventId),
                    'data' => [
                        'event_id' => $eventId,
                        'event_data' => $validation['data']
                    ],
                    'state' => null // Resetta stato
                ];
                
            } catch (Exception $e) {
                error_log("Calendar event creation failed: " . $e->getMessage());
                return $this->errorResponse("Non sono riuscito a creare l'evento: " . $e->getMessage());
            }
        }
        
        // Campi mancanti: chiedi info
        $question = $this->generateMissingFieldsQuestion($validation['missing'], $partialData);
        
        return [
            'status' => 'incomplete',
            'message' => $question,
            'data' => $partialData,
            'state' => [
                'intent' => 'create_event',
                'partial_data' => $partialData,
                'missing_fields' => $validation['missing'],
                'turn' => $state['turn']
            ]
        ];
    }
    
    /**
     * Estrae dati evento da messaggio usando Gemini
     * 
     * @param string $message Messaggio utente
     * @return array Dati evento estratti (null per campi non trovati)
     */
    private function extractEventData($message) {
        $prompt = <<<PROMPT
Sei un assistente che estrae informazioni per creare eventi calendario.

ESTRAI dal messaggio dell'utente e ritorna SOLO un oggetto JSON valido con questi campi:
{
  "title": "titolo evento (breve, es: Bolletta, Tagliando auto)",
  "date": "YYYY-MM-DD (solo se menzionata data specifica, altrimenti null)",
  "time": "HH:MM formato 24h (solo se ora specifica, altrimenti null)",
  "type": "payment | maintenance | document | personal (inferisci dal contesto)",
  "category": "etichetta libera (es: bolletta, multa, tagliando, assicurazione)",
  "recurrence": "DAILY | WEEKLY | MONTHLY | YEARLY (solo se ricorrente, altrimenti null)",
  "reminder_days_before": "numero intero giorni prima per promemoria (null se non specificato)",
  "description": "note aggiuntive (null se non presenti)"
}

REGOLE TIPO:
- "payment": bollette, pagamenti, multe, tasse
- "maintenance": tagliandi, manutenzioni, revisioni
- "document": scadenze documenti, rinnovi
- "personal": tutto il resto

REGOLE DATE:
- Se dice "15 marzo" → 2025-03-15 (anno corrente se non specificato)
- Se dice "tra 2 giorni" → calcola da oggi (2025-10-14)
- Se dice "domani" → 2025-10-15
- Se dice "prossimo lunedì" → calcola
- Se non menziona data → null

REGOLE REMINDER:
- "2 giorni prima" → 2
- "una settimana prima" → 7
- "promemoria" senza specificare → 1

MESSAGGIO UTENTE:
{$message}

JSON (SOLO JSON, nessun altro testo):
PROMPT;

        try {
            $response = $this->gemini->ask($prompt);
            
            // Pulisci response (rimuovi markdown se presente)
            $response = trim($response);
            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/\s*```$/i', '', $response);
            
            $data = json_decode($response, true);
            
            if (!is_array($data)) {
                error_log("Failed to parse Gemini JSON response: {$response}");
                return $this->getEmptyEventData();
            }
            
            // Valida tipo
            if (isset($data['type']) && !in_array($data['type'], self::VALID_TYPES)) {
                $data['type'] = 'personal'; // Fallback
            }
            
            error_log("Extracted event data: " . json_encode($data));
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Event data extraction failed: " . $e->getMessage());
            return $this->getEmptyEventData();
        }
    }
    
    /**
     * Valida dati evento e trova campi mancanti
     * 
     * @param array $data Dati parziali evento
     * @return array ['complete' => bool, 'data' => array, 'missing' => array]
     */
    private function validateAndCompleteEvent($data) {
        $missing = [];
        
        // Controlla campi obbligatori
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        // Se completo, aggiungi defaults
        if (empty($missing)) {
            // Default per campi opzionali
            $data['time'] = $data['time'] ?? null;
            $data['category'] = $data['category'] ?? '';
            $data['recurrence'] = $data['recurrence'] ?? null;
            $data['reminder_days_before'] = $data['reminder_days_before'] ?? null;
            $data['description'] = $data['description'] ?? '';
            $data['status'] = 'pending';
            $data['show_in_dashboard'] = true;
            
            return [
                'complete' => true,
                'data' => $data,
                'missing' => []
            ];
        }
        
        return [
            'complete' => false,
            'data' => $data,
            'missing' => $missing
        ];
    }
    
    /**
     * Genera domanda naturale per campi mancanti
     * 
     * @param array $missingFields Campi mancanti
     * @param array $partialData Dati già raccolti
     * @return string Domanda per utente
     */
    private function generateMissingFieldsQuestion($missingFields, $partialData) {
        if (empty($missingFields)) {
            return "Perfetto! Creo l'evento.";
        }
        
        $field = $missingFields[0]; // Chiedi un campo alla volta
        
        $prompt = <<<PROMPT
Sei un assistente amichevole che crea eventi calendario.

CONTESTO:
Stai raccogliendo informazioni per creare un evento. L'utente ha già fornito:
{$this->formatPartialDataForPrompt($partialData)}

CAMPO MANCANTE:
Devi chiedere il campo "{$field}".

REGOLE:
- Fai una domanda breve, naturale, amichevole
- Se il campo è "title": chiedi "Come vuoi chiamare questo evento?" o simile
- Se il campo è "date": chiedi "Quando scade?" o "Per quale data?"
- Se il campo è "type": chiedi "È personale o di lavoro?" o elenca opzioni
- Massimo 1-2 frasi
- Tono colloquiale

DOMANDA:
PROMPT;

        try {
            $question = $this->gemini->ask($prompt);
            return trim($question);
        } catch (Exception $e) {
            // Fallback domande predefinite
            $fallbacks = [
                'title' => "Come vuoi chiamare questo evento?",
                'date' => "Per quale data?",
                'type' => "È un pagamento, una manutenzione, un documento o qualcosa di personale?"
            ];
            
            return $fallbacks[$field] ?? "Mi puoi dare più dettagli?";
        }
    }
    
    /**
     * Crea evento su Google Calendar
     * 
     * @param array $eventData Dati completi evento
     * @return string Event ID creato
     * @throws Exception se creazione fallisce
     */
    private function createCalendarEvent($eventData) {
        // Prepara dati per API Google Calendar
        $isAllDay = empty($eventData['time']);
        
        $postData = [
            'title' => $eventData['title'],
            'description' => $eventData['description'] ?? '',
            'type' => $eventData['type'],
            'status' => 'pending',
            'category' => $eventData['category'] ?? '',
            'trigger' => 'assistant',
            'show_in_dashboard' => true
        ];
        
        if ($isAllDay) {
            $postData['allDay'] = '1';
            $postData['startDate'] = $eventData['date'];
            $postData['endDate'] = $eventData['date']; // Stesso giorno
        } else {
            $postData['allDay'] = '0';
            $dateTime = $eventData['date'] . 'T' . $eventData['time'] . ':00';
            $postData['startDateTime'] = $dateTime;
            // Fine: 1 ora dopo
            $endTime = date('Y-m-d\TH:i:s', strtotime($dateTime . ' +1 hour'));
            $postData['endDateTime'] = $endTime;
            $postData['timeZone'] = 'Europe/Rome';
        }
        
        // Ricorrenza
        if (!empty($eventData['recurrence'])) {
            $postData['recurrence'] = 'FREQ=' . $eventData['recurrence'];
        }
        
        // Promemoria
        if (!empty($eventData['reminder_days_before'])) {
            $minutes = intval($eventData['reminder_days_before']) * 1440; // giorni -> minuti
            $postData['reminders'] = json_encode([
                ['method' => 'popup', 'minutes' => $minutes]
            ]);
        }
        
        // Chiamata API interna (simula richiesta HTTP interna)
        $result = $this->callInternalAPI('google/events.php', $postData);
        
        if (!isset($result['id'])) {
            throw new Exception("API non ha ritornato event ID");
        }
        
        return $result['id'];
    }
    
    /**
     * Chiamata API interna (wrapper per api/google/events.php)
     * 
     * @param string $endpoint Endpoint relativo (es: google/events.php)
     * @param array $postData Dati POST
     * @return array Response decoded
     * @throws Exception se chiamata fallisce
     */
    private function callInternalAPI($endpoint, $postData) {
        // Simula POST request interno
        $_POST = $postData;
        $_GET['calendarId'] = 'primary';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Buffer output
        ob_start();
        
        try {
            require __DIR__ . '/../api/' . $endpoint;
            $output = ob_get_clean();
            
            $result = json_decode($output, true);
            
            if (!$result) {
                throw new Exception("Invalid API response: {$output}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * Gestisce query calendario (es: "quando va mia figlia in palestra?")
     * 
     * @param string $message Messaggio utente
     * @param array $state Stato conversazione
     * @return array Response
     */
    private function handleCalendarQuery($message, $state) {
        // TODO: Implementare in Sprint 4
        return [
            'status' => 'complete',
            'message' => "Scusa, la funzione di ricerca eventi non è ancora disponibile. Posso aiutarti a creare un nuovo evento?",
            'data' => null,
            'state' => null
        ];
    }
    
    /**
     * Gestisce conversazione generica
     * 
     * @param string $message Messaggio utente
     * @param array $state Stato conversazione
     * @return array Response
     */
    private function handleGeneric($message, $state) {
        try {
            $prompt = <<<PROMPT
Sei un assistente AI per la gestione di documenti e calendario.

Rispondi in modo breve e amichevole al messaggio dell'utente.
Se possibile, suggerisci come puoi aiutare (creare eventi, cercare nel calendario, etc).

MESSAGGIO:
{$message}

RISPOSTA (max 2-3 frasi):
PROMPT;

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
                'message' => "Ciao! Sono il tuo assistente AI. Posso aiutarti a creare eventi nel calendario o rispondere a domande generiche. Come posso aiutarti?",
                'data' => null,
                'state' => null
            ];
        }
    }
    
    /**
     * Genera messaggio di successo dopo creazione evento
     * 
     * @param array $eventData Dati evento creato
     * @param string $eventId Event ID
     * @return string Messaggio
     */
    private function generateSuccessMessage($eventData, $eventId) {
        $date = date('d/m/Y', strtotime($eventData['date']));
        $time = !empty($eventData['time']) ? ' alle ' . $eventData['time'] : '';
        
        $msg = "✅ Evento creato con successo!\n\n";
        $msg .= "📅 **{$eventData['title']}**\n";
        $msg .= "🗓️ {$date}{$time}\n";
        
        if (!empty($eventData['category'])) {
            $msg .= "🏷️ Categoria: {$eventData['category']}\n";
        }
        
        if (!empty($eventData['reminder_days_before'])) {
            $reminderDate = date('d/m/Y', strtotime($eventData['date'] . " -{$eventData['reminder_days_before']} days"));
            $msg .= "🔔 Promemoria: {$reminderDate}\n";
        }
        
        if (!empty($eventData['recurrence'])) {
            $recurrence = [
                'DAILY' => 'ogni giorno',
                'WEEKLY' => 'ogni settimana',
                'MONTHLY' => 'ogni mese',
                'YEARLY' => 'ogni anno'
            ];
            $msg .= "🔁 Si ripete: {$recurrence[$eventData['recurrence']]}\n";
        }
        
        return $msg;
    }
    
    /**
     * Helper: formatta dati parziali per prompt
     */
    private function formatPartialDataForPrompt($data) {
        if (empty($data)) {
            return "(nessuna informazione ancora)";
        }
        
        $lines = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = "- {$key}: {$value}";
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Helper: dati evento vuoti
     */
    private function getEmptyEventData() {
        return [
            'title' => null,
            'date' => null,
            'time' => null,
            'type' => null,
            'category' => null,
            'recurrence' => null,
            'reminder_days_before' => null,
            'description' => null
        ];
    }
    
    /**
     * Helper: response di errore
     */
    private function errorResponse($message) {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'state' => null
        ];
    }
}
