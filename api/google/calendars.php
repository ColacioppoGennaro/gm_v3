<?php
require_once __DIR__ . '/../../_core/google_client.php';
require_once __DIR__ . '/../../_core/db.php';
session_start();
$user_id = $_SESSION['user_id'];

$oauth = getOauth($db,$user_id);
$client = makeGoogleClientForUser($oauth);
$service = new Google_Service_Calendar($client);

$list = $service->calendarList->listCalendarList();
$out = [];
foreach ($list->getItems() as $c) {
  $out[] = [
    'id' => $c->getId(),
    'summary' => $c->getSummary(),
    'primary' => $c->getPrimary(),
    'color' => $c->getBackgroundColor()
  ];
}
header('Content-Type: application/json');
echo json_encode($out);

function getOauth($db,$uid){
  $q=$db->prepare("SELECT * FROM oauth_accounts WHERE user_id=? AND provider='google'");
  $q->execute([$uid]);
  return $q->fetch(PDO::FETCH_ASSOC);
}
