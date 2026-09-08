'use client';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import type { EmployeeSummary, FormField } from '@/lib/api/types';

type FormFieldRendererProps = {
  field: FormField;
  value: unknown;
  onChange: (value: unknown) => void;
  error?: string;
  /** Only used for the "employee" field type — the picker needs the full record to display a name, not just the stored id. */
  employeeValue?: EmployeeSummary | null;
  onEmployeeChange?: (employee: EmployeeSummary | null) => void;
};

/**
 * Renders one input for a request type's dynamic `form_schema` field. Shared
 * by the submit dialog (editable) — kept field-agnostic so a new
 * `FormFieldType` only needs a new `case` here, not a new dialog.
 */
export function FormFieldRenderer({ field, value, onChange, error, employeeValue, onEmployeeChange }: FormFieldRendererProps) {
  return (
    <div>
      <Label className="text-sm font-medium text-ink">
        {field.label}
        {field.required && <span className="ms-1 text-danger">*</span>}
      </Label>
      <div className="mt-1.5">
        <FieldInput field={field} value={value} onChange={onChange} employeeValue={employeeValue} onEmployeeChange={onEmployeeChange} />
      </div>
      {error && <p className="mt-1 text-xs text-danger">{error}</p>}
    </div>
  );
}

function FieldInput({
  field,
  value,
  onChange,
  employeeValue,
  onEmployeeChange,
}: Omit<FormFieldRendererProps, 'error'>) {
  switch (field.type) {
    case 'text':
      return (
        <Input
          value={(value as string) ?? ''}
          placeholder={field.placeholder}
          onChange={(e) => onChange(e.target.value)}
        />
      );

    case 'textarea':
      return (
        <Textarea
          rows={3}
          value={(value as string) ?? ''}
          placeholder={field.placeholder}
          onChange={(e) => onChange(e.target.value)}
        />
      );

    case 'number':
      return (
        <Input
          type="number"
          min={field.min}
          max={field.max}
          dir="ltr"
          className="num text-right"
          placeholder={field.placeholder}
          value={value === null || value === undefined ? '' : String(value)}
          onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
        />
      );

    case 'date':
      return <Input type="date" dir="ltr" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;

    case 'select':
      return (
        <Select value={(value as string) || undefined} onValueChange={onChange}>
          <SelectTrigger>
            <SelectValue placeholder={field.placeholder || 'اختر...'} />
          </SelectTrigger>
          <SelectContent>
            {(field.options ?? []).map((option) => (
              <SelectItem key={option} value={option}>
                {option}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      );

    case 'checkbox':
      return (
        <div className="flex items-center gap-2 rounded-lg border border-hairline p-2.5">
          <Switch checked={value === true} onCheckedChange={onChange} />
          <span className="text-sm text-ink-2">{value === true ? 'نعم' : 'لا'}</span>
        </div>
      );

    case 'file':
      return (
        <div>
          <Input type="file" onChange={(e) => onChange(e.target.files?.[0]?.name ?? '')} />
          {typeof value === 'string' && value && <p className="mt-1 text-xs text-muted">الملف المحدد: {value}</p>}
        </div>
      );

    case 'employee':
      return (
        <EmployeeSearchSelect
          value={employeeValue ?? null}
          onChange={(employee) => {
            onEmployeeChange?.(employee);
            onChange(employee?.id ?? null);
          }}
          placeholder={field.placeholder || 'اختر موظفاً...'}
        />
      );

    default:
      return null;
  }
}
