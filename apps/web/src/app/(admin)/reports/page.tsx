import { redirect } from 'next/navigation';

/**
 * `/reports` on its own has no index page — the sidebar links directly
 * to each specific report (attendance, leaves, requests). Typing just
 * `/reports` in the address bar used to 404; redirect to the attendance
 * report, which is the one admins hit most often, so the URL stays
 * useful even when accessed directly.
 */
export default function ReportsIndexPage() {
  redirect('/reports/attendance');
}
