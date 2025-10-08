cat > push_test.php <<'PHP'
<?php require __DIR__ . '/_core/bootstrap.php'; ?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>gm_v3 · Test Push</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="vapid-key" content="<?= htmlspecialchars(env_get('VAPID_PUBLIC_KEY','')) ?>">
  <style>
    body{font-family:system-ui,Arial,sans-serif;background:#0f1220;color:#e6e6f0;margin:0;padding:24px}
    .card{max-width:680px;margin:0 auto;background:#171a2b;border:1px solid #23253b;border-radius:14px;padding:20px}
    h1{margin:0 0 12px;font-size:22px}
    button{cursor:pointer;border:0;border-radius:10px;padding:12px 16px;margin:6px 8px 6px 0;background:#6c5ce7;color:white;font-weight:600}
    button:disabled{opacity:.5;cursor:not-allowed}
    code,pre{background:#11152a;color:#d7d7ff;padding:10px;border-radius:8px;display:block;white-space:pre-wrap}
    .ok{color:#00d18f}.err{color:#ff6b6b}
  </style>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
</head>
<body>
  <div class="card">
    <h1>Test notifiche push</h1>
    <p>1) Assicurati di essere <b>loggato</b> nell’app.<br>2) Clicca “Abilita notifiche”.<br>3) Clicca “Invia notifica di prova”.</p>

    <button id="btnEnable">🔔 Abilita notifiche</button>
    <button id="btnTest" disabled>🧪 Invia notifica di prova</button>

    <h3>Esito</h3>
    <pre id="log"></pre>
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
    if(perm!=='granted') return;

    // subscribe
    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapid)
    });
    log('Subscription creata', 'ok');

    const res = await fetch('/gm_v3/api/push.php?fn=subscribe', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(sub)
    });
    const js = await res.json();
    if(js.ok){ log('Subscription salvata lato server', 'ok'); document.getElementById('btnTest').disabled=false; }
    else { log('Errore salvataggio subscription: '+JSON.stringify(js), 'err'); }
  }catch(e){ log('Errore: '+e.message, 'err'); }
}

async function testPush(){
  try{
    const res = await fetch('/gm_v3/api/push.php?fn=test', { credentials:'include' });
    const js = await res.json();
    if(js.ok){ log('Richiesta invio test push inviata', 'ok'); }
    else { log('Errore test: '+JSON.stringify(js), 'err'); }
  }catch(e){ log('Errore: '+e.message, 'err'); }
}

document.getElementById('btnEnable').addEventListener('click', enablePush);
document.getElementById('btnTest').addEventListener('click', testPush);
</script>
</body>
</html>
PHP
