'use client';

import * as React from 'react';

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useOptionLists } from '@/hooks/use-option-lists';
import type { OptionItem, OptionListKey } from '@/lib/api/endpoints/option-lists';

/** Radix Select reserves "" for "no selection", so clearing needs a sentinel. */
const EMPTY_VALUE = '__none__';

type TriggerProps = Omit<
  React.ComponentPropsWithoutRef<typeof SelectTrigger>,
  'value' | 'defaultValue' | 'onChange' | 'children' | 'disabled'
>;

type OptionSelectProps = TriggerProps & {
  list: OptionListKey;
  value: string | null | undefined;
  /** Receives "" when the user picks the empty option. */
  onValueChange: (value: string) => void;
  allowEmpty?: boolean;
  emptyLabel?: string;
  placeholder?: string;
  /** Render "label (CODE)" — useful when the code itself is meaningful, e.g. currencies. */
  showValue?: boolean;
  disabled?: boolean;
};

/**
 * Dropdown bound to one admin-editable picker list. Forwards its ref and
 * remaining props to the trigger so it can sit directly inside a
 * react-hook-form <FormControl>.
 */
export const OptionSelect = React.forwardRef<HTMLButtonElement, OptionSelectProps>(function OptionSelect(
  {
    list,
    value,
    onValueChange,
    allowEmpty = false,
    emptyLabel = '—',
    placeholder = 'اختر',
    showValue = false,
    disabled,
    ...triggerProps
  },
  ref,
) {
  const { options } = useOptionLists();
  const current = value ?? '';

  const items = React.useMemo<OptionItem[]>(() => {
    const configured = options(list);
    // A record can hold a code that was later removed from the list; keep
    // it selectable so opening the form doesn't silently blank the field.
    if (current !== '' && !configured.some((item) => item.value === current)) {
      return [...configured, { value: current, label: current }];
    }
    return configured;
  }, [options, list, current]);

  const selectValue = current === '' ? (allowEmpty ? EMPTY_VALUE : '') : current;

  return (
    <Select
      value={selectValue}
      onValueChange={(next) => onValueChange(next === EMPTY_VALUE ? '' : next)}
      disabled={disabled}
    >
      <SelectTrigger ref={ref} {...triggerProps}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {allowEmpty && <SelectItem value={EMPTY_VALUE}>{emptyLabel}</SelectItem>}
        {items.map((item) => (
          <SelectItem key={item.value} value={item.value}>
            {showValue && item.label !== item.value ? `${item.label} (${item.value})` : item.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
});
