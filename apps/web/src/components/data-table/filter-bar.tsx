'use client';

import * as React from 'react';
import { Search } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type FilterBarProps = {
  searchValue: string;
  onSearchChange: (value: string) => void;
  searchPlaceholder?: string;
  /** Filter selects rendered next to the search box. */
  children?: React.ReactNode;
  className?: string;
};

/** Search input + filter-select row shared by every M2 list page. */
export function FilterBar({
  searchValue,
  onSearchChange,
  searchPlaceholder = 'بحث...',
  children,
  className,
}: FilterBarProps) {
  return (
    <div
      className={cn(
        'flex flex-col gap-3 rounded-xl border border-hairline bg-surface p-4 md:flex-row md:flex-wrap md:items-center',
        className
      )}
    >
      <div className="relative w-full md:w-64">
        {/* The search icon sits at the start of the field — RIGHT in RTL,
            LEFT in LTR — so we use logical `start`/`ps` and the input
            reserves matching padding for the icon overlay. */}
        <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
        <Input
          value={searchValue}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder={searchPlaceholder}
          className="ps-9"
        />
      </div>
      {children && <div className="flex flex-1 flex-wrap items-center gap-3">{children}</div>}
    </div>
  );
}
