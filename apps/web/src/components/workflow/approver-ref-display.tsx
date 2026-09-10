'use client';

import { useQuery } from '@tanstack/react-query';

import { employeesApi } from '@/lib/api/endpoints/employees';
import { ROLE_OPTIONS } from '@/lib/constants/request-options';
import type { WorkflowStep } from '@/lib/api/types';

type ApproverRefDisplayProps = Pick<WorkflowStep, 'approver_type' | 'approver_ref'> & { className?: string };

/**
 * Resolves a workflow step's `approver_ref` into a readable label — an
 * employee name, a role label, or a raw form-field key. Shared by the
 * workflow step editor's cards and the request detail dialog's "who can act
 * on this" line, so the two never describe the same ref differently.
 */
export function ApproverRefDisplay({ approver_type, approver_ref, className }: ApproverRefDisplayProps) {
  const employeeId = approver_type === 'specific_employee' && approver_ref ? Number(approver_ref) : null;

  const { data: employee } = useQuery({
    queryKey: ['employees', 'hydrate', employeeId],
    queryFn: async () => (await employeesApi.get(employeeId as number)).data.data,
    enabled: !!employeeId && !Number.isNaN(employeeId),
  });

  if (approver_type === 'specific_employee') {
    return <span className={className}>{employee ? employee.full_name : approver_ref ? '...جارٍ التحميل' : '—'}</span>;
  }

  if (approver_type === 'specific_role') {
    const roleLabel = ROLE_OPTIONS.find((r) => r.value === approver_ref)?.label ?? approver_ref;
    return <span className={className}>{roleLabel ?? '—'}</span>;
  }

  if (approver_type === 'form_field') {
    return (
      <span className={className} dir="ltr">
        {approver_ref ?? '—'}
      </span>
    );
  }

  return null;
}
