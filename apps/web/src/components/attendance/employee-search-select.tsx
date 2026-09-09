'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Check, ChevronsUpDown, X } from 'lucide-react';

import { cn } from '@/lib/utils';
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { EmployeeSummary } from '@/lib/api/types';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
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

/**
 * Searchable employee combobox.
 *
 * `source` selects the roster the dropdown offers:
 * - `all` (default): admin-only `/employees` list — used on attendance
 *   filters and admin task creation.
 * - `my-team`: `/me/team` — the current user's teammates only. Use this
 *   whenever a regular employee is picking an assignee; the backend
 *   enforces the same scope so a bypass on the FE still 422s server-side.
 */
export function EmployeeSearchSelect({
  value,
  onChange,
  placeholder = 'كل الموظفين',
  source = 'all',
}: {
  value: EmployeeSummary | null;
  onChange: (employee: EmployeeSummary | null) => void;
  placeholder?: string;
  source?: 'all' | 'my-team';
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);

  const { data: employees, isFetching } = useQuery({
    queryKey: ['employees-search', source, debouncedSearch],
    queryFn: () =>
      source === 'my-team'
        ? employeesApi
            .myTeam(debouncedSearch || undefined)
            .then((r) => r.data.data)
        : employeesApi
            .list({ search: debouncedSearch || undefined, per_page: 20 })
            .then((r) => r.data.data),
    enabled: open,
    staleTime: 30_000,
  });

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className="w-full justify-between font-normal"
        >
          <span className="truncate">{value ? value.full_name : placeholder}</span>
          <span className="flex items-center gap-1">
            {value && (
              <X
                className="h-3.5 w-3.5 text-muted hover:text-ink"
                onClick={(e) => {
                  e.stopPropagation();
                  onChange(null);
                }}
              />
            )}
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
          </span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[280px] p-0" align="start">
        <Command shouldFilter={false}>
          <CommandInput
            placeholder="ابحث بالاسم أو الرقم الوظيفي..."
            value={search}
            onValueChange={setSearch}
          />
          <CommandList>
            <CommandEmpty>{isFetching ? 'جارٍ البحث...' : 'لا يوجد نتائج'}</CommandEmpty>
            <CommandGroup>
              {employees?.map((employee) => (
                <CommandItem
                  key={employee.id}
                  value={String(employee.id)}
                  onSelect={() => {
                    onChange(employee.id === value?.id ? null : employee);
                    setOpen(false);
                  }}
                >
                  <Check
                    className={cn('h-4 w-4', value?.id === employee.id ? 'opacity-100' : 'opacity-0')}
                  />
                  <span className="flex-1 truncate">{employee.full_name}</span>
                  <span className="num text-xs text-muted" dir="ltr">
                    {employee.employee_number}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
