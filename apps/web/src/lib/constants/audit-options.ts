/**
 * Filter option lists for the audit log viewer. `subject_type`/`causer_type`
 * values are the fully-qualified model class names Laravel's activity
 * logger stores as-is (see apps/api `app/Models/*`) — kept in one place so
 * the filter dropdown and the "friendly name" rendering in the table/detail
 * dialog never drift apart.
 */
export const AUDIT_SUBJECT_TYPE_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'App\\Models\\Employee', label: 'موظف' },
  { value: 'App\\Models\\Department', label: 'قسم' },
  { value: 'App\\Models\\Team', label: 'فريق' },
  { value: 'App\\Models\\Position', label: 'مسمى وظيفي' },
  { value: 'App\\Models\\Attendance', label: 'حضور' },
  { value: 'App\\Models\\WorkSchedule', label: 'جدول عمل' },
  { value: 'App\\Models\\Holiday', label: 'عطلة' },
  { value: 'App\\Models\\LeaveRequest', label: 'طلب إجازة' },
  { value: 'App\\Models\\LeaveType', label: 'نوع إجازة' },
  { value: 'App\\Models\\Request', label: 'طلب' },
  { value: 'App\\Models\\RequestType', label: 'نوع طلب' },
  { value: 'App\\Models\\Workflow', label: 'مسار عمل' },
  { value: 'App\\Models\\Task', label: 'مهمة' },
  { value: 'App\\Models\\User', label: 'مستخدم' },
];

export const AUDIT_EVENT_META: Record<string, { label: string; className: string }> = {
  created: { label: 'إنشاء', className: 'border-transparent bg-success-soft text-success' },
  updated: { label: 'تعديل', className: 'border-transparent bg-brand-soft text-brand-ink' },
  deleted: { label: 'حذف', className: 'border-transparent bg-danger-soft text-danger' },
  restored: { label: 'استعادة', className: 'border-transparent bg-warn-soft text-warn-ink' },
  login: { label: 'تسجيل دخول', className: 'border-transparent bg-surface-2 text-ink-2' },
  logout: { label: 'تسجيل خروج', className: 'border-transparent bg-surface-2 text-ink-2' },
};

export const AUDIT_EVENT_OPTIONS = Object.entries(AUDIT_EVENT_META).map(([value, meta]) => ({
  value,
  label: meta.label,
}));

/** "App\Models\LeaveRequest" -> "LeaveRequest", for the subject/causer columns. */
export function getModelBasename(fqcn: string | null | undefined): string {
  if (!fqcn) return '—';
  const parts = fqcn.split('\\');
  return parts[parts.length - 1] || fqcn;
}

/** Friendly Arabic label for a subject/causer type, falling back to its basename. */
export function getSubjectTypeLabel(fqcn: string | null | undefined): string {
  if (!fqcn) return '—';
  const known = AUDIT_SUBJECT_TYPE_OPTIONS.find((opt) => opt.value === fqcn);
  return known?.label ?? getModelBasename(fqcn);
}
