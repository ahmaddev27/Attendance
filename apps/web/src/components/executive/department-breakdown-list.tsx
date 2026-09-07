import type { DashboardDepartmentBreakdown } from '@/lib/api/types';

/** Top-5 departments by headcount, rendered as a labeled progress-bar list. */
export function DepartmentBreakdownList({
  departments,
}: {
  departments: DashboardDepartmentBreakdown[];
}) {
  const top5 = [...departments]
    .sort((a, b) => b.employees_count - a.employees_count)
    .slice(0, 5);

  if (top5.length === 0) {
    return <p className="py-10 text-center text-sm text-muted">لا توجد أقسام لعرضها</p>;
  }

  const max = Math.max(1, ...top5.map((dept) => dept.employees_count));

  return (
    <div className="space-y-4">
      {top5.map((dept) => (
        <div key={dept.id}>
          <div className="flex items-center justify-between gap-3 text-sm">
            <span className="truncate text-ink">{dept.name}</span>
            <span className="num shrink-0 font-semibold text-ink-2">{dept.employees_count}</span>
          </div>
          <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-2">
            <div
              className="h-full rounded-full bg-brand"
              style={{ width: `${(dept.employees_count / max) * 100}%` }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}
