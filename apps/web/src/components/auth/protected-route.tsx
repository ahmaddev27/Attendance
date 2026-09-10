'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Loader2 } from 'lucide-react';
import { useAuthStore } from '@/lib/stores/auth-store';

/**
 * Guards a route group behind an authenticated session.
 *
 * The auth store persists a user profile snapshot to localStorage
 * (the credential itself is an httpOnly session cookie the JS can't
 * touch). Zustand rehydrates that snapshot asynchronously after
 * mount, so we wait for it before deciding the tab is signed out —
 * otherwise every hard refresh would flash a redirect to /login
 * before the persisted profile had a chance to load.
 */
export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  // Start false unconditionally: reading store.persist during render (rather
  // than inside an effect) executes on the server too, where zustand's
  // persist internals aren't safe to touch and break static prerendering.
  const [hasHydrated, setHasHydrated] = useState(false);

  useEffect(() => {
    if (useAuthStore.persist.hasHydrated()) {
      setHasHydrated(true);
      return;
    }
    return useAuthStore.persist.onFinishHydration(() => setHasHydrated(true));
  }, []);

  useEffect(() => {
    if (hasHydrated && !user) {
      router.replace('/login');
    }
  }, [hasHydrated, user, router]);

  if (!hasHydrated || !user) {
    return (
      <div className="min-h-screen grid place-items-center bg-ground">
        <Loader2 className="w-6 h-6 animate-spin text-brand" />
      </div>
    );
  }

  return <>{children}</>;
}
