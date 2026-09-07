import { useEffect, useState } from 'react';

/**
 * Debounces a fast-changing value (typically a search input) so consumers —
 * like a `useQuery` key — only react once the user pauses typing, instead of
 * firing a request per keystroke.
 */
export function useDebouncedValue<T>(value: T, delayMs = 350): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}
