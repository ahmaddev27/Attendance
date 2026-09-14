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
import { recruitmentUsersApi } from '@/lib/api/endpoints/recruitment';
import type { RecruitmentUserOption, UserMini } from '@/lib/api/types';
import { cn } from '@/lib/utils';

/**
 * Showing a selection only needs id + name, so a resource's `owner`
 * (UserMini) and the signed-in user both fit without an extra lookup.
 */
export type SelectedUser = Pick<UserMini, 'id' | 'name'>;

type UserSelectProps = {
  value: SelectedUser | null;
  onChange: (user: RecruitmentUserOption | null) => void;
  placeholder?: string;
  clearable?: boolean;
  disabled?: boolean;
  /** Applied to the wrapper — e.g. `mt-1.5` in a form, a width in a filter bar. */
  className?: string;
  'aria-label'?: string;
};

export function toSelectedUser(user: SelectedUser | null | undefined): SelectedUser | null {
  return user ? { id: user.id, name: user.name } : null;
}

/**
 * Searchable picker over the users recruitment work can be assigned to
 * (GET /recruitment/users). Same look and behaviour as EmployeePicker;
 * results load only while the list is open.
 */
export function UserSelect({
  value,
  onChange,
  placeholder = 'اختر مستخدماً...',
  clearable = true,
  disabled,
  className,
  'aria-label': ariaLabel,
}: UserSelectProps) {
  const [open, setOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const debouncedSearch = useDebouncedValue(search, 300);

  const { data: users, isFetching } = useQuery({
    queryKey: ['recruitment-users', 'picker', debouncedSearch],
    queryFn: async () =>
      (await recruitmentUsersApi.list({ search: debouncedSearch || undefined })).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const showClear = clearable && !!value && !disabled;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <div className={cn('relative', className)}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            aria-label={ariaLabel}
            disabled={disabled}
            className={cn('w-full justify-between font-normal', showClear && 'pe-8')}
          >
            <span className={cn('truncate', !value && 'text-muted-foreground')}>
              {value ? value.name : placeholder}
            </span>
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        {/* A separate focusable button, not nested in the trigger, so clearing
            stays reachable by keyboard and assistive tech. */}
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
      <PopoverContent className="w-[--radix-popover-trigger-width] min-w-[16rem] p-0" align="start">
        <Command shouldFilter={false}>
          <CommandInput
            value={search}
            onValueChange={setSearch}
            placeholder="ابحث بالاسم أو البريد أو الرقم الوظيفي..."
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
              {(users ?? []).map((user) => (
                <CommandItem
                  key={user.id}
                  value={String(user.id)}
                  onSelect={() => {
                    onChange(user);
                    setOpen(false);
                  }}
                >
                  <Check className={cn('h-4 w-4', value?.id === user.id ? 'opacity-100' : 'opacity-0')} />
                  <span className="flex-1 truncate">{user.name}</span>
                  {user.employee_number !== null && (
                    <span className="num text-xs text-muted" dir="ltr">
                      {user.employee_number}
                    </span>
                  )}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
