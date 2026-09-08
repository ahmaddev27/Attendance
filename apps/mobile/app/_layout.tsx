/**
 * Root layout.
 *
 * Responsibilities:
 *  1. Force RTL — TAQAT is Arabic-first. `I18nManager.forceRTL(true)` needs
 *     a reload the first time it's called; we call it before the tree
 *     renders so subsequent launches are already RTL.
 *  2. Provide `QueryClientProvider` and `SafeAreaProvider` to every screen.
 *  3. Hydrate the auth store from SecureStore, then decide whether to send
 *     the user into `(auth)` or `(tabs)` via `Redirect`.
 *  4. Keep the splash screen visible until hydration finishes so we never
 *     flash the login form for a logged-in user.
 */

import { useEffect, useState } from 'react';
import { I18nManager, StyleSheet, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { SplashScreen, Stack, useRouter, useSegments } from 'expo-router';
import { QueryClientProvider } from '@tanstack/react-query';

import { queryClient } from '../lib/queryClient';
import { colors } from '../lib/theme';
import { useAuthStore } from '../lib/auth-store';

// One-shot RTL flip. Skipped on subsequent launches once the platform has
// already been persisted into RTL mode.
if (!I18nManager.isRTL) {
  try {
    I18nManager.allowRTL(true);
    I18nManager.forceRTL(true);
  } catch {
    // Some environments (e.g. Expo web preview) throw — safe to ignore.
  }
}

// Keep the splash up until we know the auth status; prevents a flash of
// the login screen for a user who's already signed in.
SplashScreen.preventAutoHideAsync().catch(() => {});

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

  useEffect(() => {
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
