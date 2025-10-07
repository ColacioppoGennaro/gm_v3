cat > public_html/gm_v3/api/push.php <<'PHP'
<?php
require_once __DIR__.'/../_core/helpers.php';
session_start();

// adjust according to your auth; here uso session
$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) { http_response_code(401); echo json_encode(['error'=>'not_logged']); exit; }

$db = db();
$fn = $_GET['fn'] ?? '';

if ($fn === 'subscribe') {
  $raw = file_get_contents('php://input'); $in = json_decode($raw, true) ?: [];
  $endpoint = $in['endpoint'] ?? null;
  $p256dh = $in['keys']['p256dh'] ?? null;
  $auth   = $in['keys']['auth'] ?? null;
  $device = $in['device'] ?? null;
  if (!$endpoint || !$p256dh || !$auth) { http_response_code(400); echo json_encode(['error'=>'bad_subscription']); exit; }
  $stmt = $db->prepare("INSERT INTO push_subscriptions(user_id,endpoint,p256dh,auth,device)
    VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), p256dh=VALUES(p256dh), auth=VALUES(auth), revoked_at=NULL");
  $stmt->bind_param('issss',$user_id,$endpoint,$p256dh,$auth,$device);
  $stmt->execute();
  echo json_encode(['ok'=>true]); exit;
}

if ($fn === 'unsubscribe') {
  $raw = file_get_contents('php://input'); $in = json_decode($raw, true) ?: [];
  $endpoint = $in['endpoint'] ?? null;
  if (!$endpoint) { http_response_code(400); echo json_encode(['error'=>'no_endpoint']); exit; }
  $stmt = $db->prepare("UPDATE push_subscriptions SET revoked_at=NOW() WHERE endpoint=? AND user_id=?");
  $stmt->bind_param('si',$endpoint,$user_id); $stmt->execute();
  echo json_encode(['ok'=>true]); exit;
}

if ($fn === 'test') {
  require_once __DIR__.'/../vendor/autoload.php';
  $auth = ['VAPID'=>['subject'=>env_get('PUSH_SUBJECT'), 'publicKey'=>env_get('VAPID_PUBLIC_KEY'), 'privateKey'=>env_get('VAPID_PRIVATE_KEY')]];
  $webPush = new Minishlink\WebPush\WebPush($auth);
  $res = $db->query("SELECT endpoint,p256dh,auth FROM push_subscriptions WHERE user_id={$user_id} AND revoked_at IS NULL");
  while($s=$res->fetch_assoc()){
    $sub = Minishlink\WebPush\Subscription::create(['endpoint'=>$s['endpoint'],'publicKey'=>$s['p256dh'],'authToken'=>$s['auth']]);
    $payload = json_encode(['title'=>'gm_v3','body'=>'Notifica di prova','url'=>'/gm_v3/#/calendar']);
    $webPush->queueNotification($sub,$payload,true);
  }
  foreach($webPush->flush() as $report){} // opzionale logging
  echo json_encode(['ok'=>true]); exit;
}

http_response_code(400);
echo json_encode(['error'=>'bad_fn']);
PHP
