const CACHE_PREFIX = 'pilates-vitalite-shell-';
const CACHE_NAME = `${CACHE_PREFIX}v20260803-2`;
const APP_SHELL = [
  './',
  './manifest.webmanifest',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './icons/maskable-512.png',
  './icons/icon.svg',
  './icons/maskable-icon.svg',
];
const SCOPE_URL = new URL(self.registration.scope);
const ALLOWED_NOTIFICATION_HOSTS = new Set([
  'pilatesvitalite.ca',
  'www.pilatesvitalite.ca',
  'pilatesvitalite.webactiondemo.ca',
  'www.pilatesvitalite.webactiondemo.ca',
]);

function safeNotificationTarget(rawUrl) {
  try {
    const target = new URL(typeof rawUrl === 'string' && rawUrl.trim() !== '' ? rawUrl : './', SCOPE_URL);
    const allowedHost = target.origin === self.location.origin || ALLOWED_NOTIFICATION_HOSTS.has(target.hostname.toLowerCase());

    if (target.protocol !== 'https:' || !allowedHost) {
      return new URL(SCOPE_URL);
    }

    return target;
  } catch (error) {
    return new URL(SCOPE_URL);
  }
}

function isInternalTarget(target) {
  return target.origin === SCOPE_URL.origin && target.pathname.startsWith(SCOPE_URL.pathname);
}

async function openNotificationTarget(target) {
  const windowClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

  if (isInternalTarget(target)) {
    const exactClient = windowClients.find((client) => client.url === target.href);
    if (exactClient && 'focus' in exactClient) {
      return exactClient.focus();
    }

    const appClient = windowClients.find((client) => {
      try {
        const clientUrl = new URL(client.url);
        return clientUrl.origin === SCOPE_URL.origin && clientUrl.pathname.startsWith(SCOPE_URL.pathname);
      } catch (error) {
        return false;
      }
    });

    if (appClient && 'navigate' in appClient) {
      const navigatedClient = await appClient.navigate(target.href);
      if (navigatedClient && 'focus' in navigatedClient) {
        return navigatedClient.focus();
      }
    }
  }

  return self.clients.openWindow(target.href);
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const requestUrl = new URL(event.request.url);

  if (event.request.method !== 'GET' || requestUrl.pathname.includes('/api/')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const responseClone = response.clone();
        if (response.ok && requestUrl.origin === self.location.origin) {
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
        }
        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match('./')))
  );
});

self.addEventListener('push', (event) => {
  let data = {};

  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = { title: 'Pilates Vitalité', body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'Pilates Vitalité';
  const target = safeNotificationTarget(data.url);
  const options = {
    body: data.body || 'Un nouveau contenu est disponible.',
    icon: './icons/icon.svg',
    badge: './icons/icon.svg',
    data: {
      url: target.href
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = safeNotificationTarget(event.notification.data?.url);

  event.waitUntil(
    openNotificationTarget(target).catch(() => self.clients.openWindow(SCOPE_URL.href))
  );
});
