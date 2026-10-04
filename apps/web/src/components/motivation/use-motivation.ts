import { useQuery } from '@tanstack/react-query';

import { getMotivation } from '@/lib/api/endpoints/me';

const SIX_HOURS_MS = 1000 * 60 * 60 * 6;

/** Daily data: cached server-side per day, so refetching sooner is wasted. */
export function useMotivation() {
  return useQuery({
    queryKey: ['motivation'],
    queryFn: getMotivation,
    staleTime: SIX_HOURS_MS,
    retry: false,
  });
}
