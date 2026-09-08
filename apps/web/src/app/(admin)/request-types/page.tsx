'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { RequestTypeFormDialog } from '@/components/workflow/request-type-form-dialog';
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import type { RequestType } from '@/lib/api/types';

export default function RequestTypesPage() {
  const { data: requestTypes, isLoading } = useQuery({
    queryKey: ['request-types'],
    queryFn: async () => (await requestTypesApi.list()).data.data,
  });

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingType, setEditingType] = React.useState<RequestType | null>(null);
  const [deletingType, setDeletingType] = React.useState<RequestType | null>(null);

  const sortedTypes = React.useMemo(
    () => [...(requestTypes ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [requestTypes]
  );

  const openCreate = () => {
    setEditingType(null);
    setFormOpen(true);
  };

  const openEdit = (requestType: RequestType) => {
    setEditingType(requestType);
    setFormOpen(true);
  };

  const columns: DataTableColumn<RequestType>[] = [
    { key: 'name', header: 'نوع الطلب', cell: (requestType) => <RequestTypeBadge requestType={requestType} /> },
    {
      key: 'code',
      header: 'الرمز',
      cell: (requestType) => (
        <span className="num" dir="ltr">
          {requestType.code}
        </span>
      ),
    },
    {
      key: 'workflow',
      header: 'مسار العمل',
      cell: (requestType) => requestType.workflow?.name ?? '—',
    },
    {
      key: 'sort_order',
      header: 'الترتيب',
      align: 'center',
      cell: (requestType) => (
        <span className="num" dir="ltr">
          {requestType.sort_order}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'الحالة',
      align: 'center',
      cell: (requestType) => (
        <Badge
          className={
            requestType.is_active
              ? 'border-transparent bg-success-soft text-success'
              : 'border-transparent bg-surface-2 text-muted'
          }
        >
          {requestType.is_active ? 'نشط' : 'غير نشط'}
        </Badge>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          نوع طلب جديد
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">مسارات العمل</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">أنواع الطلبات</h1>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={sortedTypes}
        rowKey={(requestType) => requestType.id}
        isLoading={isLoading}
        emptyMessage="لا توجد أنواع طلبات مسجلة بعد"
        actions={[
          { label: 'تعديل', icon: Pencil, onClick: openEdit },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingType },
        ]}
      />

      <RequestTypeFormDialog open={formOpen} onOpenChange={setFormOpen} requestType={editingType} />

      <DeleteEntityDialog
        open={!!deletingType}
        onOpenChange={(open) => !open && setDeletingType(null)}
        title="حذف نوع الطلب"
        description={
          <>
            هل أنت متأكد من حذف نوع الطلب <span className="font-semibold text-ink">{deletingType?.name}</span>؟ لا يمكن
            التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => requestTypesApi.delete(deletingType!.id)}
        invalidateQueryKey={['request-types']}
        successMessage="تم حذف نوع الطلب بنجاح"
      />
    </div>
  );
}
