/**
 * Small, dependency-free formatting helpers shared across the attendance
 * feature (list table, monthly summary, kiosk). Kept separate from
 * src/lib/utils.ts (owned collectively, holds only the `cn` helper) to
 * avoid touching a file outside this module's ownership.
 */

/**
 * "512" minutes -> "8س 32د". Returns a placeholder dash for null/undefined.
 *
 * The caller MUST render this string inside an element that either has the
 * `.num` class or an explicit `dir="ltr"`. Otherwise the surrounding RTL
 * paragraph will reshuffle the hours/minutes runs (bidi neutrals like
 * spaces flip toward the Arabic letters, producing "س4د8"). We also use
 * a non-breaking space between number and unit so the pair never wraps
 * onto two lines mid-value.
 */
export function formatMinutesAsHours(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined) return '—';
  const sign = minutes < 0 ? '-' : '';
  const abs = Math.abs(Math.round(minutes));
  const hours = Math.floor(abs / 60);
  const mins = abs % 60;
  // U+00A0 non-breaking space keeps the digit + Arabic unit letter together.
  return `${sign}${hours} س ${mins} د`;
}

/** "2026-09-07T08:03:00Z" -> "08:03 ص", using the viewer's locale/timezone. */
export function formatTime(isoDateTime: string | null | undefined): string {
  if (!isoDateTime) return '—';
  const date = new Date(isoDateTime);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
}

/** Formats an ISO date ("2026-09-07") for display without timezone drift.
 *  Also accepts full ISO datetime strings ("2026-09-07T08:03:00+00:00") — the
 *  date portion is sliced off first so the year/month/day parse cleanly. */
export function formatDate(isoDate: string | null | undefined): string {
  if (!isoDate) return '—';
  // Full datetime strings ("2026-09-07T..." or "2026-09-07 08:03:00") would
  // otherwise put the time zone junk into the `day` field and NaN it out.
  const dateOnly = isoDate.length > 10 ? isoDate.slice(0, 10) : isoDate;
  const [year, month, day] = dateOnly.split('-').map(Number);
  if (!year || !month || !day) return isoDate;
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.toLocaleDateString('ar-SA', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
}

/** Weekday label for an ISO date ("2026-09-07") -> "الاثنين". */
export function formatWeekday(isoDate: string | null | undefined): string {
  if (!isoDate) return '—';
  const [year, month, day] = isoDate.split('-').map(Number);
  if (!year || !month || !day) return '—';
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.toLocaleDateString('ar-SA', { weekday: 'long', timeZone: 'UTC' });
}

export const ARABIC_WEEKDAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
export const ARABIC_WEEKDAYS_SHORT = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

/** Returns the ISO ("YYYY-MM-DD") first and last day of a given month. */
export function monthRange(year: number, month: number): { from: string; to: string } {
  const pad = (n: number) => String(n).padStart(2, '0');
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
  return {
    from: `${year}-${pad(month)}-01`,
    to: `${year}-${pad(month)}-${pad(lastDay)}`,
  };
}
