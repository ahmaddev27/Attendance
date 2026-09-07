'use client';

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

type FilterOption = { value: string; label: string };

type FilterSelectProps = {
  value: string | undefined;
  onChange: (value: string | undefined) => void;
  options: FilterOption[];
  placeholder: string;
  allLabel?: string;
  className?: string;
};

const ALL_VALUE = '__all__';

/**
 * A `Select` pre-wired with an "all / no filter" option, used for every
 * department/team/status/employment-type filter across the M2 list pages.
 * `value === undefined` means "no filter applied" — kept out of the query
 * string entirely rather than sent as an empty/sentinel value.
 */
export function FilterSelect({
  value,
  onChange,
  options,
  placeholder,
  allLabel = 'الكل',
  className,
}: FilterSelectProps) {
  return (
    <Select value={value ?? ALL_VALUE} onValueChange={(v) => onChange(v === ALL_VALUE ? undefined : v)}>
      <SelectTrigger className={className ?? 'w-full sm:w-44'}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={ALL_VALUE}>{allLabel}</SelectItem>
        {options.map((opt) => (
          <SelectItem key={opt.value} value={opt.value}>
            {opt.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
