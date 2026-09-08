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
import { TaskStatusBadge } from '@/components/tasks/task-status-badge';
import { TaskStatusFormDialog } from '@/components/tasks/task-status-form-dialog';
import { taskStatusesApi } from '@/lib/api/endpoints/task-config';
import type { TaskStatus } from '@/lib/api/types';

export default function TaskStatusesPage() {
  const { data: statuses, isLoading } = useQuery({
    queryKey: ['task-statuses'],
    queryFn: async () => (await taskStatusesApi.list()).data.data,
  });

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingStatus, setEditingStatus] = React.useState<TaskStatus | null>(null);
  const [deletingStatus, setDeletingStatus] = React.useState<TaskStatus | null>(null);

  const sortedStatuses = React.useMemo(
    () => [...(statuses ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [statuses]
  );

  const openCreate = () => {
    setEditingStatus(null);
    setFormOpen(true);
  };

  const openEdit = (status: TaskStatus) => {
    setEditingStatus(status);
    setFormOpen(true);
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">إعدادات المهام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">حالات المهام</h1>
        </div>
        <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          حالة جديدة
        </Button>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">الاسم</TableHead>
              <TableHead className="text-start">الرمز</TableHead>
              <TableHead className="text-start">الترتيب</TableHead>
              <TableHead className="text-start">اكتمال</TableHead>
              <TableHead className="text-start">إلغاء</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 6 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && sortedStatuses.length === 0 && (
              <TableRow>
                <TableCell colSpan={6} className="py-10 text-center text-sm text-muted">
                  لا توجد حالات مسجلة بعد
                </TableCell>
              </TableRow>
            )}

            {sortedStatuses.map((status) => (
              <TableRow key={status.id}>
                <TableCell className="font-medium">
                  <TaskStatusBadge status={status} />
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {status.code}
                  </span>
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {status.sort_order}
                  </span>
                </TableCell>
                <TableCell>
                  <Badge
                    className={
                      status.is_done_state
                        ? 'border-transparent bg-success-soft text-success'
                        : 'border-transparent bg-surface-2 text-muted'
                    }
                  >
                    {status.is_done_state ? 'نعم' : 'لا'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Badge
                    className={
                      status.is_cancelled_state
                        ? 'border-transparent bg-danger-soft text-danger'
                        : 'border-transparent bg-surface-2 text-muted'
                    }
                  >
                    {status.is_cancelled_state ? 'نعم' : 'لا'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(status)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingStatus(status)}
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

      <TaskStatusFormDialog open={formOpen} onOpenChange={setFormOpen} status={editingStatus} />

      <DeleteEntityDialog
        open={!!deletingStatus}
        onOpenChange={(open) => !open && setDeletingStatus(null)}
        title="حذف حالة المهمة"
        description={
          <>
            هل أنت متأكد من حذف الحالة &quot;{deletingStatus?.name}&quot;؟ لا يمكن التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => taskStatusesApi.delete(deletingStatus!.id)}
        invalidateQueryKey={['task-statuses']}
        successMessage="تم حذف الحالة بنجاح"
      />
    </div>
  );
}
