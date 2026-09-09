/**
 * Root layout.
 *
 * Responsibilities:
 *  1. Force RTL — TAQAT is Arabic-first. `I18nManager.forceRTL(true)` only
 *     takes effect after a native reload the first time it's called; we
 *     persist a `did-flip-rtl` flag so we flip + reload exactly once, then
 *     never loop on subsequent launches.
 *  2. Provide `QueryClientProvider` and `SafeAreaProvider` to every screen.
 *  3. Hydrate the auth store from SecureStore, then decide whether to send
 *     the user into `(auth)` or `(tabs)` via `Redirect`.
 *  4. Keep the splash screen visible until hydration finishes so we never
 *     flash the login form for a logged-in user.
 *  5. Route notification taps — extract `data.url` from the payload and
 *     push the user into that screen (cold-start + warm delivery).
 */

import { useEffect, useRef, useState } from 'react';
import { I18nManager, StyleSheet, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { SplashScreen, Stack, useRouter, useSegments } from 'expo-router';
import { QueryClientProvider } from '@tanstack/react-query';
import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import * as Updates from 'expo-updates';

import { queryClient } from '../lib/queryClient';
import { colors } from '../lib/theme';
import { useAuthStore } from '../lib/auth-store';
import { configureNotificationHandler, registerPushToken } from '../lib/push-notifications';

// Set the foreground-push presentation policy at module load — this
// runs once per JS bundle and is safe to call before the tree renders.
configureNotificationHandler();

// Keep the splash up until we know the auth status; prevents a flash of
// the login screen for a user who's already signed in.
SplashScreen.preventAutoHideAsync().catch(() => {});

const DID_FLIP_RTL_KEY = 'taqat.did-flip-rtl';

// Deep-link routes allowed from a notification payload. Anything else is
// dropped so a malicious/misconfigured push can't steer the app into an
// arbitrary in-app URL (or an external scheme).
const PUSH_URL_ALLOW_RE = /^\/(my-leaves|my-tasks|notifications|scan|attendance)($|\/)/;

/**
 * One-shot RTL flip. Runs inside an effect so we can persist the "we
 * already flipped" flag via SecureStore before triggering the native
 * reload — otherwise the flip fires again after every relaunch.
 *
 * Wrapped in try/catch — expo-updates is a no-op in some dev-client
 * contexts and SecureStore is stubbed on web.
 */
async function ensureRTL(): Promise<void> {
  if (I18nManager.isRTL) return;

  try {
    const alreadyFlipped = await SecureStore.getItemAsync(DID_FLIP_RTL_KEY);
    if (alreadyFlipped === 'true') return;

    I18nManager.allowRTL(true);
    I18nManager.forceRTL(true);
    await SecureStore.setItemAsync(DID_FLIP_RTL_KEY, 'true');

    if (!__DEV__) {
      // `expo-updates` is a no-op in some dev-client contexts and can
      // throw at reload time on unsupported runtimes — never let that
      // crash the boot sequence. The flag is already persisted, so
      // the RTL flip will settle on the next natural cold-start too.
      try {
        await Updates.reloadAsync();
      } catch {
        /* silent */
      }
    }
  } catch {
    // Some environments (Expo web preview, missing SecureStore) throw —
    // safe to ignore, we simply won't be RTL in those contexts.
  }
}

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <StatusBar style="dark" />
        <View style={styles.root}>
          <AuthGate>
            <Stack screenOptions={{ headerShown: false }}>
              <Stack.Screen name="(auth)" />
              <Stack.Screen name="(tabs)" />
              <Stack.Screen
                name="scan"
                options={{ presentation: 'modal', animation: 'slide_from_bottom' }}
              />
              <Stack.Screen
                name="leave-request"
                options={{ presentation: 'modal', animation: 'slide_from_bottom' }}
              />
              <Stack.Screen
                name="task/[id]"
                options={{ presentation: 'modal', animation: 'slide_from_bottom' }}
              />
            </Stack>
          </AuthGate>
        </View>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}

/**
 * Hydrates the auth store on boot and redirects between the auth and tabs
 * groups whenever the token changes. Placed inside `SafeAreaProvider` so
 * consumers can use `useSafeAreaInsets` immediately.
 */
function AuthGate({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const segments = useSegments();

  const status = useAuthStore((s) => s.status);
  const token = useAuthStore((s) => s.token);
  const hydrate = useAuthStore((s) => s.hydrate);

  const [ready, setReady] = useState(false);
  // Push-registration guard — the effect below can re-fire when the
  // token reference changes (rehydration, refresh). Once per session
  // is enough; the register endpoint is rate-limited server-side too.
  const registeredThisSession = useRef(false);

  useEffect(() => {
    // Fire the RTL flip in parallel with hydration — neither blocks the
    // other, and the reload (if any) will restart the JS bundle anyway.
    ensureRTL();

    hydrate().finally(() => {
      setReady(true);
      SplashScreen.hideAsync().catch(() => {});
    });
  }, [hydrate]);

  useEffect(() => {
    if (!ready || status !== 'ready') return;

    const inAuthGroup = segments[0] === '(auth)';
    if (!token && !inAuthGroup) {
      router.replace('/(auth)/login');
    } else if (token && inAuthGroup) {
      router.replace('/(tabs)');
    }
  }, [ready, status, token, segments, router]);

  // Re-register the push token on the first authenticated launch per
  // session. The OS can rotate Expo's push token silently (rare, after
  // an OTA update / long inactivity) and the upsert on (user, device_id)
  // keeps the DB clean instead of piling up duplicate rows. Guarded by
  // a ref so a token-reference churn doesn't refire the network call.
  useEffect(() => {
    if (!ready || !token) return;
    if (registeredThisSession.current) return;
    registeredThisSession.current = true;
    registerPushToken().catch(() => {
      // Silent — nothing else in the app hard-depends on push. Clear
      // the guard so a future re-login within the same session can
      // still retry.
      registeredThisSession.current = false;
    });
  }, [ready, token]);

  // Route notification taps into the app. Two entry points to cover:
  //   • Cold start — the app was launched by tapping a push while it
  //     was killed; `getLastNotificationResponseAsync` returns it once.
  //   • Warm — subscribe to future taps while the app is running.
  useEffect(() => {
    if (!ready) return;

    function handleResponse(response: Notifications.NotificationResponse | null) {
      if (!response) return;
      const rawUrl = (response.notification?.request?.content?.data as { url?: unknown } | undefined)?.url;
      if (typeof rawUrl !== 'string') return;
      if (!PUSH_URL_ALLOW_RE.test(rawUrl)) return;
      // `router.push` is typed against the app's static routes; the
      // allow-list above already narrows to known paths.
      router.push(rawUrl as never);
    }

    Notifications.getLastNotificationResponseAsync()
      .then(handleResponse)
      .catch(() => {});

    const sub = Notifications.addNotificationResponseReceivedListener(handleResponse);
    return () => sub.remove();
  }, [ready, router]);

  if (!ready) {
    return null;
  }

  return <>{children}</>;
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.bg,
  },
});
