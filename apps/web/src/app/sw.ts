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
    // Everything else — use Serwist's sensible defaults (images,
    // static assets, fonts, next static chunks). Add BackgroundSync
    // for the two mutating scan endpoints so a check-in submitted
    // offline still lands the next time the SW gets network.
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
