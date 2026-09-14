'use client';

import * as React from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { History, X } from 'lucide-react';

import { FilterSelect } from '@/components/data-table/filter-select';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Skeleton } from '@/components/ui/skeleton';
import { auditLogApi, type AuditActivity } from '@/lib/api/endpoints/audit-log';

type FilterOption = { value: string; label: string };

// Values are matched exactly by the API: subject_type is the stored model
// class and log_name is the channel each model or listener writes under.
const SUBJECT_TYPE_OPTIONS: FilterOption[] = [
  { value: 'App\\Models\\Employee', label: 'موظف' },
  { value: 'App\\Models\\User', label: 'حساب مستخدم' },
  { value: 'App\\Models\\LeaveRequest', label: 'طلب إجازة' },
  { value: 'App\\Models\\LeaveBalance', label: 'رصيد إجازة' },
  { value: 'App\\Models\\LeaveType', label: 'نوع إجازة' },
  { value: 'App\\Models\\Request', label: 'طلب' },
  { value: 'App\\Models\\RequestApproval', label: 'قرار على طلب' },
  { value: 'App\\Models\\RequestType', label: 'نوع طلب' },
  { value: 'App\\Models\\Workflow', label: 'مسار عمل' },
  { value: 'App\\Models\\WorkflowStep', label: 'خطوة مسار عمل' },
  { value: 'App\\Models\\Department', label: 'قسم' },
  { value: 'App\\Models\\Team', label: 'فريق' },
  { value: 'App\\Models\\Position', label: 'مسمى وظيفي' },
  { value: 'App\\Models\\WorkSchedule', label: 'جدول دوام' },
  { value: 'App\\Models\\Holiday', label: 'عطلة' },
  { value: 'App\\Models\\AttendanceDevice', label: 'جهاز QR' },
  { value: 'App\\Models\\Lead', label: 'عميل محتمل' },
  { value: 'App\\Models\\Client', label: 'عميل' },
  { value: 'App\\Models\\RecruitmentCase', label: 'حملة توظيف' },
  { value: 'App\\Models\\JobRequirement', label: 'وظيفة' },
];

const LOG_NAME_OPTIONS: FilterOption[] = [
  { value: 'auth', label: 'تسجيل الدخول والخروج' },
  { value: 'users', label: 'الحسابات والأدوار' },
  { value: 'employees', label: 'الموظفون' },
  { value: 'organization', label: 'الهيكل التنظيمي' },
  { value: 'attendance', label: 'رموز الحضور' },
  { value: 'leaves', label: 'الإجازات' },
  { value: 'leave-balance', label: 'تعديلات أرصدة الإجازات' },
  { value: 'requests', label: 'الطلبات والموافقات' },
  { value: 'workflows', label: 'مسارات العمل' },
  { value: 'settings', label: 'الإعدادات' },
  { value: 'recruitment.lead', label: 'العملاء المحتملون' },
  { value: 'recruitment.client', label: 'العملاء' },
  { value: 'recruitment.case', label: 'الحملات' },
  { value: 'recruitment.job_requirement', label: 'الوظائف' },
];

export default function AuditLogPage() {
  const [page, setPage] = React.useState(1);
  const [filters, setFilters] = React.useState<{
    subject_type?: string;
    log_name?: string;
    from?: string;
    to?: string;
  }>({});

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['audit-log', { page, filters }],
    queryFn: async () =>
      (await auditLogApi.list({ page, per_page: 30, ...filters })).data,
    placeholderData: keepPreviousData,
  });

  const activities: AuditActivity[] = data?.data ?? [];
  const meta = data?.meta;

  const setFilter = <K extends keyof typeof filters>(key: K, value: (typeof filters)[K]) => {
    setPage(1);
    setFilters((f) => ({ ...f, [key]: value }));
  };
  const clearFilters = () => {
    setFilters({});
    setPage(1);
  };
  const hasFilters = Object.values(filters).some(Boolean);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
          <History className="h-6 w-6 text-brand" /> سجل النشاط
        </h1>
        <p className="mt-1 text-sm text-muted">كل حركة يقوم بها المستخدمون على النظام مسجّلة هنا.</p>
      </div>

      <Card className="border-hairline bg-surface p-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">التصنيف</span>
            <FilterSelect
              value={filters.log_name}
              options={LOG_NAME_OPTIONS}
              onChange={(v) => setFilter('log_name', v)}
              placeholder="الكل"
              className="w-full"
            />
          </label>
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">النوع</span>
            <FilterSelect
              value={filters.subject_type}
              options={SUBJECT_TYPE_OPTIONS}
              onChange={(v) => setFilter('subject_type', v)}
              placeholder="الكل"
              className="w-full"
            />
          </label>
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">من</span>
            <DatePicker
              value={filters.from ?? ''}
              onChange={(v) => setFilter('from', v || undefined)}
              placeholder="اختر تاريخاً"
              max={filters.to || undefined}
            />
          </label>
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">إلى</span>
            <DatePicker
              value={filters.to ?? ''}
              onChange={(v) => setFilter('to', v || undefined)}
              placeholder="اختر تاريخاً"
              min={filters.from || undefined}
            />
          </label>
          {hasFilters && (
            <div className="flex items-end">
              <Button type="button" variant="outline" size="sm" onClick={clearFilters}>
                <X className="me-1 h-4 w-4" /> مسح الفلاتر
              </Button>
            </div>
          )}
        </div>
      </Card>

      <Card className="border-hairline bg-surface p-0">
        {isError ? (
          <div className="p-8 text-center text-sm text-danger">
            تعذّر تحميل السجل.{' '}
            <button onClick={() => refetch()} className="font-semibold underline">
              إعادة المحاولة
            </button>
          </div>
        ) : isLoading ? (
          <div className="space-y-3 p-6">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : activities.length === 0 ? (
          <div className="grid place-items-center gap-2 py-16 text-center text-muted">
            <History className="h-8 w-8 opacity-40" />
            <p className="text-sm">لا يوجد نشاط بعد.</p>
          </div>
        ) : (
          <ul className="divide-y divide-hairline">
            {activities.map((a) => (
              <li key={a.id} className="p-4">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex-1">
                    <p className="text-sm text-ink">
                      <span className="font-semibold">{a.causer?.name ?? 'نظام'}</span>{' '}
                      <span className="text-ink-2">— {a.description || a.event || '...'}</span>
                    </p>
                    <p className="mt-0.5 text-xs text-muted">
                      {a.subject_type && (
                        <span className="me-2 rounded bg-surface-2 px-1.5 py-0.5 text-[10px] font-medium text-ink-2">
                          {a.subject_type}
                          {a.subject_id !== null && (
                            <span className="num" dir="ltr">
                              {' #'}
                              {a.subject_id}
                            </span>
                          )}
                        </span>
                      )}
                      {a.log_name && <span className="text-muted">{a.log_name}</span>}
                    </p>
                  </div>
                  {a.created_at && (
                    <p className="num text-[11px] text-muted" dir="ltr">
                      {new Date(a.created_at).toLocaleString('ar-EG')}
                    </p>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-ink-2">
          <span>
            صفحة <span className="num" dir="ltr">{meta.current_page}</span> من{' '}
            <span className="num" dir="ltr">{meta.last_page}</span>
          </span>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
              السابق
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= (meta.last_page ?? 1)}
              onClick={() => setPage((p) => p + 1)}
            >
              التالي
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
