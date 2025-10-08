<?php
require __DIR__ . '/../vendor/autoload.php';

function makeGoogleClientForUser(?array $oauth = null): Google_Client {
  $client = new Google_Client();
  $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
  $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
  $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
  $client->setAccessType('offline');
  $client->setPrompt('consent');
  $client->setScopes([Google_Service_Calendar::CALENDAR]);

  if ($oauth && !empty($oauth['access_token'])) {
    $client->setAccessToken([
      'access_token' => $oauth['access_token'],
      'expires_in'   => max(1, strtotime($oauth['access_expires_at']) - time()),
      'created'      => time(),
      'refresh_token'=> $oauth['refresh_token'] ?? null
    ]);
  } elseif ($oauth && !empty($oauth['refresh_token'])) {
    $client->refreshToken($oauth['refresh_token']);
  }
  return $client;
}
