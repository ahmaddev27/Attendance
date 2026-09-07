import { formatDistanceToNow } from 'date-fns';
import { ar } from 'date-fns/locale';

/**
 * Small, dependency-light helpers shared across the workflow/requests
 * feature (M5). Kept separate from src/lib/utils.ts (owned collectively) —
 * mirrors the attendance-format.ts / leave-format.ts pattern already used by
 * the M3/M4 modules.
 */

/** Full ISO datetime ("2026-09-07T08:03:00Z") -> "٧ سبتمبر ٢٠٢٦، ٠٨:٠٣ ص". */
export function formatDateTime(isoDateTime: string | null | undefined): string {
  if (!isoDateTime) return '—';
  const date = new Date(isoDateTime);
  if (Number.isNaN(date.getTime())) return '—';
  return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

/** Full ISO datetime -> "منذ ٣ ساعات", for the approvals inbox cards. */
export function formatRelativeTime(isoDateTime: string | null | undefined): string {
  if (!isoDateTime) return '—';
  const date = new Date(isoDateTime);
  if (Number.isNaN(date.getTime())) return '—';
  return formatDistanceToNow(date, { addSuffix: true, locale: ar });
}
