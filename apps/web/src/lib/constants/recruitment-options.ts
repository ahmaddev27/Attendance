import type {
  CasePriority,
  ClientStatus,
  JobEmploymentType,
  JobRequirementStatus,
  LeadActivityType,
  LeadStatus,
  RecruitmentCaseStatus,
  StageOwnerRuleType,
  WorkMode,
} from '@/lib/api/types';

/**
 * Central source of truth for Recruitment enum labels + tone classes.
 * A single import keeps the badge, filter, and kanban column labels in
 * lockstep — a change here propagates everywhere.
 */

export const LEAD_STATUS_META: Record<LeadStatus, { label: string; className: string; dotClassName: string }> = {
  new: { label: 'جديد', className: 'border-transparent bg-brand-soft text-brand-ink', dotClassName: 'bg-brand' },
  contacted: { label: 'تم التواصل', className: 'border-transparent bg-surface-2 text-ink-2', dotClassName: 'bg-ink-2' },
  meeting_scheduled: { label: 'مقابلة مجدولة', className: 'border-transparent bg-warn-soft text-warn-ink', dotClassName: 'bg-warn' },
  meeting_completed: { label: 'مقابلة تمت', className: 'border-transparent bg-warn-soft text-warn-ink', dotClassName: 'bg-warn' },
  qualified: { label: 'مؤهَّل', className: 'border-transparent bg-brand-soft text-brand-ink', dotClassName: 'bg-brand' },
  proposal_sent: { label: 'عرض مُرسل', className: 'border-transparent bg-brand-soft text-brand-ink', dotClassName: 'bg-brand' },
  negotiation: { label: 'تفاوض', className: 'border-transparent bg-warn-soft text-warn-ink', dotClassName: 'bg-warn' },
  converted: { label: 'تم التحويل', className: 'border-transparent bg-success-soft text-success', dotClassName: 'bg-success' },
  lost: { label: 'خاسر', className: 'border-transparent bg-danger-soft text-danger', dotClassName: 'bg-danger' },
  on_hold: { label: 'مُعلَّق', className: 'border-transparent bg-surface-2 text-muted', dotClassName: 'bg-muted' },
};

export const LEAD_STATUS_OPTIONS: { value: LeadStatus; label: string }[] = (
  Object.keys(LEAD_STATUS_META) as LeadStatus[]
).map((value) => ({ value, label: LEAD_STATUS_META[value].label }));

/** Column order for the Kanban board — mirrors backend LeadStatus enum. */
export const LEAD_KANBAN_STATUSES: LeadStatus[] = [
  'new',
  'contacted',
  'meeting_scheduled',
  'meeting_completed',
  'qualified',
  'proposal_sent',
  'negotiation',
  'on_hold',
];

export const CLIENT_STATUS_META: Record<ClientStatus, { label: string; className: string }> = {
  active: { label: 'نشط', className: 'border-transparent bg-success-soft text-success' },
  on_hold: { label: 'مُعلَّق', className: 'border-transparent bg-warn-soft text-warn-ink' },
  inactive: { label: 'غير نشط', className: 'border-transparent bg-surface-2 text-ink-2' },
  terminated: { label: 'مُنتهٍ', className: 'border-transparent bg-danger-soft text-danger' },
};

export const CLIENT_STATUS_OPTIONS: { value: ClientStatus; label: string }[] = (
  Object.keys(CLIENT_STATUS_META) as ClientStatus[]
).map((value) => ({ value, label: CLIENT_STATUS_META[value].label }));

export const CASE_STATUS_META: Record<RecruitmentCaseStatus, { label: string; className: string }> = {
  draft: { label: 'مسودة', className: 'border-transparent bg-surface-2 text-muted' },
  active: { label: 'نشط', className: 'border-transparent bg-brand-soft text-brand-ink' },
  on_hold: { label: 'مُعلَّق', className: 'border-transparent bg-warn-soft text-warn-ink' },
  completed: { label: 'مكتمل', className: 'border-transparent bg-success-soft text-success' },
  cancelled: { label: 'ملغى', className: 'border-transparent bg-danger-soft text-danger' },
};

