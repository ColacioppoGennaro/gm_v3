cat > public_html/gm_v3/assets/js/push.js <<'JS'
export async function enablePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    alert('Push non supportate in questo browser');
    return false;
  }

  const reg = await navigator.serviceWorker.register('/gm_v3/assets/service-worker.js');
  await navigator.serviceWorker.ready;

  const perm = await Notification.requestPermission();
  if (perm !== 'granted') { alert('Permesso notifiche negato'); return false; }

  const vapid = document.querySelector('meta[name="vapid-key"]')?.content || '';
  const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapid)
  });

  const res = await fetch('/gm_v3/api/push.php?fn=subscribe', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(sub)
  });
  if (!res.ok) { alert('Subscribe fallita'); return false; }
  alert('Subscribe ok');
  return true;
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
  return outputArray;
}
JS
