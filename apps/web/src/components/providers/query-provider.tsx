'use client';

import { useEffect, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

export function QueryProvider({ children }: { children: React.ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60_000,
            retry: 1,
          },
        },
      })
  );

  // React-Query's cache is shared across route transitions and persists as
  // long as this provider is mounted — but signing out then in as a
  // different user on the same tab would leave user A's cached responses
  // visible until they turned stale. The auth store fires
  // `taqat:auth-reset` on both login and logout; drop the entire cache
  // when either boundary is crossed. See fix #6 in the Wave D audit.
  useEffect(() => {
    const handler = () => client.clear();
    window.addEventListener('taqat:auth-reset', handler);
    return () => window.removeEventListener('taqat:auth-reset', handler);
  }, [client]);

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
