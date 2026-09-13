'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronRight, Flag, GripVertical, ListOrdered, Pencil, Plus, Star, Timer, Trash2 } from 'lucide-react';
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
import { SortableList, type DragHandleProps } from '@/components/workflow/sortable-list';
import { PipelineFormDialog } from '@/app/(admin)/recruitment/pipelines/_components/pipeline-form-dialog';
import { StageFormDialog } from '@/app/(admin)/recruitment/pipelines/[id]/_components/stage-form-dialog';
import { recruitmentPipelinesApi, recruitmentPipelineStagesApi } from '@/lib/api/endpoints/recruitment';
import { STAGE_OWNER_RULE_LABELS } from '@/lib/constants/recruitment-options';
import { cn } from '@/lib/utils';
import type { RecruitmentPipelineStage } from '@/lib/api/types';

const REQUIRED_FIELD_LABELS: Record<string, string> = {
  publication_url: 'رابط النشر',
  shortlist_ids: 'القائمة المختصرة',
  contract_terms: 'شروط العقد',
};

export default function PipelineDetailPage() {
  const params = useParams<{ id: string }>();
  const pipelineId = Number(params.id);
  const qc = useQueryClient();

  const [editPipelineOpen, setEditPipelineOpen] = React.useState(false);
  const [stageFormOpen, setStageFormOpen] = React.useState(false);
  const [editingStage, setEditingStage] = React.useState<RecruitmentPipelineStage | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<RecruitmentPipelineStage | null>(null);
  // Holds the dragged order until the server confirms it, so the list
  // doesn't snap back while the reorder request is in flight.
  const [pendingOrder, setPendingOrder] = React.useState<RecruitmentPipelineStage[] | null>(null);

  const { data: pipeline, isLoading } = useQuery({
    queryKey: ['recruitment-pipelines', pipelineId],
    queryFn: async () => (await recruitmentPipelinesApi.get(pipelineId)).data.data,
    enabled: Number.isFinite(pipelineId),
  });

  const serverStages = React.useMemo(
    () => [...(pipeline?.stages ?? [])].sort((a, b) => a.display_order - b.display_order),
    [pipeline?.stages],
  );
  const stages = pendingOrder ?? serverStages;

  const reorderMutation = useMutation({
    mutationFn: (ids: number[]) => recruitmentPipelineStagesApi.reorder(pipelineId, ids),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment-pipelines'] });
      setPendingOrder(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إعادة الترتيب');
      setPendingOrder(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (stageId: number) => recruitmentPipelineStagesApi.delete(pipelineId, stageId),
    onSuccess: () => {
      toast.success('تم حذف المرحلة');
      qc.invalidateQueries({ queryKey: ['recruitment-pipelines'] });
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحذف');
      setDeleteTarget(null);
    },
  });

  const openCreateStage = () => {
    setEditingStage(null);
    setStageFormOpen(true);
  };

  const openEditStage = (stage: RecruitmentPipelineStage) => {
    setEditingStage(stage);
    setStageFormOpen(true);
  };

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />;
  if (!pipeline) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على المسار.</p>;

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/pipelines" className="hover:text-brand-ink">مسارات التوظيف</Link>
        <ChevronRight className="h-3 w-3" />
        <span>{pipeline.name}</span>
      </nav>

      <div className="rounded-xl border border-hairline bg-surface p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-bold text-ink">{pipeline.name}</h1>
              {pipeline.is_default && (
                <span className="inline-flex items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-[10px] font-semibold text-warn-ink">
                  <Star className="h-3 w-3 fill-warn-ink" /> افتراضي
                </span>
              )}
              {!pipeline.is_active && (
                <span className="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] text-muted">غير نشط</span>
              )}
            </div>
            <p className="mt-1 num text-xs text-muted" dir="ltr">{pipeline.code}</p>
            {pipeline.description && <p className="mt-2 text-sm text-ink-2">{pipeline.description}</p>}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" className="gap-2" onClick={() => setEditPipelineOpen(true)}>
              <Pencil className="h-4 w-4" /> تعديل المسار
            </Button>
            <Button type="button" className="gap-2 bg-brand text-white hover:bg-brand-hover" onClick={openCreateStage}>
              <Plus className="h-4 w-4" /> إضافة مرحلة
            </Button>
          </div>
        </div>
      </div>

      <section className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-sm font-semibold text-ink">
            المراحل <span className="num text-muted" dir="ltr">({stages.length})</span>
          </h2>
          {stages.length > 1 && (
            <p className="text-xs text-muted">اسحب المراحل من المقبض لإعادة ترتيبها</p>
          )}
        </div>

        {stages.length === 0 ? (
          <div className="rounded-xl border border-dashed border-hairline bg-surface py-10 text-center text-sm text-muted">
            لا مراحل بعد. أضف أول مرحلة ليبدأ المسار.
          </div>
        ) : (
          <SortableList
            items={stages}
            getId={(s) => String(s.id)}
            onReorder={(next) => {
              setPendingOrder(next);
              reorderMutation.mutate(next.map((s) => s.id));
            }}
            renderItem={(stage, handle) => (
              <StageCard
                stage={stage}
                index={stages.indexOf(stage)}
                handle={handle}
                disabled={reorderMutation.isPending}
                onEdit={() => openEditStage(stage)}
                onDelete={() => setDeleteTarget(stage)}
              />
            )}
          />
        )}
      </section>

      <PipelineFormDialog open={editPipelineOpen} onOpenChange={setEditPipelineOpen} pipeline={pipeline} />
      <StageFormDialog
        open={stageFormOpen}
        onOpenChange={setStageFormOpen}
        pipelineId={pipelineId}
        stage={editingStage}
      />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المرحلة «{deleteTarget?.name}»</AlertDialogTitle>
            <AlertDialogDescription>
              لا يمكن الحذف إن كانت توجد وظائف نشطة في هذه المرحلة.
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

function StageCard({
  stage,
  index,
  handle,
  disabled,
  onEdit,
  onDelete,
}: {
  stage: RecruitmentPipelineStage;
  index: number;
  handle: DragHandleProps;
  disabled: boolean;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const ownerLabel = STAGE_OWNER_RULE_LABELS[stage.owner_rule_type];
  const ownerDetail = stage.owner_rule_value ? ` · ${stage.owner_rule_value}` : '';

  return (
    <div className={cn('flex items-start gap-3 rounded-xl border border-hairline bg-surface p-4', disabled && 'opacity-70')}>
      <button
        type="button"
        aria-label="اسحب لإعادة الترتيب"
        className="mt-0.5 shrink-0 cursor-grab touch-none rounded-md p-1 text-muted hover:bg-surface-2 hover:text-ink active:cursor-grabbing"
        {...handle.attributes}
        {...handle.listeners}
      >
        <GripVertical className="h-4 w-4" />
      </button>

      <span className="num mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand-ink" dir="ltr">
        {index + 1}
      </span>

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="text-sm font-semibold text-ink">{stage.name}</p>
          <span className="num text-xs text-muted" dir="ltr">{stage.code}</span>
          {stage.is_terminal && (
            <span className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[10px] font-semibold text-success">
              <Flag className="h-3 w-3" /> نهائية
            </span>
          )}
        </div>
        {stage.description && <p className="mt-1 text-xs text-ink-2">{stage.description}</p>}

        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
          <span>
            المالك: <span className="text-ink-2">{ownerLabel}</span>
            {ownerDetail && <span className="num" dir="ltr">{ownerDetail}</span>}
          </span>
          {stage.sla_hours !== null && (
            <span className="inline-flex items-center gap-1">
              <Timer className="h-3 w-3" />
              <span className="num" dir="ltr">{stage.sla_hours}</span> ساعة
            </span>
          )}
          {stage.auto_generate_task && (
            <span className="inline-flex items-center gap-1">
              <ListOrdered className="h-3 w-3" /> مهمة تلقائية
            </span>
          )}
        </div>

        {stage.requires_fields.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1.5">
            {stage.requires_fields.map((field) => (
              <span key={field} className="rounded-md bg-warn-soft px-2 py-0.5 text-[10px] font-medium text-warn-ink">
                يتطلب: {REQUIRED_FIELD_LABELS[field] ?? field}
              </span>
            ))}
          </div>
        )}
      </div>

      <div className="flex shrink-0 items-center gap-1">
        <Button type="button" variant="ghost" size="icon" className="h-8 w-8" aria-label="تعديل" onClick={onEdit}>
          <Pencil className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="h-8 w-8 text-danger hover:bg-danger-soft hover:text-danger"
          aria-label="حذف"
          onClick={onDelete}
        >
          <Trash2 className="h-3.5 w-3.5" />
        </Button>
      </div>
    </div>
  );
}
