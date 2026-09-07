/**
 * Small, dependency-free helpers shared across the leaves feature (M4).
 * Kept separate from src/lib/utils.ts (owned collectively) to avoid touching
 * a file outside this module's ownership — mirrors the attendance-format.ts
 * pattern already used by the M3 attendance module.
 */

/**
 * Client-side *estimate* of working days between two ISO dates (inclusive),
 * skipping Friday/Saturday (the Gulf-region weekend). This only drives the
 * live "عدد الأيام" preview in the submit dialog — the authoritative day
 * count (which accounts for each employee's actual work schedule and public
 * holidays) is always computed server-side when the request is created.
 */
export function estimateWorkingDays(startDate: string, endDate: string): number {
  const start = parseIsoDate(startDate);
  const end = parseIsoDate(endDate);
  if (!start || !end || end < start) return 0;

  let count = 0;
  const cursor = new Date(start);
  while (cursor <= end) {
    const weekday = cursor.getUTCDay(); // 5 = Friday, 6 = Saturday
    if (weekday !== 5 && weekday !== 6) count += 1;
    cursor.setUTCDate(cursor.getUTCDate() + 1);
  }
  return count;
}

function parseIsoDate(value: string): Date | null {
  if (!value) return null;
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return null;
  return new Date(Date.UTC(year, month - 1, day));
}
