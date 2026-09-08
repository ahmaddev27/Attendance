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

export function isAdminUser(user: User | null): boolean {
  if (!user) return false;
  return user.roles.some((r) => ADMIN_ROLES.has(r));
}

/**
 * Runtime permission check. Falls back to `false` when the user isn't
 * loaded yet so navigation items and buttons stay hidden until we're sure.
 * super-admin implicitly has every permission.
 */
export function hasPermission(user: User | null, permission: string): boolean {
  if (!user) return false;
  if (user.roles.includes('super-admin')) return true;
  return user.permissions.includes(permission);
}

/** Same as hasPermission but for a set of permissions (any-of semantics). */
export function hasAnyPermission(user: User | null, permissions: string[]): boolean {
  if (permissions.length === 0) return true; // no gate → everyone
  return permissions.some((p) => hasPermission(user, p));
}
