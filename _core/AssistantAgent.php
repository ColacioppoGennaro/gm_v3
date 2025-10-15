<?php
/**
 * FILE: _core/AssistantAgent.php
 * 
 * Orchestratore AI conversazionale con sistema settori dinamico
 * 
 * @version 2.0.0 - Sistema Settori
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
    private $userContext; // ✨ NUOVO: contesto utente (settori/tipi)
    
    // ✅ RIMOSSO: Tipi fissi non servono più
    // const VALID_TYPES = ['payment', 'maintenance', 'document', 'personal'];
    
    // Campi obbligatori per creare evento
    const REQUIRED_FIELDS = ['title', 'date', 'settore_id', 'tipo_attivita_id'];
    
    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception("User ID non valido");
        }
        
        $this->userId = intval($userId);
        $this->gemini = new GeminiClient();
        $this->db = db();
        
        // ✨ NUOVO: Carica contesto utente
        $this->loadUserContext();
        
        error_log("AssistantAgent initialized for user: {$this->userId}");
    }
    
    /**
     * ✨ NUOVO: Carica settori e tipi attività utente per context AI
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
        $contextLines = ["SETTORI UTENTE:"];
        foreach ($settoriConTipi as $sid => $settore) {
            $contextLines[] = "- {$settore['icona']} {$settore['nome']} (ID: {$sid})";
            foreach ($settore['tipi'] as $tipo) {
                $contextLines[] = "  → {$tipo['icona']} {$tipo['nome']} (ID: {$tipo['id']})";
            }
        }
        
        $this->userContext = implode("\n", $contextLines);
        
        error_log("User context loaded: " . strlen($this->userContext) . " chars");
    }
    
    public function processMessage($message, $sessionState = null) {
        try {
            $message = trim($message);
            
            if (empty($message)) {
                return $this->errorResponse("Messaggio vuoto");
            }
            
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
            
            if ($sessionState['turn'] === 1) {
                $intent = $this->detectIntent($message);
                $sessionState['intent'] = $intent;
                
                error_log("Intent detected: {$intent}");
                
                if ($intent === 'create_event') {
                    return $this->handleEventCreation($message, $sessionState);
                } elseif ($intent === 'query_calendar') {
                    return $this->handleCalendarQuery($message, $sessionState);
                } else {
                    return $this->handleGeneric($message, $sessionState);
                }
            }
            
            if ($sessionState['intent'] === 'create_event') {
                return $this->handleEventCreation($message, $sessionState);
            } elseif ($sessionState['intent'] === 'query_calendar') {
                return $this->handleCalendarQuery($message, $sessionState);
            }
            
            return $this->handleGeneric($message, $sessionState);
            
        } catch (Exception $e) {
            error_log("AssistantAgent Error: " . $e->getMessage());
            return $this->errorResponse("Si è verificato un errore: " . $e->getMessage());
        }
    }
    
    private function detectIntent($message) {
        $prompt = <<<PROMPT
Sei un assistente AI che analizza messaggi utente per capire l'intento.

INTENTI POSSIBILI:
- "create_event": Utente vuole creare un evento/promemoria
- "query_calendar": Utente chiede info sul calendario
- "generic": Altro

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
            
            $validIntents = ['create_event', 'query_calendar', 'generic'];
            if (!in_array($intent, $validIntents)) {
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
    
    private function handleEventCreation($message, $state) {
        $extractedData = $this->extractEventData($message);
        
        $partialData = $state['partial_data'];
        foreach ($extractedData as $key => $value) {
            if ($value !== null && $value !== '') {
                $partialData[$key] = $value;
            }
        }
        
        error_log("Partial data after extraction: " . json_encode($partialData));
        
        $validation = $this->validateAndCompleteEvent($partialData);
        
        if ($validation['complete']) {
            try {
                $eventId = $this->createCalendarEvent($validation['data']);
                
                return [
                    'status' => 'complete',
                    'message' => $this->generateSuccessMessage($validation['data'], $eventId),
                    'data' => [
                        'event_id' => $eventId,
                        'event_data' => $validation['data']
                    ],
                    'state' => null
                ];
                
            } catch (Exception $e) {
                error_log("Calendar event creation failed: " . $e->getMessage());
                return $this->errorResponse("Non sono riuscito a creare l'evento: " . $e->getMessage());
            }
        }
        
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
     * ✨ MODIFICATO: Estrae dati evento con matching settore/tipo dinamico
     */
    private function extractEventData($message) {
        $prompt = <<<PROMPT
Sei un assistente che estrae informazioni per creare eventi calendario.

{$this->userContext}

ESTRAI dal messaggio dell'utente e ritorna SOLO un oggetto JSON valido:
{
  "title": "titolo evento (breve)",
  "date": "YYYY-MM-DD (solo se menzionata, altrimenti null)",
  "time": "HH:MM formato 24h (solo se specifica, altrimenti null)",
  "settore_id": "ID settore più appropriato (numero, non nome)",
  "tipo_attivita_id": "ID tipo attività più appropriato (numero, non nome)",
  "category": "etichetta libera opzionale",
  "recurrence": "DAILY | WEEKLY | MONTHLY | YEARLY (solo se ricorrente, altrimenti null)",
  "reminder_days_before": "numero intero giorni prima per promemoria (null se non specificato)",
  "description": "note aggiuntive (null se non presenti)"
}

REGOLE MATCHING:
- "bolletta luce" → Settore: Casa, Tipo: Bollette Utenze
- "stipendi" → Settore: Lavoro, Tipo: Stipendi da Pagare
- "tagliando auto" → Settore: Lavoro, Tipo: Manutenzione Mezzi
- "gatto veterinario" → Settore: Persone, Tipo: Veterinario
- "palestra" → Settore: Persone, Tipo: Sport

REGOLE DATE:
- Data corrente: 15 ottobre 2025
- Se dice "14 novembre" → 2025-11-14
- Se dice "domani" → 2025-10-16
- Se non menziona data → null

IMPORTANTE:
- Usa gli ID NUMERICI, non i nomi
- Se non sei sicuro del settore/tipo, metti null

MESSAGGIO UTENTE:
{$message}

JSON (SOLO JSON, nessun altro testo):
PROMPT;

        try {
            $response = $this->gemini->ask($prompt);
            
            $response = trim($response);
            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/\s*```$/i', '', $response);
            
            $data = json_decode($response, true);
            
            if (!is_array($data)) {
                error_log("Failed to parse Gemini JSON response: {$response}");
                return $this->getEmptyEventData();
            }
            
            // Converte ID in int se presenti
            if (isset($data['settore_id'])) {
                $data['settore_id'] = intval($data['settore_id']);
            }
            if (isset($data['tipo_attivita_id'])) {
                $data['tipo_attivita_id'] = intval($data['tipo_attivita_id']);
            }
            
            error_log("Extracted event data: " . json_encode($data));
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Event data extraction failed: " . $e->getMessage());
            return $this->getEmptyEventData();
        }
    }
    
    /**
     * ✨ MODIFICATO: Validazione con nuovi campi obbligatori
     */
    private function validateAndCompleteEvent($data) {
        $missing = [];
        
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (empty($missing)) {
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
    
    private function generateMissingFieldsQuestion($missingFields, $partialData) {
        if (empty($missingFields)) {
            return "Perfetto! Creo l'evento.";
        }
        
        $field = $missingFields[0];
        
        $fallbacks = [
            'title' => "Come vuoi chiamare questo evento?",
            'date' => "Per quale data? (es: 15 marzo, 20/11/2025, domani)",
            'settore_id' => "È per Lavoro, Casa o Persone?",
            'tipo_attivita_id' => "Di che tipo di evento si tratta? (es: Bolletta, Manutenzione, Salute...)"
        ];
        
        if (isset($fallbacks[$field])) {
            return $fallbacks[$field];
        }
        
        $prompt = <<<PROMPT
Sei un assistente amichevole che crea eventi calendario.

CONTESTO:
{$this->formatPartialDataForPrompt($partialData)}

CAMPO MANCANTE: {$field}

Fai una domanda breve e naturale per chiedere questo campo.

DOMANDA:
PROMPT;

        try {
            $question = $this->gemini->ask($prompt);
            return trim($question);
        } catch (Exception $e) {
            error_log("Question generation failed: " . $e->getMessage());
            return $fallbacks[$field] ?? "Mi puoi dare più dettagli?";
        }
    }
    
    /**
     * ✨ MODIFICATO: Creazione evento con nuova struttura extendedProperties
     */
    private function createCalendarEvent($eventData) {
        $stmt = $this->db->prepare("SELECT google_oauth_token, google_oauth_refresh, google_oauth_expiry FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $oauth = $result->fetch_assoc();
        
        if (!$oauth || empty($oauth['google_oauth_token'])) {
            throw new Exception("Google Calendar non connesso. Collegalo prima di creare eventi.");
        }
        
        require_once __DIR__ . '/google_client.php';
        
        $oauthFormatted = [
            'access_token' => $oauth['google_oauth_token'],
            'refresh_token' => $oauth['google_oauth_refresh'] ?? null,
            'access_expires_at' => $oauth['google_oauth_expiry'] ?? null,
        ];
        
        try {
            $client = makeGoogleClientForUser($oauthFormatted);
            $service = new Google_Service_Calendar($client);
        } catch (Exception $e) {
            throw new Exception("Errore inizializzazione Google Calendar: " . $e->getMessage());
        }
        
        $isAllDay = empty($eventData['time']);
        
        $event = new Google_Service_Calendar_Event([
            'summary' => $eventData['title'],
            'description' => $eventData['description'] ?? ''
        ]);
        
        if ($isAllDay) {
            $event->setStart(new Google_Service_Calendar_EventDateTime([
                'date' => $eventData['date']
            ]));
            $event->setEnd(new Google_Service_Calendar_EventDateTime([
                'date' => $eventData['date']
            ]));
        } else {
            $dateTime = $eventData['date'] . 'T' . $eventData['time'] . ':00';
            $endTime = date('Y-m-d\TH:i:s', strtotime($dateTime . ' +1 hour'));
            
            $event->setStart(new Google_Service_Calendar_EventDateTime([
                'dateTime' => $dateTime,
                'timeZone' => 'Europe/Rome'
            ]));
            $event->setEnd(new Google_Service_Calendar_EventDateTime([
                'dateTime' => $endTime,
                'timeZone' => 'Europe/Rome'
            ]));
        }
        
        if (!empty($eventData['recurrence'])) {
            $event->setRecurrence(['RRULE:FREQ=' . $eventData['recurrence']]);
        }
        
        if (!empty($eventData['reminder_days_before'])) {
            $minutes = intval($eventData['reminder_days_before']) * 1440;
            $reminder = new Google_Service_Calendar_EventReminder([
                'method' => 'popup',
                'minutes' => $minutes
            ]);
            $reminders = new Google_Service_Calendar_EventReminders([
                'useDefault' => false,
                'overrides' => [$reminder]
            ]);
            $event->setReminders($reminders);
        }
        
        // ✨ NUOVA STRUTTURA: Extended Properties con settore e tipo
        $extProps = new Google_Service_Calendar_EventExtendedProperties();
        $privateData = [
            'settore_id' => (string)$eventData['settore_id'],
            'tipo_attivita_id' => (string)$eventData['tipo_attivita_id'],
            'status' => 'pending',
            'trigger' => 'assistant',
            'show_in_dashboard' => 'true'
        ];
        
        if (!empty($eventData['category'])) {
            $privateData['category'] = $eventData['category'];
        }
        
        $extProps->setPrivate($privateData);
        $event->setExtendedProperties($extProps);
        
        $createdEvent = $service->events->insert('primary', $event);
        
        if (!$createdEvent || !$createdEvent->getId()) {
            throw new Exception("Errore creazione evento su Google Calendar");
        }
        
        return $createdEvent->getId();
    }
    
    private function handleCalendarQuery($message, $state) {
        return [
            'status' => 'complete',
            'message' => "Scusa, la funzione di ricerca eventi non è ancora disponibile. Posso aiutarti a creare un nuovo evento?",
            'data' => null,
            'state' => null
        ];
    }
    
    private function handleGeneric($message, $state) {
        try {
            $prompt = <<<PROMPT
Sei un assistente AI per la gestione di documenti e calendario.

Rispondi in modo breve e amichevole al messaggio dell'utente.

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
                'message' => "Ciao! Sono il tuo assistente AI. Posso aiutarti a creare eventi nel calendario. Come posso aiutarti?",
                'data' => null,
                'state' => null
            ];
        }
    }
    
    /**
     * ✨ MODIFICATO: Messaggio di successo con info settore/tipo
     */
    private function generateSuccessMessage($eventData, $eventId) {
        $date = date('d/m/Y', strtotime($eventData['date']));
        $time = !empty($eventData['time']) ? ' alle ' . $eventData['time'] : '';
        
        // Ottieni nomi settore e tipo
        $settoreNome = $this->getSettoreNome($eventData['settore_id']);
        $tipoNome = $this->getTipoNome($eventData['tipo_attivita_id']);
        
        $msg = "✅ Evento creato con successo!\n\n";
        $msg .= "📅 **{$eventData['title']}**\n";
        $msg .= "🗓️ {$date}{$time}\n";
        $msg .= "📂 {$settoreNome} → {$tipoNome}\n";
        
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
    
    private function getEmptyEventData() {
        return [
            'title' => null,
            'date' => null,
            'time' => null,
            'settore_id' => null,
            'tipo_attivita_id' => null,
            'category' => null,
            'recurrence' => null,
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
