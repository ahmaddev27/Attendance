import type { ApprovalAction, ApproverType, FormFieldType, RequestStatus } from '@/lib/api/types';

/**
 * Single source of truth for how a request status is labeled (Arabic) and
 * colored — shared by the status badge, the admin filters, and the
 * employee-facing table. Mirrors `LEAVE_STATUS_META` (M4) so the two
 * workflow-driven modules read consistently.
 */
export const REQUEST_STATUS_META: Record<RequestStatus, { label: string; className: string; dotClassName: string }> = {
  draft: {
    label: 'مسودة',
    className: 'border-transparent bg-surface-2 text-muted',
    dotClassName: 'bg-muted',
  },
  submitted: {
    label: 'مُرسل',
    className: 'border-transparent bg-warn-soft text-warn-ink',
    dotClassName: 'bg-warn',
  },
  pending: {
    label: 'قيد المراجعة',
    className: 'border-transparent bg-warn-soft text-warn-ink',
    dotClassName: 'bg-warn',
  },
  approved: {
    label: 'موافق عليه',
    className: 'border-transparent bg-success-soft text-success',
    dotClassName: 'bg-success',
  },
  rejected: {
    label: 'مرفوض',
    className: 'border-transparent bg-danger-soft text-danger',
    dotClassName: 'bg-danger',
  },
  returned: {
    label: 'مُعاد',
    className: 'border-transparent bg-warn-soft text-warn-ink',
    dotClassName: 'bg-warn',
  },
  cancelled: {
    label: 'ملغى',
    className: 'border-transparent bg-surface-2 text-ink-2',
    dotClassName: 'bg-ink-2',
  },
  completed: {
    label: 'مكتمل',
    className: 'border-transparent bg-brand-soft text-brand-ink',
    dotClassName: 'bg-brand',
  },
};

export const REQUEST_STATUS_LABELS: Record<RequestStatus, string> = Object.fromEntries(
  Object.entries(REQUEST_STATUS_META).map(([status, meta]) => [status, meta.label])
) as Record<RequestStatus, string>;

export const REQUEST_STATUS_OPTIONS = Object.entries(REQUEST_STATUS_LABELS).map(([value, label]) => ({
  value,
  label,
}));

/** Statuses where the request is sitting on some workflow step awaiting a decision. */
export const ACTIONABLE_REQUEST_STATUSES: RequestStatus[] = ['submitted', 'pending'];

/** Statuses an employee is still allowed to cancel their own request from. */
export const CANCELLABLE_REQUEST_STATUSES: RequestStatus[] = ['draft', 'submitted', 'pending'];

export const APPROVER_TYPE_LABELS: Record<ApproverType, string> = {
  direct_manager: 'المدير المباشر',
  department_manager: 'مدير القسم',
  specific_employee: 'موظف محدد',
  specific_role: 'دور محدد',
  form_field: 'حسب حقل في النموذج',
};

export const APPROVER_TYPE_OPTIONS = Object.entries(APPROVER_TYPE_LABELS).map(([value, label]) => ({
  value: value as ApproverType,
  label,
}));

export const APPROVAL_ACTION_LABELS: Record<ApprovalAction, string> = {
  approved: 'موافقة',
  rejected: 'رفض',
  returned: 'إرجاع',
  forwarded: 'تحويل',
};

export const APPROVAL_ACTION_BADGE_CLASSNAME: Record<ApprovalAction, string> = {
  approved: 'border-transparent bg-success-soft text-success',
  rejected: 'border-transparent bg-danger-soft text-danger',
  returned: 'border-transparent bg-warn-soft text-warn-ink',
  forwarded: 'border-transparent bg-brand-soft text-brand-ink',
};

/**
 * The five RBAC roles defined for TAQAT (apps/api database/seeders/RolePermissionSeeder.php).
 * Used for the "specific role" approver picker in the workflow step editor —
 * there's no roles API in the M5 contract, so the fixed role set is mirrored
 * here rather than left as free text.
 */
export const ROLE_OPTIONS = [
  { value: 'super-admin', label: 'مدير عام' },
  { value: 'management', label: 'الإدارة العليا' },
  { value: 'department-manager', label: 'مدير قسم' },
  { value: 'team-leader', label: 'قائد فريق' },
  { value: 'employee', label: 'موظف' },
];

export const FORM_FIELD_TYPE_LABELS: Record<FormFieldType, string> = {
  text: 'نص قصير',
  textarea: 'نص طويل',
  number: 'رقم',
  date: 'تاريخ',
  select: 'قائمة اختيار',
  checkbox: 'خيار (نعم/لا)',
  file: 'ملف',
  employee: 'موظف',
};

export const FORM_FIELD_TYPE_OPTIONS = Object.entries(FORM_FIELD_TYPE_LABELS).map(([value, label]) => ({
  value: value as FormFieldType,
  label,
}));
