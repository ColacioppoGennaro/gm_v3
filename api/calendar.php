<?php
// File: public_html/gm_v3/api/calendar.php

require_once __DIR__.'/../_core/helpers.php';

require_login();

session_start();

// Nota: Assicurati che questo sia il modo corretto per ottenere l'ID dell'utente.
// Se la tua funzione require_login() è ancora necessaria, puoi inserirla qui.
$user_id = $_SESSION['user']['id'] ?? null; 
if (!$user_id) { 
    http_response_code(401); 
    exit('not logged'); 
}

$db = db();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Legge e decodifica il corpo della richiesta JSON.
 * Esce con errore 400 se mancano chiavi richieste.
 */
function json_body($required = []) {
  $raw = file_get_contents('php://input'); 
  $data = json_decode($raw, true) ?: [];
  foreach($required as $k) {
      if(!array_key_exists($k, $data)) { 
          http_response_code(400); 
          exit("missing $k"); 
      }
  }
  return $data;
}

/**
 * Formatta una data in formato ISO 8601 (richiesto da FullCalendar).
 */
function iso($ts){ 
    return $ts ? date('c', strtotime($ts)) : null; 
}

/**
 * Ottiene un parametro dalla query string GET.
 * Esce con errore 400 se il parametro è obbligatorio e manca.
 */
function req($k, $must = false){ 
    $v = $_GET[$k] ?? null; 
    if ($must && !$v) { 
        http_response_code(400); 
        exit("missing $k"); 
    } 
    return $v; 
}

// === GESTIONE RICHIESTA GET (Lettura eventi) ===
if ($method === 'GET') {
  $start = req('start'); // Inizio dell'intervallo di date (formato ISO)
  $end = req('end');     // Fine dell'intervallo di date (formato ISO)

  $stmt = $db->prepare("SELECT id, title, description, starts_at, ends_at, all_day, color, rrule, reminders
                          FROM events WHERE user_id=? AND starts_at < ? AND (ends_at IS NULL OR ends_at >= ?)");
  $stmt->bind_param('iss', $user_id, $end, $start);
  $stmt->execute(); 
  $res = $stmt->get_result();

  $out = []; 
  while($r = $res->fetch_assoc()){
    $out[] = [
      'id'    => $r['id'],
      'title' => $r['title'],
      'start' => iso($r['starts_at']),
      'end'   => iso($r['ends_at']),
      'allDay'=> (bool)$r['all_day'],
      'color' => $r['color'],
      'extendedProps' => [
        'description' => $r['description'],
        'rrule'       => $r['rrule'],
        'reminders'   => json_decode($r['reminders'] ?: '[]', true)
      ]
    ];
  }

  header('Content-Type: application/json'); 
  echo json_encode($out); 
  exit;
}

// === GESTIONE RICHIESTA POST (Creazione evento) ===
if ($method === 'POST') {
  $in = json_body(['title','start']);
  $rem = isset($in['reminders']) ? json_encode($in['reminders']) : '[]';

  $stmt = $db->prepare("INSERT INTO events(user_id, title, description, starts_at, ends_at, all_day, color, rrule, reminders, source)
                         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, 'ui')");
  $stmt->bind_param('issssisss', $user_id, $in['title'], $in['description'], $in['start'], $in['end'], $in['allDay'], $in['color'], $in['rrule'], $rem);
  $stmt->execute();

  header('Content-Type: application/json'); 
  echo json_encode(['id' => $db->insert_id]); 
  exit;
}

// === GESTIONE RICHIESTA PATCH o PUT (Aggiornamento evento) ===
if ($method === 'PATCH' || $method === 'PUT') {
  $id = req('id', true);
  $in = json_body();
  $rem = array_key_exists('reminders', $in) ? json_encode($in['reminders']) : null;

  $stmt = $db->prepare("UPDATE events SET
      title = COALESCE(?, title), 
      description = COALESCE(?, description),
      starts_at = COALESCE(?, starts_at), 
      ends_at = COALESCE(?, ends_at),
      all_day = COALESCE(?, all_day), 
      color = COALESCE(?, color),
      rrule = COALESCE(?, rrule), 
      reminders = COALESCE(?, reminders)
    WHERE id = ? AND user_id = ?");
  $stmt->bind_param('ssssisssii', $in['title'], $in['description'], $in['start'], $in['end'], $in['allDay'], $in['color'], $in['rrule'], $rem, $id, $user_id);
  $stmt->execute(); 
  
  header('Content-Type: application/json'); 
  echo '{"ok":true}'; 
  exit;
}

// === GESTIONE RICHIESTA DELETE (Cancellazione evento) ===
if ($method === 'DELETE') {
  $id = req('id', true);

  $stmt = $db->prepare("DELETE FROM events WHERE id=? AND user_id=?");
  $stmt->bind_param('ii', $id, $user_id); 
  $stmt->execute();

  header('Content-Type: application/json'); 
  echo '{"ok":true}'; 
  exit;
}

// Se il metodo HTTP non è tra quelli gestiti, rispondi con un errore.
http_response_code(405); // Method Not Allowed
