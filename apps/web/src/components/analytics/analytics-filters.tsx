'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { CalendarDays, Filter, X } from 'lucide-react';
import { format, startOfMonth, subDays } from 'date-fns';

import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { teamsApi } from '@/lib/api/endpoints/teams';
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { AnalyticsFilters } from '@/lib/api/endpoints/analytics';
import { cn } from '@/lib/utils';

type Preset = 'last-7' | 'last-30' | 'this-month' | 'custom';

/**
 * The four presets cover ~95% of the reporting flow (weekly standup,
 * monthly report, current-month tracking). "Custom" opens the calendar
 * range picker — used sparingly but has to exist for auditors / HR who
 * need to slice a specific incident window.
 */
const PRESETS: Array<{ id: Preset; label: string; compute: () => { from: Date; to: Date } }> = [
  { id: 'last-7', label: 'آخر 7 أيام', compute: () => ({ from: subDays(new Date(), 7), to: new Date() }) },
  { id: 'last-30', label: 'آخر 30 يوم', compute: () => ({ from: subDays(new Date(), 30), to: new Date() }) },
  { id: 'this-month', label: 'هذا الشهر', compute: () => ({ from: startOfMonth(new Date()), to: new Date() }) },
  { id: 'custom', label: 'مخصص', compute: () => ({ from: subDays(new Date(), 30), to: new Date() }) },
];

type Props = {
  value: AnalyticsFilters;
  onChange: (next: AnalyticsFilters) => void;
};

export function AnalyticsFiltersBar({ value, onChange }: Props) {
  const [preset, setPreset] = React.useState<Preset>('last-30');
  const [calOpen, setCalOpen] = React.useState(false);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'analytics-filter'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
  });
  const { data: teams } = useQuery({
    queryKey: ['teams', 'analytics-filter', value.department_id],
    queryFn: async () =>
      (await teamsApi.list({ per_page: 100, department_id: value.department_id, is_active: true })).data.data,
    staleTime: 60_000,
  });
  const { data: employees } = useQuery({
    queryKey: ['employees', 'analytics-filter', value.department_id, value.team_id],
    queryFn: async () =>
      (
        await employeesApi.list({
          per_page: 50,
          department_id: value.department_id,
          team_id: value.team_id,
        })
      ).data.data,
    staleTime: 60_000,
  });

  const applyPreset = (id: Preset) => {
    setPreset(id);
    if (id === 'custom') {
      setCalOpen(true);
      return;
    }
    const { from, to } = PRESETS.find((p) => p.id === id)!.compute();
    onChange({ ...value, from: format(from, 'yyyy-MM-dd'), to: format(to, 'yyyy-MM-dd') });
  };

  const hasAnyFilter = value.department_id || value.team_id || value.employee_id;

  return (
    <div className="rounded-xl border border-hairline bg-surface p-4">
      <div className="flex flex-wrap items-center gap-3">
        {/* Preset pills */}
        <div className="flex flex-wrap items-center gap-1 rounded-lg border border-hairline bg-ground p-1">
          {PRESETS.map((p) => (
            <button
              key={p.id}
              type="button"
              onClick={() => applyPreset(p.id)}
              className={cn(
                'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                preset === p.id
                  ? 'bg-brand text-white shadow-sm'
                  : 'text-ink-2 hover:bg-surface hover:text-ink'
              )}
            >
              {p.label}
            </button>
          ))}
        </div>

        {/* Custom range popover — only interactive when the preset is 'custom' */}
        <Popover open={calOpen} onOpenChange={setCalOpen}>
          <PopoverTrigger asChild>
            <button
              type="button"
              disabled={preset !== 'custom'}
              className={cn(
                'inline-flex items-center gap-2 rounded-lg border border-hairline px-3 py-2 text-xs transition-colors',
                preset === 'custom'
                  ? 'bg-surface text-ink hover:bg-surface-2'
                  : 'cursor-not-allowed bg-ground text-muted'
              )}
            >
              <CalendarDays className="h-3.5 w-3.5" />
              <span className="num" dir="ltr">
                {value.from ?? '—'} → {value.to ?? '—'}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent align="start" className="w-auto p-0">
            <Calendar
              mode="range"
              defaultMonth={value.from ? new Date(value.from) : undefined}
              selected={{
                from: value.from ? new Date(value.from) : undefined,
                to: value.to ? new Date(value.to) : undefined,
              }}
              onSelect={(range) => {
                if (range?.from && range?.to) {
                  onChange({
                    ...value,
                    from: format(range.from, 'yyyy-MM-dd'),
                    to: format(range.to, 'yyyy-MM-dd'),
                  });
                }
              }}
              numberOfMonths={2}
            />
          </PopoverContent>
        </Popover>

        <span className="ms-2 me-2 h-6 w-px bg-hairline" aria-hidden />

        {/* Scoping filters */}
        <div className="flex items-center gap-2">
          <Filter className="h-3.5 w-3.5 text-muted" />
          <Select
            value={value.department_id?.toString() ?? 'all'}
            onValueChange={(v) => onChange({ ...value, department_id: v === 'all' ? undefined : Number(v), team_id: undefined, employee_id: undefined })}
          >
            <SelectTrigger className="h-9 w-40 text-xs">
              <SelectValue placeholder="القسم" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الأقسام</SelectItem>
              {(departments ?? []).map((d) => (
                <SelectItem key={d.id} value={d.id.toString()}>
                  {d.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select
            value={value.team_id?.toString() ?? 'all'}
            onValueChange={(v) => onChange({ ...value, team_id: v === 'all' ? undefined : Number(v), employee_id: undefined })}
          >
            <SelectTrigger className="h-9 w-40 text-xs">
              <SelectValue placeholder="الفريق" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الفرق</SelectItem>
              {(teams ?? []).map((t) => (
                <SelectItem key={t.id} value={t.id.toString()}>
                  {t.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select
            value={value.employee_id?.toString() ?? 'all'}
            onValueChange={(v) => onChange({ ...value, employee_id: v === 'all' ? undefined : Number(v) })}
          >
            <SelectTrigger className="h-9 w-52 text-xs">
              <SelectValue placeholder="الموظف" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الموظفين</SelectItem>
              {(employees ?? []).map((e) => (
                <SelectItem key={e.id} value={e.id.toString()}>
                  {e.full_name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {hasAnyFilter && (
            <button
              type="button"
              onClick={() =>
                onChange({ from: value.from, to: value.to, department_id: undefined, team_id: undefined, employee_id: undefined })
              }
              className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-muted transition-colors hover:bg-surface-2 hover:text-ink"
              title="مسح الفلاتر"
            >
              <X className="h-3 w-3" />
              مسح
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
