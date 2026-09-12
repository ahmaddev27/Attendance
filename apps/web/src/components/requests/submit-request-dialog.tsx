'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { FormFieldRenderer } from '@/components/requests/form-field-renderer';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { myRequestsApi } from '@/lib/api/endpoints/requests';
import type { EmployeeSummary, FormField, RequestType } from '@/lib/api/types';

function buildDefaultValues(fields: FormField[]): Record<string, unknown> {
  const values: Record<string, unknown> = {};
  fields.forEach((field) => {
    switch (field.type) {
      case 'number':
      case 'employee':
        values[field.key] = null;
        break;
      case 'checkbox':
        values[field.key] = false;
        break;
      default:
        values[field.key] = '';
    }
  });
  return values;
}

/**
 * Client-side mirror of the server's required/min/max rules — kept as a
 * plain function rather than a react-hook-form + zod schema because the
 * field set (and therefore the schema shape) is only known at runtime, one
 * request type at a time.
 */
function validateFormData(fields: FormField[], values: Record<string, unknown>): Record<string, string> {
  const errors: Record<string, string> = {};

  fields.forEach((field) => {
    const value = values[field.key];

    if (field.required && field.type !== 'checkbox') {
      const isEmpty = value === null || value === undefined || value === '';
      if (isEmpty) {
        errors[field.key] = `${field.label} مطلوب`;
        return;
      }
    }

    if (field.type === 'number' && value !== null && value !== undefined && value !== '') {
      const num = Number(value);
      if (field.min != null && num < field.min) errors[field.key] = `القيمة يجب ألا تقل عن ${field.min}`;
      else if (field.max != null && num > field.max) errors[field.key] = `القيمة يجب ألا تزيد عن ${field.max}`;
    }
  });

  return errors;
}

type SubmitRequestDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  requestType: RequestType | null;
  /**
   * When set, the dialog operates in "resubmit" mode against an existing
   * Returned request instead of creating a new one — it pre-fills the
   * form with `initialFormData` and POSTs to /me/requests/{id}/resubmit
   * on submit. Keeping the same dialog for both flows means the form
   * validation and field rendering stay in one place.
   */
  resubmitRequestId?: number | null;
  initialFormData?: Record<string, unknown> | null;
};

/** Employee self-service submission — renders `requestType.form_schema` as a dynamic form. */
export function SubmitRequestDialog({
  open,
  onOpenChange,
  requestType,
  resubmitRequestId,
  initialFormData,
}: SubmitRequestDialogProps) {
  const queryClient = useQueryClient();
  const isResubmit = resubmitRequestId != null;
  const [values, setValues] = React.useState<Record<string, unknown>>({});
  const [employeeSelections, setEmployeeSelections] = React.useState<Record<string, EmployeeSummary | null>>({});
  const [errors, setErrors] = React.useState<Record<string, string>>({});
  const [apiError, setApiError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (open && requestType) {
      const defaults = buildDefaultValues(requestType.form_schema);
      // Merge the returned request's own values on top of the schema's
      // defaults so a field that has since been added to the schema
      // still renders with its default, and every schema-known field
      // still passes the client-side validator on second submit.
      setValues(initialFormData ? { ...defaults, ...initialFormData } : defaults);
      setEmployeeSelections({});
      setErrors({});
      setApiError(null);
    }
  }, [open, requestType, initialFormData]);

  const mutation = useMutation({
    mutationFn: (formData: Record<string, unknown>) =>
      isResubmit
        ? myRequestsApi.resubmit(resubmitRequestId!, formData)
        : myRequestsApi.submit({ request_type_id: requestType!.id, form_data: formData }),
    onSuccess: () => {
      toast.success(isResubmit ? 'تم إعادة تقديم الطلب' : 'تم إرسال الطلب بنجاح');
      queryClient.invalidateQueries({ queryKey: ['my-requests'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const response = (
        err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      )?.response;

      // Laravel validates nested form_data fields as `form_data.<key>` —
      // strip the prefix so each 422 error lands next to its own input.
      if (response?.data?.errors) {
        const fieldErrors: Record<string, string> = {};
        Object.entries(response.data.errors).forEach(([key, messages]) => {
          const fieldKey = key.replace(/^form_data\./, '');
          if (messages[0]) fieldErrors[fieldKey] = messages[0];
        });
        setErrors((prev) => ({ ...prev, ...fieldErrors }));
      }

      setApiError(
        response?.data?.message ||
          (isResubmit ? 'تعذر إعادة تقديم الطلب، حاول مرة أخرى' : 'تعذر إرسال الطلب، حاول مرة أخرى'),
      );
    },
  });

  if (!requestType) return null;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setApiError(null);

    const validationErrors = validateFormData(requestType.form_schema, values);
    setErrors(validationErrors);
    if (Object.keys(validationErrors).length > 0) return;

    mutation.mutate(values);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>
            <RequestTypeBadge requestType={requestType} className="text-base" />
          </DialogTitle>
          {requestType.description && <DialogDescription>{requestType.description}</DialogDescription>}
        </DialogHeader>

        <form onSubmit={submit} className="space-y-4">
          {requestType.form_schema.length === 0 && (
            <p className="text-sm text-muted">لا توجد حقول إضافية لهذا النوع من الطلبات — اضغط إرسال للمتابعة.</p>
          )}

          {requestType.form_schema.map((field) => (
            <FormFieldRenderer
              key={field.key}
              field={field}
              value={values[field.key]}
              onChange={(next) => setValues((prev) => ({ ...prev, [field.key]: next }))}
              error={errors[field.key]}
              employeeValue={employeeSelections[field.key] ?? null}
              onEmployeeChange={(employee) => setEmployeeSelections((prev) => ({ ...prev, [field.key]: employee }))}
            />
          ))}

          {apiError && <p className="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{apiError}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              إلغاء
            </Button>
            <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
              {mutation.isPending && <Spinner className="text-white" />}
              {isResubmit ? 'إعادة تقديم' : 'إرسال الطلب'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
