import axios from 'axios';

/**
 * Unauthenticated axios instance for the public kiosk scan flow
 * (src/app/(public)/scan/[qrToken]). Deliberately has no auth interceptor —
 * the scan endpoints are public and must never send (or expect) a bearer
 * token or trigger the authenticated client's 401 -> /login redirect.
 */
export const publicApiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
});
