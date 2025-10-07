// assets/service-worker.js

// INSTALL: caching base + attivazione immediata
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open('gmv3').then(c => c.addAll(['/gm_v3/']))
  );
  self.skipWaiting();
});

// ACTIVATE: prende subito il controllo
self.addEventListener('activate', e => self.clients.claim());

// FETCH: serve dal cache o fa fetch online
self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(r => r || fetch(e.request))
  );
});

// PUSH: riceve notifiche
self.addEventListener('push', event => {
  let data = {};
  try { data = event.data.json(); }
  catch(e) { data = { title:'gm_v3', body: event.data && event.data.text() }; }

  const title = data.title || 'Promemoria';
  const options = {
    body: data.body || '',
    data: { url: data.url || '/gm_v3/#/calendar' },
    icon: '/gm_v3/assets/icons/icon-192.png',
    badge: '/gm_v3/assets/icons/badge.png'
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

// CLICK: apre o attiva la finestra
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const goto = (event.notification.data && event.notification.data.url) || '/gm_v3/';
  event.waitUntil(
    clients.matchAll({ type:'window', includeUncontrolled:true }).then(list => {
      for (const c of list) if (c.url.includes('/gm_v3/')) { c.focus(); return; }
      return clients.openWindow(goto);
    })
  );
});
