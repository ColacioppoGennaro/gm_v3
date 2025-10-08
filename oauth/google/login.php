<?php
require_once __DIR__ . '/../../_core/google_client.php';
session_start();
$client = makeGoogleClientForUser();
header('Location: '.$client->createAuthUrl());
exit;
