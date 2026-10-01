'use client';

import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { companiesApi } from '@/lib/api/endpoints/companies';
import type { Company } from '@/lib/api/types';

type DeleteCompanyDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  company: Company | null;
};

/**
 * Confirmation shell around the shared DeleteEntityDialog — the only
 * company-specific bits are the copy (including the "X قسم" warning when
 * the row still owns departments) and the actual delete endpoint. The
 * server-side guard in CompanyService::delete is the real safety net;
 * this dialog just surfaces the warning up front so the admin isn't
 * surprised by a validation error.
 */
export function DeleteCompanyDialog({ open, onOpenChange, company }: DeleteCompanyDialogProps) {
  const departmentsCount = company?.departments_count ?? 0;

  return (
    <DeleteEntityDialog
      open={open}
      onOpenChange={onOpenChange}
      title="حذف الشركة"
      description={
        <>
          هل أنت متأكد من حذف شركة{' '}
          <span className="font-semibold text-ink">{company?.name}</span>؟
          {departmentsCount > 0 && (
            <>
              {' '}
              <span className="font-semibold text-danger">
                لا يمكن الحذف: الشركة ترتبط حالياً بـ {departmentsCount} قسم. يجب نقل أو حذف
                الأقسام أولاً.
              </span>
            </>
          )}
        </>
      }
      onDelete={() => companiesApi.delete(company!.id)}
      invalidateQueryKey={['companies']}
      successMessage="تم حذف الشركة بنجاح"
    />
  );
}
