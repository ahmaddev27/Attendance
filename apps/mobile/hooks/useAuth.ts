/**
 * Thin auth hook that wraps the Zustand store with API calls.
 *
 * Keeping the API-facing methods here (instead of on the store) keeps the
 * store a plain state container and lets us swap the transport (axios,
 * fetch, mock) without touching UI-facing consumers.
 */

import { useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { api, extractApiMessage } from '../lib/api';
import { useAuthStore, type AuthUser } from '../lib/auth-store';

interface LoginPayload {
  employee_number: number;
  password: string;
}

interface LoginResponse {
  user: AuthUser;
  token: string;
}

export function useAuth() {
  const queryClient = useQueryClient();
  const token = useAuthStore((s) => s.token);
  const user = useAuthStore((s) => s.user);
  const status = useAuthStore((s) => s.status);
  const setSession = useAuthStore((s) => s.setSession);
  const clear = useAuthStore((s) => s.clear);

  const login = useCallback(
    async (payload: LoginPayload): Promise<AuthUser> => {
      try {
        const { data } = await api.post<LoginResponse>('/auth/login', payload);
        await setSession({ token: data.token, user: data.user });
        return data.user;
      } catch (err) {
        throw new Error(extractApiMessage(err, 'فشل تسجيل الدخول'));
      }
    },
    [setSession],
  );

  const logout = useCallback(async () => {
    // Fire-and-forget — the token is revoked server-side, but even if the
    // request fails we clear the local session so the user can log back in.
    try {
      await api.post('/auth/logout');
    } catch {
      // Ignore — 401 already clears the store via the interceptor.
    }
    await clear();
    queryClient.clear();
  }, [clear, queryClient]);

  return {
    token,
    user,
    status,
    isAuthenticated: Boolean(token),
    login,
    logout,
  } as const;
}
