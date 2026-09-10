'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Check, ChevronsUpDown } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { TaskTagBadge } from '@/components/tasks/task-tag-badge';
import { taskTagsApi } from '@/lib/api/endpoints/task-config';
import type { TaskTag } from '@/lib/api/types';
import { cn } from '@/lib/utils';

type TaskTagPickerProps = {
  value: TaskTag[];
  onChange: (tags: TaskTag[]) => void;
  disabled?: boolean;
};

/** Multi-select tag picker — a checkable list in a popover, with the current selection shown as removable pills below it. */
export function TaskTagPicker({ value, onChange, disabled }: TaskTagPickerProps) {
  const [open, setOpen] = React.useState(false);

  const { data: allTags } = useQuery({
    queryKey: ['task-tags'],
    queryFn: async () => (await taskTagsApi.list()).data.data,
  });

  const toggleTag = (tag: TaskTag) => {
    const exists = value.some((t) => t.id === tag.id);
    onChange(exists ? value.filter((t) => t.id !== tag.id) : [...value, tag]);
  };

  return (
    <div className="space-y-2">
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className="w-full justify-between font-normal"
          >
            <span className={cn(value.length === 0 && 'text-muted-foreground')}>
              {value.length > 0 ? `${value.length} وسم محدد` : 'اختر الوسوم'}
            </span>
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-64 p-0" align="start">
          <Command>
            <CommandList>
              <CommandEmpty>لا توجد وسوم بعد</CommandEmpty>
              <CommandGroup>
                {(allTags ?? []).map((tag) => {
                  const selected = value.some((t) => t.id === tag.id);
                  return (
                    <CommandItem key={tag.id} value={tag.name} onSelect={() => toggleTag(tag)}>
                      <Check className={cn('h-4 w-4', selected ? 'opacity-100' : 'opacity-0')} />
                      <TaskTagBadge tag={tag} />
                    </CommandItem>
                  );
                })}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>

      {value.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {value.map((tag) => (
            <TaskTagBadge key={tag.id} tag={tag} onRemove={disabled ? undefined : () => toggleTag(tag)} />
          ))}
        </div>
      )}
    </div>
  );
}