export const CASE_STATUS_OPTIONS: { value: RecruitmentCaseStatus; label: string }[] = (
  Object.keys(CASE_STATUS_META) as RecruitmentCaseStatus[]
).map((value) => ({ value, label: CASE_STATUS_META[value].label }));

export const JOB_STATUS_META: Record<JobRequirementStatus, { label: string; className: string }> = {
  draft: { label: 'مسودة', className: 'border-transparent bg-surface-2 text-muted' },
  active: { label: 'نشط', className: 'border-transparent bg-brand-soft text-brand-ink' },
  on_hold: { label: 'مُعلَّق', className: 'border-transparent bg-warn-soft text-warn-ink' },
  filled: { label: 'مُشغَّل', className: 'border-transparent bg-success-soft text-success' },
  cancelled: { label: 'ملغى', className: 'border-transparent bg-danger-soft text-danger' },
};

export const JOB_STATUS_OPTIONS: { value: JobRequirementStatus; label: string }[] = (
  Object.keys(JOB_STATUS_META) as JobRequirementStatus[]
).map((value) => ({ value, label: JOB_STATUS_META[value].label }));

export const CASE_PRIORITY_LABELS: Record<CasePriority, string> = {
  low: 'منخفضة',
  normal: 'عادية',
  high: 'مرتفعة',
  urgent: 'عاجلة',
};

export const CASE_PRIORITY_OPTIONS: { value: CasePriority; label: string }[] = (
  Object.keys(CASE_PRIORITY_LABELS) as CasePriority[]
).map((value) => ({ value, label: CASE_PRIORITY_LABELS[value] }));

export const EMPLOYMENT_TYPE_LABELS: Record<JobEmploymentType, string> = {
  full_time: 'دوام كامل',
  part_time: 'دوام جزئي',
  contract: 'عقد',
  intern: 'تدريب',
  temporary: 'مؤقت',
};

export const EMPLOYMENT_TYPE_OPTIONS: { value: JobEmploymentType; label: string }[] = (
  Object.keys(EMPLOYMENT_TYPE_LABELS) as JobEmploymentType[]
).map((value) => ({ value, label: EMPLOYMENT_TYPE_LABELS[value] }));

export const WORK_MODE_LABELS: Record<WorkMode, string> = {
  remote: 'عن بُعد',
  onsite: 'في الموقع',
  hybrid: 'هجين',
};

export const WORK_MODE_OPTIONS: { value: WorkMode; label: string }[] = (
  Object.keys(WORK_MODE_LABELS) as WorkMode[]
).map((value) => ({ value, label: WORK_MODE_LABELS[value] }));

export const LEAD_ACTIVITY_TYPE_LABELS: Record<LeadActivityType, string> = {
  call: 'مكالمة',
  meeting: 'اجتماع',
  email: 'بريد',
  note: 'ملاحظة',
};

export const LEAD_ACTIVITY_TYPE_OPTIONS: { value: LeadActivityType; label: string }[] = (
  Object.keys(LEAD_ACTIVITY_TYPE_LABELS) as LeadActivityType[]
).map((value) => ({ value, label: LEAD_ACTIVITY_TYPE_LABELS[value] }));

export const STAGE_OWNER_RULE_LABELS: Record<StageOwnerRuleType, string> = {
  role: 'حسب صلاحية',
  specific: 'مستخدم محدد',
  case_owner: 'مالك الحملة',
  job_owner: 'مالك الوظيفة',
  previous_stage_owner: 'مالك المرحلة السابقة',
  none: 'بدون مهمة',
};

export const STAGE_OWNER_RULE_OPTIONS: { value: StageOwnerRuleType; label: string }[] = (
  Object.keys(STAGE_OWNER_RULE_LABELS) as StageOwnerRuleType[]
).map((value) => ({ value, label: STAGE_OWNER_RULE_LABELS[value] }));
