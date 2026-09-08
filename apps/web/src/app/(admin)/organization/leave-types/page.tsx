'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { LeaveTypeFormDialog } from '@/components/leaves/leave-type-form-dialog';
import { leaveTypesApi } from '@/lib/api/endpoints/leave-types';
import type { LeaveType } from '@/lib/api/types';

export default function LeaveTypesPage() {
  const { data: leaveTypes, isLoading } = useQuery({
    queryKey: ['leave-types'],
    queryFn: async () => (await leaveTypesApi.list()).data.data,
  });

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingType, setEditingType] = React.useState<LeaveType | null>(null);
  const [deletingType, setDeletingType] = React.useState<LeaveType | null>(null);

  const sortedTypes = React.useMemo(
    () => [...(leaveTypes ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [leaveTypes]
  );

  const openCreate = () => {
    setEditingType(null);
    setFormOpen(true);
  };

  const openEdit = (leaveType: LeaveType) => {
    setEditingType(leaveType);
    setFormOpen(true);
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          نوع إجازة جديد
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">إدارة النظام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">أنواع الإجازات</h1>
        </div>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">الاسم</TableHead>
              <TableHead className="text-start">الرمز</TableHead>
              <TableHead className="text-start">مدفوعة</TableHead>
              <TableHead className="text-start">تعتمد على رصيد</TableHead>
              <TableHead className="text-start">الرصيد السنوي</TableHead>
              <TableHead className="text-start">الحالة</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 7 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && sortedTypes.length === 0 && (
              <TableRow>
                <TableCell colSpan={7} className="py-10 text-center text-sm text-muted">
                  لا توجد أنواع إجازات مسجلة بعد
                </TableCell>
              </TableRow>
            )}

            {sortedTypes.map((leaveType) => (
              <TableRow key={leaveType.id}>
                <TableCell className="font-medium">
                  <LeaveTypeBadge leaveType={leaveType} />
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {leaveType.code}
                  </span>
                </TableCell>
                <TableCell>
                  <Badge
                    className={
                      leaveType.is_paid
                        ? 'border-transparent bg-success-soft text-success'
                        : 'border-transparent bg-surface-2 text-muted'
                    }
                  >
                    {leaveType.is_paid ? 'مدفوعة' : 'غير مدفوعة'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Badge
                    className={
                      leaveType.is_balance_based
                        ? 'border-transparent bg-brand-soft text-brand-ink'
                        : 'border-transparent bg-surface-2 text-muted'
                    }
                  >
                    {leaveType.is_balance_based ? 'نعم' : 'لا'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {leaveType.default_annual_entitlement}
                  </span>
                </TableCell>
                <TableCell>
                  <Badge
                    className={
                      leaveType.is_active
                        ? 'border-transparent bg-success-soft text-success'
                        : 'border-transparent bg-surface-2 text-muted'
                    }
                  >
                    {leaveType.is_active ? 'نشط' : 'غير نشط'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(leaveType)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingType(leaveType)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <LeaveTypeFormDialog open={formOpen} onOpenChange={setFormOpen} leaveType={editingType} />

      <DeleteEntityDialog
        open={!!deletingType}
        onOpenChange={(open) => !open && setDeletingType(null)}
        title="حذف نوع الإجازة"
        description={
          <>
            هل أنت متأكد من حذف نوع الإجازة &quot;{deletingType?.name}&quot;؟ لا يمكن التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => leaveTypesApi.delete(deletingType!.id)}
        invalidateQueryKey={['leave-types']}
        successMessage="تم حذف نوع الإجازة بنجاح"
      />
    </div>
  );
}
