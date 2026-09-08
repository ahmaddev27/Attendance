import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export type User = {
  id: number;
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
  logout: () => void;
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      setAuth: (user, token) => {
        localStorage.setItem('taqat_token', token);
        set({ user, token });
      },
      logout: () => {
        localStorage.removeItem('taqat_token');
        set({ user: null, token: null });
      },
    }),
    { name: 'taqat-auth' }
  )
);

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
