import axios from 'axios';

import { useAuthStore } from '@/lib/stores/auth-store';

/**
 * Resolve the API base URL. Priority order:
 *   1. NEXT_PUBLIC_API_URL if set to a non-empty string that starts with
 *      `http` (absolute) or `/` (relative to origin).
 *   2. `/api` — safe default that works with the prod nginx reverse
 *      proxy: `attendees.taqatgaza.com/api/…` → nginx → api container.
 *   3. `http://localhost:8000/api` — dev fallback for `next dev` on a
 *      standalone laptop where the browser can reach Laravel directly.
 *
 * Falling all the way through to a bad value (empty string, `undefined`)
 * would silently strip the `/api` prefix from every request, so a
 * `POST /auth/login` lands on Next.js's own routing → 404. The `/api`
 * default catches that class of bug on any deploy that forgot to bake
 * the env var in.
 */
function resolveApiBaseUrl(): string {
  const raw = process.env.NEXT_PUBLIC_API_URL;
  if (typeof raw === 'string' && raw.trim() !== '') {
    const trimmed = raw.trim();
    if (trimmed.startsWith('http') || trimmed.startsWith('/')) {
      return trimmed;
    }
  }
  // On the browser we can safely default to a same-origin relative
  // prefix; on the server (SSR/build) fall through to a dev URL.
  if (typeof window !== 'undefined') return '/api';
  return 'http://localhost:8000/api';
}

/**
 * withCredentials is REQUIRED for Sanctum SPA (stateful) mode: the
 * browser must attach the session cookie + XSRF-TOKEN cookie on every
 * request, and axios reads XSRF-TOKEN off the cookie jar and mirrors
 * it into the X-XSRF-TOKEN header automatically — that's the CSRF
 * pair Laravel's ValidateCsrfToken middleware checks. Without this
 * flag, the browser sends no cookies and every state-changing call
 * 419s.
 */
export const apiClient = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: { Accept: 'application/json' },
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
});

if (typeof window !== 'undefined') {
  // One-line boot log so a mis-baked NEXT_PUBLIC_API_URL is immediately
  // visible in the browser console — the previous silent stripping of
  // the /api prefix produced 404s with no diagnostic.
  // eslint-disable-next-line no-console
  console.info('[taqat] apiClient baseURL =', apiClient.defaults.baseURL);
}

apiClient.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401 && typeof window !== 'undefined') {
      // Full logout — clears the persisted zustand user AND
      // disconnects Echo — instead of only wiping the token key
      // (which used to leave `user` hydrated in the store).
      useAuthStore.getState().logout();
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

/**
 * Sanctum's CSRF primer. Call this ONCE before the first state-changing
 * request in a session (login, form submit, anything that ships a
 * X-XSRF-TOKEN header). The response sets an `XSRF-TOKEN` cookie that
 * axios then echoes back on every subsequent request as
 * `X-XSRF-TOKEN` — the pair Laravel's ValidateCsrfToken middleware
 * checks. Safe to call repeatedly; each call rotates the token.
 *
 * The route is registered by Sanctum at `/sanctum/csrf-cookie` and
 * lives OUTSIDE the `/api` prefix, so we hit the site origin directly.
 */
export async function primeCsrfCookie(): Promise<void> {
  if (typeof window === 'undefined') return;
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}
