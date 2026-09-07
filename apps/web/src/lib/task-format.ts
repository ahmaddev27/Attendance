/**
 * Small, dependency-light formatting helpers shared across the tasks feature
 * (M6). Kept separate from src/lib/utils.ts (owned collectively) to avoid
 * touching a file outside this module's ownership — mirrors the
 * attendance-format.ts / leave-format.ts pattern already used by other
 * modules.
 */
import { formatDistanceToNow } from 'date-fns';
import { arSA } from 'date-fns/locale';

/** "2026-09-05T10:00:00Z" -> "قبل ٣ أيام", relative to now. */
export function formatRelativeTime(isoDateTime: string | null | undefined): string {
  if (!isoDateTime) return '—';
  const date = new Date(isoDateTime);
  if (Number.isNaN(date.getTime())) return '—';
  return formatDistanceToNow(date, { addSuffix: true, locale: arSA });
}

/** "2400" bytes -> "2.3 كيلوبايت"; scales up to ميغابايت/غيغابايت as needed. */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} بايت`;
  const units = ['كيلوبايت', 'ميغابايت', 'غيغابايت'];
  let value = bytes / 1024;
  let unitIndex = 0;
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }
  return `${value.toFixed(1)} ${units[unitIndex]}`;
}

export type DueDateUrgency = 'overdue' | 'soon' | 'normal' | 'none';

/**
 * Classifies a task's due date for color-coded display: overdue (red) once
 * the date has passed, soon (orange) within the next 3 days, normal
 * otherwise. A completed task is never flagged as overdue/soon.
 */
export function getDueDateUrgency(
  dueDate: string | null | undefined,
  completedAt: string | null | undefined
): DueDateUrgency {
  if (!dueDate) return 'none';
  if (completedAt) return 'normal';

  const due = parseIsoDate(dueDate);
  if (!due) return 'none';

  const today = new Date();
  const todayUtc = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  const diffDays = Math.round((due.getTime() - todayUtc) / (1000 * 60 * 60 * 24));

  if (diffDays < 0) return 'overdue';
  if (diffDays <= 3) return 'soon';
  return 'normal';
}

export const DUE_DATE_URGENCY_CLASSNAME: Record<DueDateUrgency, string> = {
  overdue: 'text-danger',
  soon: 'text-warn-ink',
  normal: 'text-ink-2',
  none: 'text-muted',
};

function parseIsoDate(value: string): Date | null {
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return null;
  return new Date(Date.UTC(year, month - 1, day));
}

/**
 * Applies an alpha channel to a `#rrggbb`/`#rgb` hex color, for the soft
 * tinted backgrounds behind status/priority/tag badges (whose base color is
 * an arbitrary hex picked by an admin, unlike the app's own fixed
 * success/warn/danger tokens). Falls back to the raw color if it can't be
 * parsed, so an unexpected value never crashes the render.
 */
export function withAlpha(hex: string, alpha: number): string {
  const normalized = hex.replace('#', '');
  const isShort = normalized.length === 3;
  const full = isShort
    ? normalized
        .split('')
        .map((c) => c + c)
        .join('')
    : normalized;

  if (full.length !== 6) return hex;

  const r = parseInt(full.slice(0, 2), 16);
  const g = parseInt(full.slice(2, 4), 16);
  const b = parseInt(full.slice(4, 6), 16);

  if ([r, g, b].some(Number.isNaN)) return hex;

  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
