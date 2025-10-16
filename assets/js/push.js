export async function enablePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
  
  const reg = await navigator.serviceWorker.register('/gm_v3/assets/service-worker.js?v=2');
  await navigator.serviceWorker.ready;

  const perm = await Notification.requestPermission();
  if (perm !== 'granted') return false;

  const vapid = document.querySelector('meta[name="vapid-key"]')?.content;
  const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapid)
  });

  const res = await fetch('/gm_v3/api/push.php?fn=subscribe', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(sub)
  });

  return res.ok;
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}
