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
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { EmployeeMini, EmployeeSummary } from '@/lib/api/types';
import { cn } from '@/lib/utils';

type EmployeePickerProps = {
  // The picker only reads id + full_name off of the selected value, so it
  // accepts the trimmed EmployeeMini shape too — some resources (like
  // Employee.direct_manager) only ship those two fields from the API.
  value: EmployeeMini | null;
  onChange: (employee: EmployeeSummary | null) => void;
  placeholder?: string;
  /** Excludes an employee from the results — e.g. an employee can't manage themself. */
  excludeId?: number;
  disabled?: boolean;
  clearable?: boolean;
};

/**
 * Searchable employee combobox used everywhere the M2 spec calls for a
 * "searchable select of employees": direct manager on the employee form,
 * and department/team manager & leader assignment.
 *
 * The component only needs to know the *currently selected* EmployeeSummary
 * (passed in by the caller, who already has it from the record being
 * edited) — search results are fetched live from the API as the user types,
 * so this never has to load the full employee list up front.
 */
export function EmployeePicker({
  value,
  onChange,
  placeholder = 'اختر موظفاً...',
  excludeId,
  disabled,
  clearable = true,
}: EmployeePickerProps) {
  const [open, setOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const debouncedSearch = useDebouncedValue(search, 300);

  const { data: options, isFetching } = useQuery({
    queryKey: ['employees', 'picker', debouncedSearch],
    queryFn: async () => {
      const { data } = await employeesApi.list({
        search: debouncedSearch || undefined,
        per_page: 20,
      });
      return data.data;
    },
    enabled: open,
    staleTime: 30_000,
  });

  const filteredOptions = (options ?? []).filter((employee) => employee.id !== excludeId);

  const showClear = clearable && !!value && !disabled;

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
              {value ? value.full_name : placeholder}
            </span>
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        {/* A real, independently-focusable button — not nested inside the
            trigger button — so clearing the selection stays keyboard/AT
            accessible instead of relying on a bare clickable icon. */}
        {showClear && (
          <button
            type="button"
            onClick={() => onChange(null)}
            aria-label="إزالة الاختيار"
            className="absolute inset-y-0 end-7 flex items-center text-ink-2 opacity-60 hover:opacity-100"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
      <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
        <Command shouldFilter={false}>
          <CommandInput
            value={search}
            onValueChange={setSearch}
            placeholder="ابحث بالاسم أو الرقم الوظيفي..."
          />
          <CommandList>
            {isFetching && (
              <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted">
                <Spinner className="h-4 w-4" />
                جارِ البحث...
              </div>
            )}
            {!isFetching && <CommandEmpty>لا توجد نتائج</CommandEmpty>}
            <CommandGroup>
              {filteredOptions.map((employee) => (
                <CommandItem
                  key={employee.id}
                  value={String(employee.id)}
                  onSelect={() => {
                    onChange(employee);
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
