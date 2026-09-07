import type { EmployeeStatus, EmploymentType, Gender } from '@/lib/api/types';

/**
 * Arabic display labels for the Employee module's fixed enums. Centralized
 * so the table, filters, badges, and the create/edit form all render the
 * exact same wording for a given status/type — one source of truth instead
 * of the label being re-typed at every call site.
 */
export const EMPLOYMENT_TYPE_LABELS: Record<EmploymentType, string> = {
  full_time: 'دوام كامل',
  part_time: 'دوام جزئي',
  contractor: 'متعاقد',
  intern: 'متدرب',
};

export const GENDER_LABELS: Record<Gender, string> = {
  male: 'ذكر',
  female: 'أنثى',
};

export const EMPLOYEE_STATUS_LABELS: Record<EmployeeStatus, string> = {
  active: 'نشط',
  inactive: 'غير نشط',
  on_leave: 'في إجازة',
  terminated: 'منتهي الخدمة',
};

export const EMPLOYMENT_TYPE_OPTIONS = Object.entries(EMPLOYMENT_TYPE_LABELS).map(([value, label]) => ({
  value,
  label,
}));

export const EMPLOYEE_STATUS_OPTIONS = Object.entries(EMPLOYEE_STATUS_LABELS).map(([value, label]) => ({
  value,
  label,
}));
