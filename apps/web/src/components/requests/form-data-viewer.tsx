'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Paperclip } from 'lucide-react';

import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { formatDate } from '@/lib/attendance-format';
import type { FormField } from '@/lib/api/types';

type FormDataViewerProps = {
  formData: Record<string, unknown>;
  /** The request type's field definitions — used for labels + type-aware formatting when available. */
  schema?: FormField[];
};

/** Read-only key/value rendering of a submitted request's `form_data`, used in both the admin and employee detail views. */
export function FormDataViewer({ formData, schema }: FormDataViewerProps) {
  const schemaKeys = new Set((schema ?? []).map((field) => field.key));
  const extraKeys = Object.keys(formData).filter((key) => !schemaKeys.has(key));

  if ((schema ?? []).length === 0 && extraKeys.length === 0) {
    return <p className="text-sm text-muted">لا توجد بيانات مرفقة مع هذا الطلب</p>;
  }

  return (
    <div className="divide-y divide-hairline rounded-lg border border-hairline">
      {(schema ?? []).map((field) => (
        <FieldRow key={field.key} label={field.label} value={formData[field.key]} type={field.type} />
      ))}
      {extraKeys.map((key) => (
        <FieldRow key={key} label={key} value={formData[key]} />
      ))}
    </div>
  );
}

function FieldRow({ label, value, type }: { label: string; value: unknown; type?: FormField['type'] }) {
  return (
    <div className="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
      <span className="shrink-0 text-xs font-medium text-muted">{label}</span>
      <span className="text-sm text-ink sm:text-left">
        <FieldValue value={value} type={type} />
      </span>
    </div>
  );
}

function FieldValue({ value, type }: { value: unknown; type?: FormField['type'] }) {
  if (value === null || value === undefined || value === '') return <span className="text-muted">—</span>;

  switch (type) {
    case 'checkbox':
      return <span>{value ? 'نعم' : 'لا'}</span>;
    case 'date':
      return <span className="num">{formatDate(String(value))}</span>;
    case 'number':
      return <span className="num">{String(value)}</span>;
    case 'file':
      return (
        <span className="inline-flex items-center gap-1.5">
          <Paperclip className="h-3.5 w-3.5 text-muted" />
          {String(value)}
        </span>
      );
    case 'employee':
      return <EmployeeFieldValue employeeId={Number(value)} />;
    default:
      return <span className="whitespace-pre-wrap">{String(value)}</span>;
  }
}

/** Resolves an "employee" field's stored id into an avatar + name instead of a bare number. */
function EmployeeFieldValue({ employeeId }: { employeeId: number }) {
  const { data: employee, isLoading } = useQuery({
    queryKey: ['employees', 'hydrate', employeeId],
    queryFn: async () => (await employeesApi.get(employeeId)).data.data,
    enabled: Number.isFinite(employeeId),
    staleTime: 60_000,
  });

  if (!Number.isFinite(employeeId)) return <span className="text-muted">—</span>;
  if (isLoading) return <span className="text-muted">...جارٍ التحميل</span>;
  if (!employee) return <span className="num text-muted">{employeeId}</span>;

  return (
    <span className="inline-flex items-center gap-2">
      <EmployeeAvatar employee={employee} size={22} />
      {employee.full_name}
    </span>
  );
}
