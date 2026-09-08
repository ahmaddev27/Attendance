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
import { TaskTagBadge } from '@/components/tasks/task-tag-badge';
import { TaskTagFormDialog } from '@/components/tasks/task-tag-form-dialog';
import { taskTagsApi } from '@/lib/api/endpoints/task-config';
import type { TaskTag } from '@/lib/api/types';

export default function TaskTagsPage() {
  const { data: tags, isLoading } = useQuery({
    queryKey: ['task-tags'],
    queryFn: async () => (await taskTagsApi.list()).data.data,
  });

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingTag, setEditingTag] = React.useState<TaskTag | null>(null);
  const [deletingTag, setDeletingTag] = React.useState<TaskTag | null>(null);

  const openCreate = () => {
    setEditingTag(null);
    setFormOpen(true);
  };

  const openEdit = (tag: TaskTag) => {
    setEditingTag(tag);
    setFormOpen(true);
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">إعدادات المهام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">وسوم المهام</h1>
        </div>
        <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          وسم جديد
        </Button>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">الاسم</TableHead>
              <TableHead className="text-start">اللون</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 3 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && (tags ?? []).length === 0 && (
              <TableRow>
                <TableCell colSpan={3} className="py-10 text-center text-sm text-muted">
                  لا توجد وسوم مسجلة بعد
                </TableCell>
              </TableRow>
            )}

            {(tags ?? []).map((tag) => (
              <TableRow key={tag.id}>
                <TableCell className="font-medium">
                  <TaskTagBadge tag={tag} />
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {tag.color}
                  </span>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(tag)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingTag(tag)}
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

      <TaskTagFormDialog open={formOpen} onOpenChange={setFormOpen} tag={editingTag} />

      <DeleteEntityDialog
        open={!!deletingTag}
        onOpenChange={(open) => !open && setDeletingTag(null)}
        title="حذف الوسم"
        description={
          <>
            هل أنت متأكد من حذف الوسم &quot;{deletingTag?.name}&quot;؟ لا يمكن التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => taskTagsApi.delete(deletingTag!.id)}
        invalidateQueryKey={['task-tags']}
        successMessage="تم حذف الوسم بنجاح"
      />
    </div>
  );
}
