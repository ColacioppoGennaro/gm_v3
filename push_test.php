<?php
// === DEBUG SEMPRE ON QUI, per capire subito se c’è qualcosa che non va ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Proviamo a prendere la VAPID_PUBLIC_KEY in modo robusto:
// 1) Preferisco caricare bootstrap + usare env_get()
// 2) Se fallisce, ripiego su getenv() o su .env letto a mano
$vapid = '';

try {
  require __DIR__ . '/_core/bootstrap.php'; // se esiste e non esplode
  if (function_exists('env_get')) {
    $vapid = (string) env_get('VAPID_PUBLIC_KEY', '');
  }
} catch (Throwable $e) {
  // Ignoro, provo fallback
}

if ($vapid === '') {
  // fallback 1: getenv (se bootstrap ha già caricato .env)
  $vapid = getenv('VAPID_PUBLIC_KEY') ?: '';
}

if ($vapid === '' && file_exists(__DIR__.'/.env')) {
  // fallback 2: leggo .env a mano (molto grezzo ma efficace per una pagina di test)
  $lines = @file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  foreach ($lines as $line) {
    if (strpos($line, 'VAPID_PUBLIC_KEY=') === 0) {
      $vapid = trim(substr($line, strlen('VAPID_PUBLIC_KEY=')));
      break;
    }
  }
}

// Se proprio è vuota, lo segnalo in pagina.
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>gm_v3 · Test Push</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- niente shorthand <?= ... ?> per evitare problemi di short_open_tag -->
  <meta name="vapid-key" content="<?php echo htmlspecialchars($vapid, ENT_QUOTES, 'UTF-8'); ?>">

  <style>
    body{font-family:system-ui,Arial,sans-serif;background:#0f1220;color:#e6e6f0;margin:0;padding:24px}
    .card{max-width:720px;margin:0 auto;background:#171a2b;border:1px solid #23253b;border-radius:14px;padding:20px}
    h1{margin:0 0 12px;font-size:22px}
    button{cursor:pointer;border:0;border-radius:10px;padding:12px 16px;margin:6px 8px 6px 0;background:#6c5ce7;color:white;font-weight:600}
    button:disabled{opacity:.5;cursor:not-allowed}
    pre{background:#11152a;color:#d7d7ff;padding:10px;border-radius:8px;white-space:pre-wrap}
    .ok{color:#00d18f}.err{color:#ff6b6b}.warn{color:#ffd166}
    .hint{opacity:.85;font-size:13px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Test notifiche push</h1>
    <p>1) Assicurati di essere <b>loggato</b> nell’app.<br>2) Clicca “Abilita notifiche”.<br>3) Clicca “Invia notifica di prova”.</p>

    <p class="hint">Se vedi pagina bianca, qui sopra avresti errori: ora sono visibili perché abbiamo il debug attivo.</p>

    <?php if ($vapid === ''): ?>
      <p class="warn"><b>Attenzione:</b> <code>VAPID_PUBLIC_KEY</code> non trovata. Controlla il tuo <code>.env</code>.
        Continua pure: la pagina funziona ma la subscribe fallirà finché non la inserisci.</p>
    <?php endif; ?>

    <button id="btnEnable">🔔 Abilita notifiche</button>
    <button id="btnTest" disabled>🧪 Invia notifica di prova</button>

    <h3>Esito</h3>
    <pre id="log"></pre>
  </div>

<script type="module">
const log = (m, cls='')=>{
  const el = document.getElementById('log');
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

    // Registra SW (path assoluto alla tua installazione)
    const reg = await navigator.serviceWorker.register('/gm_v3/assets/service-worker.js');
    await navigator.serviceWorker.ready;
    log('Service worker registrato', 'ok');

    // Chiedi permessi
    const perm = await Notification.requestPermission();
    log('Permission: '+perm);
    if(perm!=='granted') { log('Permesso negato: interrompo.', 'err'); return; }

    if (!vapid) { log('VAPID_PUBLIC_KEY vuota: impossibile creare la subscription', 'err'); return; }

    // Crea subscription
    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapid)
    });
    log('Subscription creata (browser)', 'ok');

    // Salva lato server
    const res = await fetch('/gm_v3/api/push.php?fn=subscribe', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(sub), credentials:'include'
    });

    let js = {};
    try { js = await res.json(); } catch(e) { js = {ok:false, parse:'fail'}; }

    if(js && js.ok){
      log('Subscription salvata lato server', 'ok');
      document.getElementById('btnTest').disabled = false;
    } else {
      log('Errore salvataggio subscription: '+JSON.stringify(js||{}), 'err');
    }
  }catch(e){
    log('Errore: '+(e && e.message ? e.message : String(e)), 'err');
  }
}

async function testPush(){
  try{
    const res = await fetch('/gm_v3/api/push.php?fn=test', { credentials:'include' });
    let js = {};
    try { js = await res.json(); } catch(e) { js = {ok:false, parse:'fail'}; }
    if(js && js.ok){ log('Richiesta invio test push inviata (controlla la notifica)', 'ok'); }
    else { log('Errore test: '+JSON.stringify(js||{}), 'err'); }
  }catch(e){ log('Errore: '+(e && e.message ? e.message : String(e)), 'err'); }
}

document.getElementById('btnEnable').addEventListener('click', enablePush);
document.getElementById('btnTest').addEventListener('click', testPush);
</script>
</body>
</html>
