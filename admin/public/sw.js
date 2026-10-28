/*
 * Phay's service worker: makes the admin installable and keeps it from a
 * white screen without internet. Only Next's hashed build files (and the
 * icons) are kept, cache-first: they never change under one name. Pages
 * and every API answer always come from the network, so one person's
 * business data is never stored on the device; a page that can't load
 * without internet shows /offline.html instead.
 */
const VERSION = "phay-v4";
const STATIC = `${VERSION}-static`;
const SHELL = ["/offline.html", "/icons/phay-192.png", "/icons/phay-512.png"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(STATIC)
      .then((cache) => cache.addAll(SHELL))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // A page: always the network; without it, the offline page.
  if (request.mode === "navigate") {
    event.respondWith(fetch(request).catch(() => caches.match("/offline.html")));
    return;
  }

  // Build files and icons: named by their content, kept once fetched.
  if (url.pathname.startsWith("/_next/static/") || url.pathname.startsWith("/icons/")) {
    event.respondWith(
      caches.match(request).then(
        (hit) =>
          hit ||
          fetch(request).then((response) => {
            if (response.ok) {
              const copy = response.clone();
              caches.open(STATIC).then((cache) => cache.put(request, copy));
            }
            return response;
          }),
      ),
    );
  }
  // Everything else (the admin's API routes, page data): the browser as usual.
});
