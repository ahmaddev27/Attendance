'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Loader2 } from 'lucide-react';
import { useAuthStore } from '@/lib/stores/auth-store';

/**
 * Guards a route group behind an authenticated session.
 *
 * The auth store persists the token to localStorage, which is only
 * readable client-side and rehydrates asynchronously after mount. We wait
 * for that rehydration to finish before deciding there is no token —
 * otherwise every hard refresh would flash a redirect to /login before the
 * persisted token has a chance to load.
 */
export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const token = useAuthStore((s) => s.token);
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
    if (hasHydrated && !token) {
      router.replace('/login');
    }
  }, [hasHydrated, token, router]);

  if (!hasHydrated || !token) {
    return (
      <div className="min-h-screen grid place-items-center bg-ground">
        <Loader2 className="w-6 h-6 animate-spin text-brand" />
      </div>
    );
  }

  return <>{children}</>;
}
