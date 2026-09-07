'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';

import { Command, CommandEmpty, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { EmployeeSummary } from '@/lib/api/types';

export type MentionedEmployee = { id: number; full_name: string };

/**
 * A comment mentions someone via a `@[Full Name]` token embedded directly in
 * the body text — bracketed so the display-side regex parser
 * (`MENTION_REGEX`) can find and highlight it unambiguously, even though
 * Arabic full names contain spaces.
 */
export function mentionToken(name: string): string {
  return `@[${name}]`;
}

export const MENTION_REGEX = /@\[([^\]]+)\]/g;

type MentionInputProps = {
  value: string;
  onChange: (value: string) => void;
  mentions: MentionedEmployee[];
  onMentionsChange: (mentions: MentionedEmployee[]) => void;
  onSubmit?: () => void;
  placeholder?: string;
  disabled?: boolean;
  rows?: number;
  className?: string;
};

/**
 * Reusable textarea with `@` autocomplete: typing `@` followed by any text
 * (until the next whitespace) opens a popover of matching employees; picking
 * one inserts a mention token at the cursor and records it in `mentions` so
 * the caller can submit `{ body, mentions: [...ids] }`.
 */
export function MentionInput({
  value,
  onChange,
  mentions,
  onMentionsChange,
  onSubmit,
  placeholder,
  disabled,
  rows = 3,
  className,
}: MentionInputProps) {
  const textareaRef = React.useRef<HTMLTextAreaElement | null>(null);
  const [query, setQuery] = React.useState<string | null>(null);
  const [mentionStart, setMentionStart] = React.useState<number | null>(null);
  const debouncedQuery = useDebouncedValue(query ?? '', 250);

  const { data: options, isFetching } = useQuery({
    queryKey: ['employees', 'mention-search', debouncedQuery],
    queryFn: async () => {
      const { data } = await employeesApi.list({ search: debouncedQuery || undefined, per_page: 8 });
      return data.data;
    },
    enabled: query !== null,
    staleTime: 30_000,
  });

  const closeMentionMenu = () => {
    setQuery(null);
    setMentionStart(null);
  };

  const updateMentionQuery = (text: string, caret: number) => {
    const upToCaret = text.slice(0, caret);
    const match = /(?:^|\s)@([^\s@]*)$/.exec(upToCaret);
    if (!match) {
      closeMentionMenu();
      return;
    }
    setQuery(match[1]);
    setMentionStart(caret - match[1].length - 1);
  };

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    onChange(e.target.value);
    updateMentionQuery(e.target.value, e.target.selectionStart);
  };

  const insertMention = (employee: EmployeeSummary) => {
    const textarea = textareaRef.current;
    if (mentionStart === null || !textarea) return;

    const caret = textarea.selectionStart;
    const before = value.slice(0, mentionStart);
    const after = value.slice(caret);
    const token = `${mentionToken(employee.full_name)} `;
    onChange(`${before}${token}${after}`);

    if (!mentions.some((m) => m.id === employee.id)) {
      onMentionsChange([...mentions, { id: employee.id, full_name: employee.full_name }]);
    }

    closeMentionMenu();

    requestAnimationFrame(() => {
      const nextCaret = before.length + token.length;
      textarea.focus();
      textarea.setSelectionRange(nextCaret, nextCaret);
    });
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      onSubmit?.();
      return;
    }
    if (e.key === 'Escape' && query !== null) {
      closeMentionMenu();
    }
  };

  return (
    <Popover open={query !== null} onOpenChange={(next) => !next && closeMentionMenu()}>
      <PopoverAnchor asChild>
        <Textarea
          ref={textareaRef}
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          rows={rows}
          className={className}
        />
      </PopoverAnchor>
      <PopoverContent align="start" className="w-72 p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
        <Command shouldFilter={false}>
          <CommandList>
            <CommandEmpty>{isFetching ? 'جارٍ البحث...' : 'لا يوجد نتائج'}</CommandEmpty>
            <CommandGroup heading="الإشارة إلى موظف">
              {(options ?? []).map((employee) => (
                <CommandItem key={employee.id} value={String(employee.id)} onSelect={() => insertMention(employee)}>
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
