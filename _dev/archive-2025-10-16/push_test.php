<?php
// Debug visibile (solo per questa pagina di test)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prendiamo la VAPID_PUBLIC_KEY in modo robusto
$vapid = '';
try {
  require __DIR__ . '/_core/bootstrap.php'; // carica env_get() se disponibile
  if (function_exists('env_get')) {
    $vapid = (string) env_get('VAPID_PUBLIC_KEY', '');
  }
} catch (Throwable $e) {
  // ignora, useremo i fallback sotto
}
if ($vapid === '') { $vapid = getenv('VAPID_PUBLIC_KEY') ?: ''; }
if ($vapid === '' && file_exists(__DIR__.'/.env')) {
  $lines = @file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  foreach ($lines as $line) {
    if (strpos($line, 'VAPID_PUBLIC_KEY=') === 0) {
      $vapid = trim(substr($line, strlen('VAPID_PUBLIC_KEY=')));
      break;
    }
  }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <title>gm_v3 · Test Push</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Passo la tua VAPID dalla PHP al JS -->
  <meta name="vapid-key" content="<?php echo htmlspecialchars($vapid, ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    :root{--bg:#0f1220;--card:#171a2b;--muted:#23253b;--ok:#00d18f;--err:#ff6b6b;--warn:#ffd166}
    body{font-family:system-ui,Arial,sans-serif;background:var(--bg);color:#e6e6f0;margin:0;padding:24px}
    .card{max-width:720px;margin:0 auto;background:var(--card);border:1px solid var(--muted);border-radius:14px;padding:20px}
    h1{margin:0 0 12px;font-size:22px}
    button{cursor:pointer;border:0;border-radius:10px;padding:12px 16px;margin:6px 8px 6px 0;background:#6c5ce7;color:white;font-weight:600}
    button:disabled{opacity:.5;cursor:not-allowed}
    pre{background:#11152a;color:#d7d7ff;padding:10px;border-radius:8px;white-space:pre-wrap;min-height:120px}
    .ok{color:var(--ok)} .err{color:var(--err)} .warn{color:var(--warn)}
    .hint{opacity:.85;font-size:13px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Test notifiche push</h1>
    <p>1) Assicurati di essere <b>loggato</b> nell’app.<br>2) Clicca “Abilita notifiche”.<br>3) Clicca “Invia notifica di prova”.</p>
    <?php if (!$vapid): ?>
      <p class="warn"><b>Attenzione:</b> VAPID_PUBLIC_KEY non trovata. La subscribe fallirà finché non la imposti nel <code>.env</code>.</p>
    <?php endif; ?>

    <button id="btnEnable">🔔 Abilita notifiche</button>
    <button id="btnTest" disabled>🧪 Invia notifica di prova</button>

    <h3>Esito</h3>
    <pre id="log"></pre>
    <p class="hint">Se non arriva la notifica: controlla HTTPS, login, service worker a <code>/gm_v3/assets/service-worker.js</code>, tabella <code>push_subscriptions</code> nel DB.</p>
  </div>

<script type="module">
const log = (m, cls='')=>{
  const el=document.getElementById('log');
  el.innerHTML += (cls?`<span class="${cls}">`:'') + m + (cls?'</span>':'') + "\n";
};

const vapid = document.querySelector('meta[name="vapid-key"]')?.content || '';

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
  return outputArray;
}

async function enablePush(){
  try{
    if (!('serviceWorker' in navigator)) { log('Service worker non supportato', 'err'); return; }
    if (!('PushManager' in window)) { log('Push API non supportata', 'err'); return; }

    // registra SW
    const reg = await navigator.serviceWorker.register('/gm_v3/assets/service-worker.js');
    await navigator.serviceWorker.ready;
    log('Service worker registrato', 'ok');

    // permesso
    const perm = await Notification.requestPermission();
    log('Permission: '+perm);
    if(perm!=='granted') { log('Permesso negato dal browser', 'err'); return; }

    if (!vapid) { log('VAPID_PUBLIC_KEY vuota: impossibile creare la subscription', 'err'); return; }

    // subscription nel browser
    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapid)
    });
    log('Subscription creata nel browser', 'ok');

    // salvataggio sul server
    const res = await fetch('/gm_v3/api/push.php?fn=subscribe', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(sub),
      credentials:'include'
    });
    let js = {};
    try { js = await res.json(); } catch(e){ js = {}; }
    if(js.ok){
      log('Subscription salvata lato server', 'ok');
      document.getElementById('btnTest').disabled = false;
    } else {
      log('Errore salvataggio subscription: '+JSON.stringify(js), 'err');
    }
  }catch(e){
    log('Errore: '+(e?.message||String(e)), 'err');
  }
}

async function testPush(){
  try{
    const res = await fetch('/gm_v3/api/push.php?fn=test', { credentials:'include' });
    let js = {};
    try { js = await res.json(); } catch(e){ js = {}; }
    if(js.ok){ log('Richiesta invio test push inviata. Controlla la notifica.', 'ok'); }
    else { log('Errore test: '+JSON.stringify(js), 'err'); }
  }catch(e){ log('Errore: '+(e?.message||String(e)), 'err'); }
}

document.getElementById('btnEnable').addEventListener('click', enablePush);
document.getElementById('btnTest').addEventListener('click', testPush);
</script>
</body>
</html>
