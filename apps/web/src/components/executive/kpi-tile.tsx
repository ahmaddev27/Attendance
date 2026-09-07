import { cn } from '@/lib/utils';

export type KpiStat = {
  label: string;
  value: React.ReactNode;
  tone?: 'default' | 'danger' | 'success' | 'warn';
};

type KpiTileProps = {
  title: string;
  icon: React.ComponentType<{ className?: string }>;
  mainValue: React.ReactNode;
  mainLabel?: string;
  stats?: KpiStat[];
  className?: string;
};

const STAT_TONE_CLASSNAME: Record<NonNullable<KpiStat['tone']>, string> = {
  default: 'text-ink',
  danger: 'text-danger',
  success: 'text-success',
  warn: 'text-warn-ink',
};

/**
 * Row-1 KPI card for the executive dashboard: one headline number plus up to
 * three small supporting stats. No M7 equivalent exists yet, so this is
 * M8's own reusable tile rather than an import from elsewhere.
 */
export function KpiTile({ title, icon: Icon, mainValue, mainLabel, stats, className }: KpiTileProps) {
  return (
    <div className={cn('rounded-xl border border-hairline bg-surface p-5', className)}>
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm font-medium text-ink-2">{title}</p>
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand-ink">
          <Icon className="h-[18px] w-[18px]" />
        </div>
      </div>

      <p className="num mt-3 text-3xl font-bold text-ink">{mainValue}</p>
      {mainLabel && <p className="mt-1 text-xs text-muted">{mainLabel}</p>}

      {stats && stats.length > 0 && (
        <div className="mt-4 grid grid-cols-3 gap-2 border-t border-hairline pt-3">
          {stats.map((stat) => (
            <div key={stat.label} className="min-w-0">
              <p className={cn('num text-sm font-semibold', STAT_TONE_CLASSNAME[stat.tone ?? 'default'])}>
                {stat.value}
              </p>
              <p className="mt-0.5 truncate text-[11px] text-muted">{stat.label}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/** Loading placeholder matching KpiTile's footprint, shown while the dashboard query is in flight. */
export function KpiTileSkeleton() {
  return (
    <div className="animate-pulse rounded-xl border border-hairline bg-surface p-5">
      <div className="flex items-center justify-between gap-3">
        <div className="h-4 w-24 rounded bg-surface-2" />
        <div className="h-9 w-9 rounded-lg bg-surface-2" />
      </div>
      <div className="mt-4 h-8 w-16 rounded bg-surface-2" />
      <div className="mt-4 grid grid-cols-3 gap-2 border-t border-hairline pt-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="h-8 rounded bg-surface-2" />
        ))}
      </div>
    </div>
  );
}
