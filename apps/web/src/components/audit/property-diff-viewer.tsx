import type { AuditLogEntry } from '@/lib/api/types';
import { cn } from '@/lib/utils';

/** Best-effort readable rendering for a diff cell of unknown type. */
function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? 'نعم' : 'لا';
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

function humanizeField(key: string): string {
  return key.replace(/_/g, ' ');
}

/**
 * Renders `properties.old` vs `properties.attributes` side-by-side, one row
 * per changed field — removed values highlighted red, added/changed values
 * highlighted green. Falls back to a raw JSON dump when the activity
 * carries neither key (e.g. a login/logout event with custom properties).
 */
export function PropertyDiffViewer({ properties }: { properties: AuditLogEntry['properties'] }) {
  const oldValues = properties.old ?? {};
  const newValues = properties.attributes ?? {};
  const keys = Array.from(new Set([...Object.keys(oldValues), ...Object.keys(newValues)])).sort();

  if (keys.length === 0) {
    const rest = Object.fromEntries(
      Object.entries(properties).filter(([key]) => key !== 'old' && key !== 'attributes')
    );

    if (Object.keys(rest).length === 0) {
      return <p className="py-6 text-center text-sm text-muted">لا توجد تفاصيل إضافية لهذا الحدث</p>;
    }

    return (
      <pre className="overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs text-ink-2" dir="ltr">
        {JSON.stringify(rest, null, 2)}
      </pre>
    );
  }

  return (
    <div className="overflow-hidden rounded-lg border border-hairline">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-hairline bg-surface-2 text-xs font-semibold text-ink-2">
            <th className="px-3 py-2 text-right">الحقل</th>
            <th className="px-3 py-2 text-right">القيمة السابقة</th>
            <th className="px-3 py-2 text-right">القيمة الجديدة</th>
          </tr>
        </thead>
        <tbody>
          {keys.map((key) => {
            const hasOld = key in oldValues;
            const hasNew = key in newValues;
            const oldDisplay = formatValue(oldValues[key]);
            const newDisplay = formatValue(newValues[key]);
            const changed = hasOld && hasNew && oldDisplay !== newDisplay;

            return (
              <tr key={key} className="border-b border-hairline last:border-b-0">
                <td className="px-3 py-2 align-top font-medium text-ink">{humanizeField(key)}</td>
                <td
                  className={cn(
                    'px-3 py-2 align-top',
                    (changed || (hasOld && !hasNew)) && 'bg-danger-soft text-danger'
                  )}
                >
                  {hasOld ? oldDisplay : '—'}
                </td>
                <td
                  className={cn(
                    'px-3 py-2 align-top',
                    (changed || (hasNew && !hasOld)) && 'bg-success-soft text-success'
                  )}
                >
                  {hasNew ? newDisplay : '—'}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
