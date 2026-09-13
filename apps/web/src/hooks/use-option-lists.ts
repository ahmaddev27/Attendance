import { useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';

import { optionListsApi, type OptionItem, type OptionListKey } from '@/lib/api/endpoints/option-lists';

export const OPTION_LISTS_QUERY_KEY = ['option-lists'] as const;

const EMPTY: OptionItem[] = [];

/**
 * Admin-editable picker lists (currencies, lead sources, industries, ...).
 * Every form and table shares one cached request; lists change rarely, so
 * a five-minute stale time keeps page navigation free of refetches.
 */
export function useOptionLists() {
  const { data, isLoading } = useQuery({
    queryKey: OPTION_LISTS_QUERY_KEY,
    queryFn: async () => (await optionListsApi.all()).data.data,
    staleTime: 5 * 60_000,
  });

  const options = useCallback((key: OptionListKey): OptionItem[] => data?.[key] ?? EMPTY, [data]);

  /**
   * Display label for a stored code. Falls back to the code itself so a
   * value an admin removed from the list (or typed before lists existed)
   * still renders instead of disappearing.
   */
  const labelOf = useCallback(
    (key: OptionListKey, value: string | null | undefined): string | null => {
      if (!value) return null;
      return data?.[key]?.find((item) => item.value === value)?.label ?? value;
    },
    [data],
  );

  return { options, labelOf, isLoading };
}
