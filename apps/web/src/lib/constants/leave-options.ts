import type { LeaveStatus } from '@/lib/api/types';

/**
 * Single source of truth for how a leave request status is labeled (Arabic)
 * and colored — shared by the status badge, the admin filters, and the
 * employee-facing table so wording/color never drifts between them.
 */
export const LEAVE_STATUS_META: Record<LeaveStatus, { label: string; className: string; dotClassName: string }> = {
  draft: {
    label: 'مسودة',
    className: 'border-transparent bg-surface-2 text-muted',
    dotClassName: 'bg-muted',
  },
  pending: {
    label: 'قيد المراجعة',
    className: 'border-transparent bg-warn-soft text-warn-ink',
    dotClassName: 'bg-warn',
  },
  approved: {
    label: 'موافق عليها',
    className: 'border-transparent bg-success-soft text-success',
    dotClassName: 'bg-success',
  },
  rejected: {
    label: 'مرفوضة',
    className: 'border-transparent bg-danger-soft text-danger',
    dotClassName: 'bg-danger',
  },
  cancelled: {
    label: 'ملغاة',
    className: 'border-transparent bg-surface-2 text-ink-2',
    dotClassName: 'bg-ink-2',
  },
};

export const LEAVE_STATUS_LABELS: Record<LeaveStatus, string> = Object.fromEntries(
  Object.entries(LEAVE_STATUS_META).map(([status, meta]) => [status, meta.label])
) as Record<LeaveStatus, string>;

export const LEAVE_STATUS_OPTIONS = Object.entries(LEAVE_STATUS_LABELS).map(([value, label]) => ({
  value,
  label,
}));
