/**
 * Thin auth hook that wraps the Zustand store with API calls.
 *
 * Keeping the API-facing methods here (instead of on the store) keeps the
 * store a plain state container and lets us swap the transport (axios,
 * fetch, mock) without touching UI-facing consumers.
 *
 * Two extra side-effects wired in here:
 *   • On successful login we register the device's Expo push token so
 *     the backend can start delivering push notifications immediately.
 *     Non-blocking — the login flow still succeeds if push permission
 *     was denied or the device is a web preview with no push support.
 *   • On logout we revoke the token for THIS device only (not every
 *     handset the user is signed into) so a second phone stays live.
 */

import { useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { api, extractApiMessage } from '../lib/api';
import { useAuthStore, type AuthUser } from '../lib/auth-store';
import {
  registerPushToken,
  revokePushToken,
  getDeviceId,
} from '../lib/push-notifications';

interface LoginPayload {
  /**
   * Email OR employee_number — the backend's LoginRequest normalises
   * both via its `identifier()` getter. We send a single string field
   * so the UI can keep one input instead of an email/number toggle.
   */
  identifier: string;
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

        // Fire-and-forget push registration — a permission-denied user
        // still gets a working login, and a background retry is fine
        // (the RN app registers again on every launch anyway).
        registerPushToken().catch(() => {
          /* silent — see reasoning above */
        });

        return data.user;
      } catch (err) {
        throw new Error(extractApiMessage(err, 'فشل تسجيل الدخول'));
      }
    },
    [setSession],
  );

  const logout = useCallback(async () => {
    // Revoke THIS handset's push token first so we stop receiving
    // notifications immediately; then clear the auth session. Both
    // are swallow-on-fail — the user still gets logged out locally.
    const deviceId = await getDeviceId().catch(() => null);
    if (deviceId) {
      await revokePushToken(deviceId).catch(() => {});
    }

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
