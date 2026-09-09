/**
 * Expo push registration + revocation helpers.
 *
 * `registerPushToken()` is called by useAuth on successful login and by
 * a root-layout effect on every app launch (see `_layout.tsx`). It is a
 * no-op on web / when the user denies permission — we do NOT block the
 * flow, because push is a nice-to-have on mobile and not available at
 * all on the web preview.
 *
 * `revokePushToken(deviceId)` clears only THIS handset's server-side
 * row. A user signed into a second phone stays live there.
 *
 * `getDeviceId()` returns a stable per-install id from expo-secure-store,
 * generating one on first call. We prefer this over the OS-provided
 * install id (which resets on reinstall) so a re-login on the same
 * physical device overwrites the same row instead of piling up.
 */

import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import Constants from 'expo-constants';

import { api } from './api';

const DEVICE_ID_KEY = 'taqat.device.id';

/**
 * Return the stable install-scoped device id, generating one the first
 * time this function runs on the device. Kept in SecureStore because
 * AsyncStorage is plaintext on disk and the value is used as a
 * (user, device) uniqueness key server-side.
 */
export async function getDeviceId(): Promise<string> {
  let id = await SecureStore.getItemAsync(DEVICE_ID_KEY);
  if (!id) {
    // random UUID via crypto if available (RN 0.71+), else a fallback
    // built from the current timestamp + a random suffix.
    id =
      typeof globalThis.crypto?.randomUUID === 'function'
        ? globalThis.crypto.randomUUID()
        : `dev_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
    await SecureStore.setItemAsync(DEVICE_ID_KEY, id);
  }
  return id;
}

/**
 * Ask the OS for notification permission, fetch an Expo push token, and
 * register it with the backend. Returns the registered token on success
 * or null when we couldn't obtain one (web, denied permission, simulator
 * without a projectId, etc). Errors are caught at the caller side —
 * useAuth intentionally ignores them so a failed push registration
 * doesn't break login.
 */
export async function registerPushToken(): Promise<string | null> {
  // Web: expo-notifications is a stub — skip cleanly.
  if (Platform.OS === 'web') {
    return null;
  }

  const settings = await Notifications.getPermissionsAsync();
  let status = settings.status;
  if (status !== 'granted') {
    const request = await Notifications.requestPermissionsAsync();
    status = request.status;
  }
  if (status !== 'granted') {
    return null;
  }

  // Android: create a default channel so Expo pushes land with a name
  // + sound instead of the OS's generic "unknown" channel bucket.
  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'TAQAT',
      importance: Notifications.AndroidImportance.HIGH,
      sound: 'default',
      lightColor: '#2678c4',
    });
  }

  // The projectId is read from EAS build config or app.json's
  // `expo.extra.eas.projectId`. Without it Expo returns an "anonymous"
  // token that the /send endpoint rejects — better to fail here than
  // to save a dead token and spam the prune path later.
  const projectId =
    Constants?.expoConfig?.extra?.eas?.projectId ??
    Constants?.easConfig?.projectId ??
    undefined;

  // Hard-stop if the placeholder projectId from app.json is still in
  // place. Without a real projectId `getExpoPushTokenAsync` returns a
  // token Expo's /send rejects, so pushes silently disappear — much
  // better to surface the misconfig immediately during development.
  // Run `eas init` and paste the returned id into app.json to clear.
  if (typeof projectId === 'string' && projectId.startsWith('REPLACE_')) {
    throw new Error(
      'expo.extra.eas.projectId is still the placeholder (REPLACE_...). ' +
        'Run `eas init` and paste the returned projectId into apps/mobile/app.json.',
    );
  }

  const tokenResponse = await Notifications.getExpoPushTokenAsync(
    projectId ? { projectId } : undefined,
  );

  const token = tokenResponse.data;
  if (!token) return null;

  const deviceId = await getDeviceId();

  try {
    await api.post('/me/push-tokens', {
      token,
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
      device_id: deviceId,
      device_name:
        Constants?.deviceName ??
        Constants?.expoConfig?.name ??
        `${Platform.OS} device`,
    });
    return token;
  } catch {
    // Non-fatal — a failed register means we simply won't get pushes
    // until the next launch retry. Never breaks the calling flow.
    return null;
  }
}

/**
 * Revoke this handset's push token server-side. Silent-on-fail — the
 * device is still logged out locally either way.
 */
export async function revokePushToken(deviceId: string): Promise<void> {
  await api.delete(`/me/push-tokens/${encodeURIComponent(deviceId)}`);
}

/**
 * Notification handler — decides how a push arriving while the app is
 * foregrounded is presented. We show the alert + play the sound but
 * don't set a badge; the API already tracks unread counts.
 */
export function configureNotificationHandler(): void {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowAlert: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });
}
