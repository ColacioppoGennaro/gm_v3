<?php
require_once __DIR__ . '/../_core/db.php';

$resourceId = $_SERVER['HTTP_X_GOOG_RESOURCE_ID'] ?? null;
$state      = $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'] ?? null;
// Verifica resourceId nel tuo sync_state e poi fai una sync delta con syncToken salvato.
http_response_code(200);
