import { Loader2 } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * Reusable loading spinner (buttons, page-level loaders, etc). Wraps Lucide's
 * Loader2 so every spinner in the app shares one animation + default size.
 */
export function Spinner({ className }: { className?: string }) {
  return <Loader2 className={cn('h-4 w-4 animate-spin', className)} aria-hidden="true" />;
}
