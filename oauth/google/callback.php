<?php
require_once __DIR__ . '/../../_core/google_client.php';
require_once __DIR__ . '/../../_core/db.php';
session_start();
$user_id = $_SESSION['user_id'] ?? null;

$client = makeGoogleClientForUser();
$token = $client->fetchAccessTokenWithAuthCode($_GET['code'] ?? '');
if (isset($token['error'])) { http_response_code(400); exit('OAuth error'); }

$access = $client->getAccessToken();
$refresh = $client->getRefreshToken();

$stmt = $db->prepare("
  INSERT INTO oauth_accounts (user_id, provider, refresh_token, scope, access_token, access_expires_at)
  VALUES (?, 'google', ?, ?, ?, FROM_UNIXTIME(?))
  ON DUPLICATE KEY UPDATE refresh_token=VALUES(refresh_token), scope=VALUES(scope),
                          access_token=VALUES(access_token), access_expires_at=VALUES(access_expires_at)");
$stmt->execute([
  $user_id,
  $refresh ?? getExistingRefresh($db,$user_id),
  implode(' ', $client->getScopes()),
  $access['access_token'],
  time() + ($access['expires_in'] ?? 3500),
]);

header('Location: '.getenv('APP_BASE_URL').'/settings?google=connected');
exit;

function getExistingRefresh($db,$uid){
  $q=$db->prepare("SELECT refresh_token FROM oauth_accounts WHERE user_id=? AND provider='google'");
  $q->execute([$uid]);
  return $q->fetchColumn();
}
