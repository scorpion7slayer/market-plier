// Service Worker — Web Push Notifications pour Market Plier

self.addEventListener('install', function(event) {
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function(event) {
  var data = { title: 'Market Plier', body: '', icon: '/market-plier/assets/images/logo.svg', link: null };

  if (event.data) {
    try {
      var payload = event.data.json();
      data.title = payload.title || data.title;
      data.body = payload.body || data.body;
      data.link = payload.link || null;
      if (payload.icon) data.icon = payload.icon;
    } catch (e) {
      data.body = event.data.text();
    }
  }

  var options = {
    body: data.body,
    icon: data.icon,
    badge: '/market-plier/assets/images/logo.svg',
    data: { link: data.link },
    vibrate: [200, 100, 200]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  var link = event.notification.data && event.notification.data.link
    ? new URL(event.notification.data.link, self.location.origin).href
    : self.location.origin + '/market-plier/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clients) {
      for (var i = 0; i < clients.length; i++) {
        if (clients[i].url === link && 'focus' in clients[i]) {
          return clients[i].focus();
        }
      }
      return self.clients.openWindow(link);
    })
  );
});
