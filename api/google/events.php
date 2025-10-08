<?php
require_once __DIR__ . '/../../_core/google_client.php';
require_once __DIR__ . '/../../_core/db.php';
session_start();
$user_id = $_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];
$calendarId = $_GET['calendarId'] ?? $_POST['calendarId'] ?? null;
if(!$calendarId){ http_response_code(400); exit('calendarId required'); }

$oauth = getOauth($db,$user_id);
$client = makeGoogleClientForUser($oauth);
$service = new Google_Service_Calendar($client);

switch ($method) {
  case 'GET':
    $timeMin = $_GET['start'] ?? null;
    $timeMax = $_GET['end'] ?? null;
    $params = ['singleEvents'=>true,'orderBy'=>'startTime'];
    if($timeMin) $params['timeMin']=$timeMin;
    if($timeMax) $params['timeMax']=$timeMax;

    $events = $service->events->listEvents($calendarId, $params);
    $out=[];
    foreach($events->getItems() as $e){
      $out[] = [
        'id'    => $e->getId(),
        'title' => $e->getSummary() ?: '(senza titolo)',
        'start' => $e->getStart()->getDateTime() ?: $e->getStart()->getDate(),
        'end'   => $e->getEnd()->getDateTime() ?: $e->getEnd()->getDate(),
        'allDay'=> (bool)$e->getStart()->getDate(),
      ];
    }
    header('Content-Type: application/json'); echo json_encode($out); break;

  case 'POST':
    $payload = json_decode(file_get_contents('php://input'), true);
    $ev = new Google_Service_Calendar_Event([
      'summary' => $payload['title'] ?? '',
      'description' => $payload['description'] ?? '',
      'start' => ['dateTime' => $payload['start']],
      'end'   => ['dateTime' => $payload['end']],
      'attendees' => array_map(fn($m)=>['email'=>$m], $payload['attendees'] ?? []),
      'recurrence'=> !empty($payload['rrule']) ? [$payload['rrule']] : [],
      'reminders' => [
        'useDefault'=> false,
        'overrides'=> array_map(fn($m)=>['method'=>$m['method'],'minutes'=>$m['minutes']], $payload['reminders'] ?? [])
      ],
    ]);
    $created = $service->events->insert($calendarId,$ev);
    header('Content-Type: application/json'); echo json_encode(['id'=>$created->getId()]); break;

  case 'PATCH':
    $id = $_GET['id'] ?? null; if(!$id){ http_response_code(400); exit('id required'); }
    $payload = json_decode(file_get_contents('php://input'), true);
    $ev = $service->events->get($calendarId,$id);
    if(isset($payload['title'])) $ev->setSummary($payload['title']);
    if(isset($payload['description'])) $ev->setDescription($payload['description']);
    if(isset($payload['start'])) $ev->setStart(new Google_Service_Calendar_EventDateTime(['dateTime'=>$payload['start']]));
    if(isset($payload['end']))   $ev->setEnd(new Google_Service_Calendar_EventDateTime(['dateTime'=>$payload['end']]));
    $service->events->update($calendarId,$id,$ev);
    http_response_code(204); break;

  case 'DELETE':
    $id = $_GET['id'] ?? null; if(!$id){ http_response_code(400); exit('id required'); }
    $service->events->delete($calendarId,$id);
    http_response_code(204); break;
}
