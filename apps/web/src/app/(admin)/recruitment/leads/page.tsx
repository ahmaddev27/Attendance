'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download, LayoutGrid, Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { LeadStatusBadge } from '@/components/recruitment/status-badges';
import { UserSelect } from '@/components/recruitment/user-select';
import { LeadFormDialog } from '@/app/(admin)/recruitment/leads/_components/lead-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useOptionLists } from '@/hooks/use-option-lists';
import { leadsApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import { LEAD_STATUS_OPTIONS } from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { Lead, LeadSource, LeadStatus, RecruitmentUserOption } from '@/lib/api/types';

const PER_PAGE = 25;

/** Global Leads list with filters, pagination, kanban switch, and CSV export. */
export default function LeadsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-leads');
  const canExport = hasPermission(user, 'export-recruitment-data');
  const { options, labelOf } = useOptionLists();

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<LeadStatus | undefined>();
  const [source, setSource] = React.useState<LeadSource | undefined>();
  const [owner, setOwner] = React.useState<RecruitmentUserOption | null>(null);
  const [country, setCountry] = React.useState('');

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingLead, setEditingLead] = React.useState<Lead | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<Lead | null>(null);

  const debouncedSearch = useDebouncedValue(search);
  const debouncedCountry = useDebouncedValue(country);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status, source, owner?.id, debouncedCountry]);

  const filters = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status,
    source,
    owner_id: owner?.id,
    country: debouncedCountry || undefined,
  };

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['leads', 'list', filters],
    queryFn: async () => (await leadsApi.list(filters)).data,
    placeholderData: keepPreviousData,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => leadsApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف العميل المحتمل');
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر حذف السجل');
    },
  });

  const openCreate = () => {
    setEditingLead(null);
    setFormOpen(true);
  };

  const openEdit = (lead: Lead) => {
    setEditingLead(lead);
    setFormOpen(true);
  };

  const openDetail = (lead: Lead) => router.push(`/recruitment/leads/${lead.id}`);

  const handleExport = async () => {
    try {
      const { data } = await leadsApi.exportCsv(filters);
      const url = URL.createObjectURL(data as Blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `leads-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch {
      toast.error('تعذر تنزيل ملف CSV');
    }
  };

  const columns: DataTableColumn<Lead>[] = [
    {
      key: 'lead_number',
      header: 'الرقم',
      cell: (l) => (
        <button
          type="button"
          onClick={() => openDetail(l)}
          className="num text-start font-medium text-brand-ink hover:underline"
          dir="ltr"
        >
          {l.lead_number}
        </button>
      ),
    },
    {
      key: 'company_name',
      header: 'الشركة',
      cell: (l) => (
        <button
          type="button"
          onClick={() => openDetail(l)}
          className="max-w-[240px] truncate text-start font-medium text-ink hover:underline"
          title={l.company_name}
        >
          {l.company_name}
        </button>
      ),
    },
    {
      key: 'country',
      header: 'الدولة',
      cell: (l) => <span className="text-sm text-ink-2">{l.country ?? '—'}</span>,
    },
    { key: 'source', header: 'المصدر', cell: (l) => <span className="text-sm text-ink-2">{labelOf('lead_sources', l.source)}</span> },
    { key: 'status', header: 'الحالة', cell: (l) => <LeadStatusBadge status={l.status} /> },
    {
      key: 'owner',
      header: 'المالك',
      cell: (l) =>
        l.owner ? (
          <span className="text-sm text-ink" title={l.owner.email}>
            {l.owner.name}
          </span>
        ) : (
          <span className="text-xs text-muted">—</span>
        ),
    },
    {
      key: 'followup',
      header: 'المتابعة',
      cell: (l) =>
        l.next_followup_at ? (
          <span className="num text-xs text-ink-2" dir="ltr">
            {formatDate(l.next_followup_at)}
          </span>
        ) : (
          <span className="text-xs text-muted">—</span>
        ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">العملاء المحتملون</h1>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="outline"
            className="gap-2"
            onClick={() => router.push('/recruitment/leads/kanban')}
          >
            <LayoutGrid className="h-4 w-4" />
            لوحة كانبان
          </Button>
          {canExport && (
            <Button type="button" variant="outline" className="gap-2" onClick={handleExport}>
              <Download className="h-4 w-4" />
              تنزيل CSV
            </Button>
          )}
          {canManage && (
            <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
              <Plus className="h-4 w-4" />
              عميل محتمل جديد
            </Button>
          )}
        </div>
      </div>

      <FilterBar
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="ابحث باسم الشركة أو جهة الاتصال..."
      >
        <FilterSelect
          value={status}
          onChange={(value) => setStatus(value as LeadStatus | undefined)}
          options={LEAD_STATUS_OPTIONS}
          placeholder="الحالة"
          allLabel="كل الحالات"
        />
        <FilterSelect
          value={source}
          onChange={(value) => setSource(value as LeadSource | undefined)}
          options={options('lead_sources')}
          placeholder="المصدر"
          allLabel="كل المصادر"
        />
        <UserSelect
          value={owner}
          onChange={setOwner}
          placeholder="كل المالكين"
          aria-label="المالك"
          className="w-full sm:w-52"
        />
      </FilterBar>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="لا يوجد عملاء محتملون مطابقون للفلاتر"
        actions={
          canManage
            ? [
                { label: 'تعديل', icon: Pencil, onClick: openEdit },
                { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: (l) => setDeleteTarget(l) },
              ]
            : undefined
        }
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />
      {isFetching && !isLoading && <p className="text-xs text-muted">جارٍ التحديث...</p>}

      <LeadFormDialog open={formOpen} onOpenChange={setFormOpen} lead={editingLead} />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف العميل المحتمل</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف {deleteTarget?.company_name}. الإجراء قابل للاسترجاع من قبل الأدمن.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              className="bg-danger text-white hover:bg-danger/90"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
            >
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
