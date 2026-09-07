import { cn } from '@/lib/utils';

type ReportStatCardProps = {
  label: string;
  value: React.ReactNode;
  tone?: 'default' | 'danger' | 'success' | 'warn';
};

const TONE_CLASSNAME: Record<NonNullable<ReportStatCardProps['tone']>, string> = {
  default: 'text-ink',
  danger: 'text-danger',
  success: 'text-success',
  warn: 'text-warn-ink',
};

/** Small labeled stat card used to lay out a row of report KPIs. */
export function ReportStatCard({ label, value, tone = 'default' }: ReportStatCardProps) {
  return (
    <div className="rounded-xl border border-hairline bg-surface p-4">
      <p className="text-xs font-medium text-muted">{label}</p>
      <p className={cn('num mt-1.5 text-2xl font-bold', TONE_CLASSNAME[tone])}>{value}</p>
    </div>
  );
}
