import { create } from 'zustand';
import { persist } from 'zustand/middleware';

import { apiClient } from '@/lib/api/client';
import { disconnectEcho } from '@/lib/echo';

// Guards against the axios 401 interceptor invoking logout() while our
// own POST /auth/logout is in flight — a stale token would 401 that
// call, the interceptor would re-enter logout(), and we'd have a POST
// loop until the page finishes navigating away.
let logoutInFlight = false;

export type User = {
  id: number;
  // Linked employee row's id (nullable — legacy users can be
  // provisioned without an Employee). Needed so the request-detail
  // dialog can compare a workflow step's `approver_ref` (an EMPLOYEE
  // id, not a USER id) against the current viewer.
  employee_id: number | null;
  employee_number: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
};

type AuthState = {
  user: User | null;
  token: string | null;
  setAuth: (user: User, token: string) => void;
  logout: () => Promise<void>;
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      // Token lives in the zustand-persisted `taqat-auth` key only —
      // we used to also mirror it to a duplicate `taqat_token`
      // localStorage entry, which was easy to leave stale on logout.
      setAuth: (user, token) => set({ user, token }),
      logout: async () => {
        // Revoke the Sanctum token on the server so it can't be replayed;
        // do this BEFORE we clear local state, since the axios request
        // interceptor reads the token straight from this store. Any
        // network/500 error is swallowed — we still want to clear the
        // client-side session even if the server round-trip failed.
        if (get().token && !logoutInFlight) {
          logoutInFlight = true;
          try {
            await apiClient.post('/auth/logout');
          } catch {
            // Deliberate no-op — see comment above.
          } finally {
            logoutInFlight = false;
          }
        }

        // Tear down the Echo singleton first so its captured bearer
        // (and any open websocket subscribed as user A) is closed
        // BEFORE we clear state — otherwise a re-init after user B
        // signs in on the same tab would already be racing against a
        // still-open channel authorized as A.
        disconnectEcho();
        set({ user: null, token: null });
        if (typeof localStorage !== 'undefined') {
          // Belt-and-braces: the persist middleware's own write above
          // leaves `{ user: null, token: null }` in the key, but
          // removing the key entirely also cleans up any leftover
          // fields from older schema versions on the client.
          localStorage.removeItem('taqat-auth');
          // Clean up the legacy duplicate key for users upgrading
          // from before the mirror was removed.
          localStorage.removeItem('taqat_token');
        }
        // The QueryClient lives inside the provider tree and can't be
        // reached from this zustand store directly. Broadcast an event
        // that the top-level QueryProvider listens for and clears its
        // cache on — otherwise signing in as a different user on the
        // same tab would show user A's cached data until it turned
        // stale. See fix #6 in the Wave D audit.
        emitAuthReset();
      },
    }),
    { name: 'taqat-auth' }
  )
);

/**
 * Broadcasts an auth-boundary event on both login and logout so the
 * top-level QueryProvider can react by clearing its cache. Guarded
 * for SSR since window isn't available during React server render.
 */
export function emitAuthReset(): void {
  if (typeof window === 'undefined') return;
  window.dispatchEvent(new Event('taqat:auth-reset'));
}

/** Any of the "administrative" roles that route through /dashboard, not /home. */
const ADMIN_ROLES = new Set(['super-admin', 'management', 'department-manager', 'team-leader']);

/**
 * Safely coerce a value that SHOULD be a string array into one.
 *
 * Every consumer of user.roles / user.permissions here assumed the
 * backend always returns arrays. That's true for a fresh Laravel
 * UserResource, but the persisted zustand session on the client can
 * outlive schema changes — a user signed in before spatie/permission
 * was wired up (or on a broken login response) rehydrates with
 * `roles`/`permissions` undefined, and `.some/.includes/.map` on
 * undefined blew up the whole admin sidebar with the mangled
 * "a.map is not a function" runtime error. Fall back to [] so the
 * guarded page still renders — worst case a user sees no admin nav
 * until they re-login, instead of a client-side crash.
 */
function toStringArray(value: unknown): string[] {
  return Array.isArray(value) ? (value as string[]) : [];
}

export function isAdminUser(user: User | null): boolean {
  if (!user) return false;
  return toStringArray(user.roles).some((r) => ADMIN_ROLES.has(r));
}

/**
 * Runtime permission check. Falls back to `false` when the user isn't
 * loaded yet so navigation items and buttons stay hidden until we're sure.
 * super-admin implicitly has every permission.
 */
export function hasPermission(user: User | null, permission: string): boolean {
  if (!user) return false;
  const roles = toStringArray(user.roles);
  if (roles.includes('super-admin')) return true;
  return toStringArray(user.permissions).includes(permission);
}

/** Same as hasPermission but for a set of permissions (any-of semantics). */
export function hasAnyPermission(user: User | null, permissions: string[]): boolean {
  if (permissions.length === 0) return true; // no gate → everyone
  return permissions.some((p) => hasPermission(user, p));
}
