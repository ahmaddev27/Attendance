'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

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
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { ClientStatusBadge } from '@/components/recruitment/status-badges';
import { ClientFormDialog } from '@/app/(admin)/recruitment/clients/_components/client-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useOptionLists } from '@/hooks/use-option-lists';
import { clientsApi } from '@/lib/api/endpoints/recruitment';
import { CLIENT_STATUS_OPTIONS } from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { Client, ClientStatus } from '@/lib/api/types';

const PER_PAGE = 25;

export default function ClientsPage() {
  const router = useRouter();
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-clients');
  const { labelOf } = useOptionLists();

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<ClientStatus | 'all'>('all');
  const [country, setCountry] = React.useState('');

  const [formOpen, setFormOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<Client | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<Client | null>(null);

  const debouncedSearch = useDebouncedValue(search);
  const debouncedCountry = useDebouncedValue(country);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status, debouncedCountry]);

  const filters = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status === 'all' ? undefined : status,
    country: debouncedCountry || undefined,
  };

  const { data, isLoading } = useQuery({
    queryKey: ['clients', 'list', filters],
    queryFn: async () => (await clientsApi.list(filters)).data,
    placeholderData: keepPreviousData,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => clientsApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف العميل');
      qc.invalidateQueries({ queryKey: ['clients'] });
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر حذف السجل');
    },
  });

  const columns: DataTableColumn<Client>[] = [
    {
      key: 'client_number',
      header: 'الرقم',
      cell: (c) => (
        <button
          type="button"
          onClick={() => router.push(`/recruitment/clients/${c.id}`)}
          className="num text-start font-medium text-brand-ink hover:underline"
          dir="ltr"
        >
          {c.client_number}
        </button>
      ),
    },
    {
      key: 'company_name',
      header: 'الشركة',
      cell: (c) => (
        <button
          type="button"
          onClick={() => router.push(`/recruitment/clients/${c.id}`)}
          className="max-w-[240px] truncate text-start font-medium text-ink hover:underline"
          title={c.company_name}
        >
          {c.company_name}
        </button>
      ),
    },
    { key: 'country', header: 'الدولة', cell: (c) => <span className="text-sm text-ink-2">{c.country ?? '—'}</span> },
    { key: 'industry', header: 'القطاع', cell: (c) => <span className="text-sm text-ink-2">{labelOf('industries', c.industry) ?? '—'}</span> },
    { key: 'status', header: 'الحالة', cell: (c) => <ClientStatusBadge status={c.status} /> },
    {
      key: 'account_manager',
      header: 'مدير الحساب',
      cell: (c) => c.account_manager?.name ?? <span className="text-xs text-muted">—</span>,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">العملاء</h1>
        </div>
        {canManage && (
          <Button
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
            className="gap-2 bg-brand text-white hover:bg-brand-hover"
          >
            <Plus className="h-4 w-4" />
            عميل جديد
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-4">
        <div className="sm:col-span-2">
          <Label className="text-xs font-semibold text-ink-2">بحث</Label>
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="ابحث باسم الشركة..." className="mt-1.5" />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
          <Select value={status} onValueChange={(v) => setStatus(v as ClientStatus | 'all')}>
            <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الحالات</SelectItem>
              {CLIENT_STATUS_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الدولة</Label>
          <Input value={country} onChange={(e) => setCountry(e.target.value)} placeholder="مثال: الأردن" className="mt-1.5" />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="لا يوجد عملاء مطابقون"
        actions={
          canManage
            ? [
                { label: 'تعديل', icon: Pencil, onClick: (c) => { setEditing(c); setFormOpen(true); } },
                { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeleteTarget },
              ]
            : undefined
        }
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <ClientFormDialog open={formOpen} onOpenChange={setFormOpen} client={editing} />
      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف العميل</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف {deleteTarget?.company_name}. لا يمكن الحذف إن كان مرتبطاً بحملات.
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
