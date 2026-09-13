'use client';

import * as React from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Star, Trash2 } from 'lucide-react';
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
import { Skeleton } from '@/components/ui/skeleton';
import { PipelineFormDialog } from '@/app/(admin)/recruitment/pipelines/_components/pipeline-form-dialog';
import { recruitmentPipelinesApi } from '@/lib/api/endpoints/recruitment';
import type { RecruitmentPipeline } from '@/lib/api/types';

export default function PipelinesPage() {
  const qc = useQueryClient();
  const [formOpen, setFormOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<RecruitmentPipeline | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<RecruitmentPipeline | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['recruitment-pipelines', 'list'],
    queryFn: async () => (await recruitmentPipelinesApi.list({ per_page: 100 })).data.data,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => recruitmentPipelinesApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف المسار');
      qc.invalidateQueries({ queryKey: ['recruitment-pipelines'] });
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحذف');
    },
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف · إدارة</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">مسارات التوظيف</h1>
        </div>
        <Button
          onClick={() => {
            setEditing(null);
            setFormOpen(true);
          }}
          className="gap-2 bg-brand text-white hover:bg-brand-hover"
        >
          <Plus className="h-4 w-4" /> مسار جديد
        </Button>
      </div>

      {isLoading ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-24 rounded-xl" />
          ))}
        </div>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {(data ?? []).map((p) => (
            <li key={p.id} className="rounded-xl border border-hairline bg-surface p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <Link href={`/recruitment/pipelines/${p.id}`} className="text-sm font-semibold text-brand-ink hover:underline">
                      {p.name}
                    </Link>
                    {p.is_default && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-[10px] font-semibold text-warn-ink">
                        <Star className="h-3 w-3 fill-warn-ink" /> افتراضي
                      </span>
                    )}
                    {!p.is_active && (
                      <span className="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] text-muted">غير نشط</span>
                    )}
                  </div>
                  <p className="mt-0.5 num text-xs text-muted" dir="ltr">{p.code}</p>
                  {p.description && <p className="mt-1 text-xs text-ink-2">{p.description}</p>}
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    aria-label="تعديل"
                    onClick={() => {
                      setEditing(p);
                      setFormOpen(true);
                    }}
                  >
                    <Pencil className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-danger hover:bg-danger-soft hover:text-danger"
                    aria-label="حذف"
                    onClick={() => setDeleteTarget(p)}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            </li>
          ))}
          {(data ?? []).length === 0 && (
            <li className="col-span-full py-8 text-center text-sm text-muted">لا مسارات معرَّفة.</li>
          )}
        </ul>
      )}

      <PipelineFormDialog open={formOpen} onOpenChange={setFormOpen} pipeline={editing} />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المسار</AlertDialogTitle>
            <AlertDialogDescription>
              لا يمكن الحذف إن كان مرتبطاً بوظائف قائمة.
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
