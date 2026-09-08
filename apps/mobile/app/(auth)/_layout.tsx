import { Stack } from 'expo-router';

/**
 * Auth group — headerless stack. Currently a single screen (login), but
 * kept as a group so we can drop `forgot-password.tsx`, `reset.tsx`, etc.
 * next to it without touching the routing shape.
 */
export default function AuthLayout() {
  return <Stack screenOptions={{ headerShown: false, animation: 'fade' }} />;
}
