'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
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
import { TaskPriorityBadge } from '@/components/tasks/task-priority-badge';
import { TaskPriorityFormDialog } from '@/components/tasks/task-priority-form-dialog';
import { taskPrioritiesApi } from '@/lib/api/endpoints/task-config';
import type { TaskPriority } from '@/lib/api/types';

export default function TaskPrioritiesPage() {
  const { data: priorities, isLoading } = useQuery({
    queryKey: ['task-priorities'],
    queryFn: async () => (await taskPrioritiesApi.list()).data.data,
  });

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingPriority, setEditingPriority] = React.useState<TaskPriority | null>(null);
  const [deletingPriority, setDeletingPriority] = React.useState<TaskPriority | null>(null);

  const sortedPriorities = React.useMemo(
    () => [...(priorities ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [priorities]
  );

  const openCreate = () => {
    setEditingPriority(null);
    setFormOpen(true);
  };

  const openEdit = (priority: TaskPriority) => {
    setEditingPriority(priority);
    setFormOpen(true);
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">إعدادات المهام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">أولويات المهام</h1>
        </div>
        <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          أولوية جديدة
        </Button>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">الاسم</TableHead>
              <TableHead className="text-start">الرمز</TableHead>
              <TableHead className="text-start">الترتيب</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 4 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && sortedPriorities.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-10 text-center text-sm text-muted">
                  لا توجد أولويات مسجلة بعد
                </TableCell>
              </TableRow>
            )}

            {sortedPriorities.map((priority) => (
              <TableRow key={priority.id}>
                <TableCell className="font-medium">
                  <TaskPriorityBadge priority={priority} />
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {priority.code}
                  </span>
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {priority.sort_order}
                  </span>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(priority)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingPriority(priority)}
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

      <TaskPriorityFormDialog open={formOpen} onOpenChange={setFormOpen} priority={editingPriority} />

      <DeleteEntityDialog
        open={!!deletingPriority}
        onOpenChange={(open) => !open && setDeletingPriority(null)}
        title="حذف الأولوية"
        description={
          <>
            هل أنت متأكد من حذف الأولوية &quot;{deletingPriority?.name}&quot;؟ لا يمكن التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => taskPrioritiesApi.delete(deletingPriority!.id)}
        invalidateQueryKey={['task-priorities']}
        successMessage="تم حذف الأولوية بنجاح"
      />
    </div>
  );
}
