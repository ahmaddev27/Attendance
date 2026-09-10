'use client';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { apiClient } from '@/lib/api/client';

/**
 * Laravel Echo instance wired to our Reverb server.
 *
 * Config comes from four NEXT_PUBLIC_* env vars baked into the client
 * bundle at build time (see infra/docker/web/Dockerfile):
 *   NEXT_PUBLIC_REVERB_APP_KEY  — matches REVERB_APP_KEY in the API's .env
 *   NEXT_PUBLIC_REVERB_HOST     — public hostname the browser can reach
 *                                 (e.g. attendees.taqatgaza.com behind
 *                                 the Apache reverse-proxy that forwards
 *                                 /apps and /broadcasting to the reverb
 *                                 Docker container's 8080)
 *   NEXT_PUBLIC_REVERB_PORT     — 443 for prod, 8182 (docker host bind) for local
 *   NEXT_PUBLIC_REVERB_SCHEME   — 'https' or 'http'; drives wss vs ws
 *
 * A single lazy singleton — the module can be imported from React trees
 * without spinning up a new connection per component. `getEcho()` returns
 * null on the SSR pass so `import 'echo'` at module scope doesn't crash
 * `window`-less server rendering.
 */

// Pusher must be on window for laravel-echo to find it — this is how the
// upstream package plugs the pusher client in without a dependency.
declare global {
  interface Window {
    Pusher?: typeof Pusher;
    Echo?: unknown;
  }
}

let echoInstance: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
  if (typeof window === 'undefined') return null;
  if (echoInstance) return echoInstance;

  window.Pusher = Pusher;

  const host = process.env.NEXT_PUBLIC_REVERB_HOST || window.location.hostname;
  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME || 'https';
  const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT || (scheme === 'https' ? 443 : 80));
  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY || '';

  if (!key) {
    console.warn('[echo] NEXT_PUBLIC_REVERB_APP_KEY missing — realtime disabled');
    return null;
  }

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // Custom authorizer so channel auth goes through the shared
    // apiClient — that gets us `withCredentials: true` and the
    // X-XSRF-TOKEN header for free, which is what Sanctum's stateful
    // (session-cookie) mode needs. The previous bearer-header path is
    // gone: XSS can no longer read the credential, so channel auth
    // relies on the same httpOnly cookie the rest of the app uses.
    authorizer: (channel) => ({
      authorize: (
        socketId: string,
        callback: (error: Error | null, data: { auth: string; channel_data?: string; shared_secret?: string } | null) => void,
      ) => {
        apiClient
          .post<{ auth: string; channel_data?: string; shared_secret?: string }>(
            '/broadcasting/auth',
            {
              socket_id: socketId,
              channel_name: channel.name,
            },
          )
          .then((r) => callback(null, r.data))
          .catch((err) => callback(err instanceof Error ? err : new Error(String(err)), null));
      },
    }),
  });

  // Expose on window for quick devtools inspection — safe: the instance
  // itself lives in module scope and is the source of truth.
  window.Echo = echoInstance;

  return echoInstance;
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
