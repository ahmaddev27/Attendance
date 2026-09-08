/// <reference lib="webworker" />
/**
 * TAQAT PWA Service Worker (Serwist).
 *
 * Two goals, in order of importance:
 *   1. Make the /scan/[qrToken] flow work with no network — an employee
 *      arriving at a QR device on a flaky lift wifi should still see the
 *      camera screen and be able to check in (the POST queues via
 *      Background Sync and drains when connection is back).
 *   2. Keep the rest of the SPA fast on subsequent visits by caching the
 *      static build + fonts + API GETs with sensible strategies.
 *
 * Runtime caches live under distinct names so a schema change bumps only
 * the affected cache — no global cache-buster needed on deploys.
 *
 * CACHING POLICY (2026-09):
 *   Users kept seeing broken pages after deploys because the stock
 *   `defaultCache` uses StaleWhileRevalidate for HTML + JS chunks — a
 *   fresh visitor got the OLD hashed shell that referenced OLD chunk
 *   filenames, then Next.js's runtime hit 404 on those and rendered
 *   the client-exception overlay. To fix, we serve HTML documents and
 *   Next's runtime chunks with NetworkFirst (small timeout, offline
 *   fallback intact) so a fresh deploy is visible on the very next
 *   navigation instead of after a stale-then-revalidate cycle.
 */

import { defaultCache } from '@serwist/next/worker';
import type { PrecacheEntry, SerwistGlobalConfig } from 'serwist';
import { NetworkFirst, Serwist, StaleWhileRevalidate } from 'serwist';

declare global {
  interface WorkerGlobalScope extends SerwistGlobalConfig {
    __SW_MANIFEST: (PrecacheEntry | string)[] | undefined;
  }
}

declare const self: ServiceWorkerGlobalScope;

const serwist = new Serwist({
  precacheEntries: self.__SW_MANIFEST,
  skipWaiting: true,
  clientsClaim: true,
  navigationPreload: true,
  runtimeCaching: [
    // /scan/* — offline-capable check-in flow. NetworkFirst so a live
    // device_info fetch is preferred, but we fall back to the cached
    // shell if the network drops. The camera + form component code
    // is precached (bundled Next.js chunk), so the page is fully
    // interactive without network.
    {
      matcher: /^\/scan\//,
      handler: new NetworkFirst({
        cacheName: 'taqat-scan',
        networkTimeoutSeconds: 3,
      }),
    },
    // Public GETs from the API (device_info) — brief cache so a re-open
    // within the same shift is instant.
    {
      matcher: /^\/api\/scan\/device\//,
      handler: new StaleWhileRevalidate({
        cacheName: 'taqat-scan-device',
      }),
    },
    // Document navigations — NetworkFirst with a 2s timeout so a fresh
    // deploy shows up immediately on the next request, while an offline
    // user still gets the last-known-good HTML from the cache (and the
    // /offline fallback below when even that misses).
    {
      matcher: ({ request }) => request.destination === 'document',
      handler: new NetworkFirst({
        cacheName: 'taqat-pages',
        networkTimeoutSeconds: 2,
      }),
    },
    // Next.js runtime bundles (`/_next/static/chunks/**`, `/_next/data`,
    // `/sw.js`, etc.) — the chunk filenames are content-hashed, so a
    // network request for an old hash 404s after a deploy. Serve them
    // NetworkFirst so the fresh shell fetches its matching chunks, not
    // the stale ones the SW cached last visit.
    {
      matcher: ({ url }) => url.pathname.startsWith('/_next/'),
      handler: new NetworkFirst({
        cacheName: 'taqat-next',
        networkTimeoutSeconds: 3,
      }),
    },
    // Everything else — use Serwist's sensible defaults (images,
    // static assets, fonts). The overrides above land first because
    // Serwist runs the runtimeCaching entries in order.
    ...defaultCache,
  ],
  fallbacks: {
    entries: [
      {
        url: '/offline',
        matcher({ request }) {
          return request.destination === 'document';
        },
      },
    ],
  },
});

serwist.addEventListeners();
