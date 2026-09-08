/**
 * Auth state store.
 *
 * State is kept in memory via Zustand and mirrored to `expo-secure-store`
 * (Keychain / Keystore). We DO NOT use AsyncStorage for the token because
 * AsyncStorage is plaintext on device — a rooted / jailbroken phone would
 * leak the Sanctum bearer.
 *
 * The store exposes:
 *   - `hydrate()`   — call once on app boot to load token+user from disk.
 *   - `setSession()`— call after a successful login.
 *   - `clear()`     — logout (also invoked by the API 401 interceptor).
 *
 * We also register the token getter with `lib/api` so Axios can inject the
 * Bearer header without importing this store (which would create a cycle).
 */

import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

import { setAuthTokenGetter, setUnauthorizedHandler } from './api';

const TOKEN_KEY = 'taqat.auth.token';
const USER_KEY = 'taqat.auth.user';

export interface AuthUser {
  id: number;
  employee_number: number;
  name: string;
  email?: string | null;
  role?: string | null;
  // Backend UserResource may include more — keep it open on the boundary.
  [key: string]: unknown;
}

interface AuthState {
  token: string | null;
  user: AuthUser | null;
  status: 'idle' | 'hydrating' | 'ready';

  hydrate: () => Promise<void>;
  setSession: (payload: { token: string; user: AuthUser }) => Promise<void>;
  clear: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  token: null,
  user: null,
  status: 'idle',

  async hydrate() {
    if (get().status !== 'idle') return;
    set({ status: 'hydrating' });
    try {
      const [token, userJson] = await Promise.all([
        SecureStore.getItemAsync(TOKEN_KEY),
        SecureStore.getItemAsync(USER_KEY),
      ]);
      const user = userJson ? (JSON.parse(userJson) as AuthUser) : null;
      set({ token, user, status: 'ready' });
    } catch {
      // Corrupt storage — clear it and continue unauthenticated.
      await Promise.all([
        SecureStore.deleteItemAsync(TOKEN_KEY).catch(() => {}),
        SecureStore.deleteItemAsync(USER_KEY).catch(() => {}),
      ]);
      set({ token: null, user: null, status: 'ready' });
    }
  },

  async setSession({ token, user }) {
    await Promise.all([
      SecureStore.setItemAsync(TOKEN_KEY, token),
      SecureStore.setItemAsync(USER_KEY, JSON.stringify(user)),
    ]);
    set({ token, user });
  },

  async clear() {
    await Promise.all([
      SecureStore.deleteItemAsync(TOKEN_KEY).catch(() => {}),
      SecureStore.deleteItemAsync(USER_KEY).catch(() => {}),
    ]);
    set({ token: null, user: null });
  },
}));

// Wire the store into the API client. The getter is closure-fresh — it reads
// the latest snapshot on every request instead of a stale copy.
setAuthTokenGetter(() => useAuthStore.getState().token);
setUnauthorizedHandler(() => useAuthStore.getState().clear());
