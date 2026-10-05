/**
 * Small, dependency-free formatting helpers shared across the attendance
 * feature (list table, monthly summary, kiosk). Kept separate from
 * src/lib/utils.ts (owned collectively, holds only the `cn` helper) to
 * avoid touching a file outside this module's ownership.
 */

/**
 * "512" minutes -> "8:32". Returns a placeholder dash for null/undefined.
 *
 * Switched from "8س 32د" to a plain H:MM separator on 2026-10-04 after
 * the owner spotted "س 42 د 1" appearing on the attendance list — the
 * old format mixed Arabic unit letters (RTL) with digit runs (LTR) and
 * bidi reorder transposed the hour/minute digits around the trailing
 * "د". The colon separator reads identically in any direction, and the
 * zero-padded minutes align cleanly down a column ("8:05", not "8:5").
 */
export function formatMinutesAsHours(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined) return '—';
  const sign = minutes < 0 ? '-' : '';
  const abs = Math.abs(Math.round(minutes));
  const hours = Math.floor(abs / 60);
  const mins = abs % 60;
  return `${sign}${hours}:${String(mins).padStart(2, '0')}`;
}

/** The business timezone every attendance surface is anchored to. */
const ATTENDANCE_DISPLAY_TIMEZONE = 'Asia/Gaza';

/** "2026-09-07T08:03:00Z" -> "08:03 ص", pinned to Asia/Gaza regardless of
 *  the viewer's local timezone.
 *
 * Pinned timezone (added 2026-10-05) because the owner noticed that times
 * were rendering two hours behind what the kiosk actually scanned. The
 * previous behaviour let `toLocaleTimeString` fall back to the browser's
 * TZ, so a server running UTC/Amman combined with a browser clock in a
 * different zone would drift. Attendance always belongs to Gaza, so we
 * force Gaza here and stop depending on the viewer's clock.
 *
 * Uses `numberingSystem: 'latn'` so the digits render as Latin (0-9) rather
 * than Arabic-Indic (٠-٩). The owner spotted that ٩:٠٤ visually read as
 * ٠٤:٩ (hours and minutes swapped) because Arabic-Indic digits are Unicode
 * class "AN" and sequence right-to-left even inside an LTR span, so the
 * trailing "ص" dragged them. Latin digits are class "EN" and always flow
 * left-to-right, so "9:04 ص" renders exactly as read.
 */
export function formatTime(isoDateTime: string | null | undefined): string {
  if (!isoDateTime) return '—';
  const date = new Date(isoDateTime);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleTimeString('ar-SA', {
    hour: '2-digit',
    minute: '2-digit',
    numberingSystem: 'latn',
    timeZone: ATTENDANCE_DISPLAY_TIMEZONE,
  });
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
