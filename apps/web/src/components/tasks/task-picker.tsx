'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Check, ChevronsUpDown, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { tasksApi } from '@/lib/api/endpoints/tasks';
import type { Task } from '@/lib/api/types';
import { cn } from '@/lib/utils';

export type TaskPickerOption = Pick<Task, 'id' | 'title'>;

type TaskPickerProps = {
  value: TaskPickerOption | null;
  onChange: (task: TaskPickerOption | null) => void;
  /** Excludes a task from the results — a task can't be its own parent. */
  excludeId?: number;
  placeholder?: string;
  disabled?: boolean;
};

/**
 * Searchable task combobox for picking an (optional) parent task —
 * mirrors `EmployeePicker`'s pattern of a live, debounced API search rather
 * than loading every task up front.
 */
export function TaskPicker({ value, onChange, excludeId, placeholder = 'بدون مهمة رئيسية', disabled }: TaskPickerProps) {
  const [open, setOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const debouncedSearch = useDebouncedValue(search, 300);

  const { data: options, isFetching } = useQuery({
    queryKey: ['tasks', 'picker', debouncedSearch],
    queryFn: async () => {
      const { data } = await tasksApi.list({ search: debouncedSearch || undefined, per_page: 20 });
      return data.data;
    },
    enabled: open,
    staleTime: 30_000,
  });

  const filteredOptions = (options ?? []).filter((task) => task.id !== excludeId);
  const showClear = !!value && !disabled;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <div className="relative">
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className={cn('w-full justify-between font-normal', showClear && 'pe-8')}
          >
            <span className={cn('truncate', !value && 'text-muted-foreground')}>
              {value ? value.title : placeholder}
            </span>
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        {showClear && (
          <button
            type="button"
            onClick={() => onChange(null)}
            aria-label="إزالة المهمة الرئيسية"
            className="absolute inset-y-0 end-7 flex items-center text-ink-2 opacity-60 hover:opacity-100"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
      <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
        <Command shouldFilter={false}>
          <CommandInput value={search} onValueChange={setSearch} placeholder="ابحث عن مهمة..." />
          <CommandList>
            {isFetching && (
              <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted">
                <Spinner className="h-4 w-4" />
                جارِ البحث...
              </div>
            )}
            {!isFetching && <CommandEmpty>لا توجد نتائج</CommandEmpty>}
            <CommandGroup>
              {filteredOptions.map((task) => (
                <CommandItem
                  key={task.id}
                  value={String(task.id)}
                  onSelect={() => {
                    onChange(task);
                    setOpen(false);
                  }}
                >
                  <Check className={cn('h-4 w-4', value?.id === task.id ? 'opacity-100' : 'opacity-0')} />
                  <span className="flex-1 truncate">{task.title}</span>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
