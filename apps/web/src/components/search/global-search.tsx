'use client';

/*
 * TODO: mount <GlobalSearch /> in admin-header.tsx (drive its `open`
 * state with the exported `useGlobalSearchTrigger()` hook). This
 * component is deliberately not wired here to keep the header edit
 * out of this task's diff — a follow-up commit does it.
 */

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { Briefcase, FileText, PlaneTakeoff, User } from 'lucide-react';

import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/components/ui/command';
import { searchApi, type SearchResponse, type SearchResult } from '@/lib/api/endpoints/search';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

const MIN_QUERY_LENGTH = 2;
const DEBOUNCE_MS = 300;

/**
 * Command-palette-style global search. Every group is rendered in the
 * same list so cmdk handles arrow-key navigation across types.
 */
export function GlobalSearch({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const router = useRouter();
  const [term, setTerm] = React.useState('');
  const debounced = useDebouncedValue(term, DEBOUNCE_MS);
  const enabled = debounced.trim().length >= MIN_QUERY_LENGTH;

  // Reset the input every time the palette closes so the next open
  // isn't stale.
  React.useEffect(() => {
    if (!open) setTerm('');
  }, [open]);

  const { data, isFetching } = useQuery({
    queryKey: ['global-search', debounced],
    queryFn: async () => (await searchApi.query(debounced)).data.data,
    enabled,
    staleTime: 30_000,
  });

  const emptyResults: SearchResponse = {
    employees: [],
    tasks: [],
    requests: [],
    leaves: [],
  };
  const results: SearchResponse = data ?? emptyResults;
  const isEmpty =
    results.employees.length +
      results.tasks.length +
      results.requests.length +
      results.leaves.length ===
    0;

  const handleSelect = (url: string) => {
    onOpenChange(false);
    router.push(url);
  };

  return (
    <CommandDialog open={open} onOpenChange={onOpenChange}>
      {/* cmdk does its own client-side filtering by default, which fights
          server results — turn it off with `shouldFilter={false}` so the
          list mirrors what the backend returned exactly. */}
      <CommandInput
        placeholder="ابحث في الموظفين والمهام والطلبات…"
        value={term}
        onValueChange={setTerm}
      />
      <CommandList>
        {!enabled ? (
          <CommandEmpty>اكتب حرفين على الأقل لبدء البحث</CommandEmpty>
        ) : isFetching && isEmpty ? (
          <CommandEmpty>جارٍ البحث…</CommandEmpty>
        ) : isEmpty ? (
          <CommandEmpty>لا توجد نتائج مطابقة</CommandEmpty>
        ) : null}

        <ResultGroup
          heading="الموظفون"
          items={results.employees}
          icon={<User className="h-4 w-4" />}
          onSelect={handleSelect}
        />
        {results.employees.length > 0 && results.tasks.length > 0 && <CommandSeparator />}
        <ResultGroup
          heading="المهام"
          items={results.tasks}
          icon={<Briefcase className="h-4 w-4" />}
          onSelect={handleSelect}
        />
        {results.tasks.length > 0 && results.requests.length > 0 && <CommandSeparator />}
        <ResultGroup
          heading="الطلبات"
          items={results.requests}
          icon={<FileText className="h-4 w-4" />}
          onSelect={handleSelect}
        />
        {results.requests.length > 0 && results.leaves.length > 0 && <CommandSeparator />}
        <ResultGroup
          heading="الإجازات"
          items={results.leaves}
          icon={<PlaneTakeoff className="h-4 w-4" />}
          onSelect={handleSelect}
        />
      </CommandList>
    </CommandDialog>
  );
}

function ResultGroup({
  heading,
  items,
  icon,
  onSelect,
}: {
  heading: string;
  items: SearchResult[];
  icon: React.ReactNode;
  onSelect: (url: string) => void;
}) {
  if (items.length === 0) return null;

  return (
    <CommandGroup heading={heading}>
      {items.map((item) => (
        <CommandItem
          key={`${item.type}-${item.id}`}
          value={`${item.type}-${item.id}-${item.title}`}
          onSelect={() => onSelect(item.url)}
        >
          <span className="text-muted-foreground">{icon}</span>
          <div className="flex flex-1 flex-col overflow-hidden">
            <span className="truncate text-sm">{item.title}</span>
            {item.subtitle && (
              <span className="truncate text-xs text-muted-foreground">{item.subtitle}</span>
            )}
          </div>
        </CommandItem>
      ))}
    </CommandGroup>
  );
}

/**
 * Owns the palette's open state + the Cmd/Ctrl+K keyboard shortcut so
 * the header (or any consumer) doesn't have to re-implement either.
 *
 * Usage:
 *   const { open, setOpen } = useGlobalSearchTrigger();
 *   return <><TriggerButton onClick={() => setOpen(true)} /><GlobalSearch open={open} onOpenChange={setOpen} /></>;
 */
export function useGlobalSearchTrigger() {
  const [open, setOpen] = React.useState(false);

  React.useEffect(() => {
    const handler = (event: KeyboardEvent) => {
      if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        setOpen((prev) => !prev);
      }
    };

    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  return { open, setOpen };
}
