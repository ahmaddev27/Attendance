'use client';

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowRight, Check, CheckCircle2, Pencil, Plus, Trash2, X } from 'lucide-react';

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
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { TaskAttachments } from '@/components/tasks/task-attachments';
import { TaskComments } from '@/components/tasks/task-comments';
import { TaskFormDialog } from '@/components/tasks/task-form-dialog';
import { TaskProgressBar } from '@/components/tasks/task-progress-bar';
import { TaskStatusBadge } from '@/components/tasks/task-status-badge';
import { TaskTagPicker } from '@/components/tasks/task-tag-picker';
import { taskPrioritiesApi, taskStatusesApi } from '@/lib/api/endpoints/task-config';
import { tasksApi } from '@/lib/api/endpoints/tasks';
import { formatDate } from '@/lib/attendance-format';
import { formatRelativeTime } from '@/lib/task-format';
import type { EmployeeSummary, TaskAction, TaskPayload, TaskTag } from '@/lib/api/types';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

const HISTORY_ACTION_LABELS: Record<TaskAction, string> = {
  created: 'أنشأ المهمة',
  assigned: 'أسند المهمة',
  unassigned: 'ألغى الإسناد',
  status_changed: 'غيّر الحالة',
  priority_changed: 'غيّر الأولوية',
  commented: 'أضاف تعليقاً',
  attached_file: 'أرفق ملفاً',
  completed: 'أكمل المهمة',
  deleted: 'حذف المهمة',
  restored: 'استعاد المهمة',
};

type TaskDetailViewProps = {
  taskId: number;
  /**
   * Base path for the tasks section this view lives in (e.g. `/tasks` for
   * admins, `/my-tasks` for employees). Used both for the back link and
   * for subtask/parent navigation so we stay in the same section.
   */
  basePath: string;
};

/**
 * Full task detail as a dedicated page. Left column carries the long-form
 * content (description, subtasks, comments, attachments, history); the right
 * sidebar is the field editor (status/priority/assignee/dates/hours/progress/
 * tags). Every sidebar edit is an independent PUT that patches the cached
 * detail in place.
 */
export function TaskDetailView({ taskId, basePath }: TaskDetailViewProps) {
  const router = useRouter();
  const queryClient = useQueryClient();

  const { data: task, isLoading, isError } = useQuery({
    queryKey: ['tasks', 'detail', taskId],
    queryFn: async () => (await tasksApi.get(taskId)).data.data,
    enabled: Number.isFinite(taskId),
  });

  const { data: statuses } = useQuery({
    queryKey: ['task-statuses'],
    queryFn: async () => (await taskStatusesApi.list()).data.data,
  });

  const { data: priorities } = useQuery({
    queryKey: ['task-priorities'],
    queryFn: async () => (await taskPrioritiesApi.list()).data.data,
  });

  const [editingTitle, setEditingTitle] = React.useState(false);
  const [titleDraft, setTitleDraft] = React.useState('');
  const [editingDescription, setEditingDescription] = React.useState(false);
  const [descriptionDraft, setDescriptionDraft] = React.useState('');
  const [progressDraft, setProgressDraft] = React.useState(0);
  const [deleteConfirmOpen, setDeleteConfirmOpen] = React.useState(false);
  const [subtaskFormOpen, setSubtaskFormOpen] = React.useState(false);
  const [editFormOpen, setEditFormOpen] = React.useState(false);

  React.useEffect(() => {
    if (!task) return;
    setEditingTitle(false);
    setTitleDraft(task.title);
    setEditingDescription(false);
    setDescriptionDraft(task.description ?? '');
    setProgressDraft(task.progress_percent);
  }, [task]);

  // Every mutation pins the task id into its `variables` instead of closing
  // over the current `taskId` prop, so a request issued for task A always
  // lands its response in task A's cache — even if the user has since
  // navigated to task B.
  const updateMutation = useMutation({
    mutationFn: ({ taskId, payload }: { taskId: number; payload: Partial<TaskPayload> }) =>
      tasksApi.update(taskId, payload),
    onSuccess: (response, vars) => {
      queryClient.setQueryData(['tasks', 'detail', vars.taskId], response.data.data);
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban'] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
      queryClient.invalidateQueries({ queryKey: ['my-tasks'] });
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر تحديث المهمة')),
  });

  const completeMutation = useMutation({
    mutationFn: (id: number) => tasksApi.complete(id),
    onSuccess: (response, id) => {
      toast.success('تم إكمال المهمة');
      queryClient.setQueryData(['tasks', 'detail', id], response.data.data);
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban'] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
      queryClient.invalidateQueries({ queryKey: ['my-tasks'] });
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر إكمال المهمة')),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => tasksApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف المهمة');
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban'] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
      queryClient.invalidateQueries({ queryKey: ['my-tasks'] });
      setDeleteConfirmOpen(false);
      router.push(basePath);
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر حذف المهمة')),
  });

  const sortedStatuses = React.useMemo(
    () => [...(statuses ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [statuses]
  );
  const sortedPriorities = React.useMemo(
    () => [...(priorities ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [priorities]
  );

  const saveTitle = () => {
    const trimmed = titleDraft.trim();
    if (!trimmed || !task) {
      setEditingTitle(false);
      setTitleDraft(task?.title ?? '');
      return;
    }
    if (trimmed !== task.title) updateMutation.mutate({ taskId: task.id, payload: { title: trimmed } });
    setEditingTitle(false);
  };

  const saveDescription = () => {
    if (!task) return;
    if (descriptionDraft !== (task.description ?? '')) {
      updateMutation.mutate({ taskId: task.id, payload: { description: descriptionDraft || undefined } });
    }
    setEditingDescription(false);
  };

  const commitProgress = () => {
    if (!task || progressDraft === task.progress_percent) return;
    updateMutation.mutate({ taskId: task.id, payload: { progress_percent: progressDraft } });
  };

  const handleAssigneeChange = (employee: EmployeeSummary | null) => {
    if (!task) return;
    updateMutation.mutate({ taskId: task.id, payload: { assigned_to: employee?.id ?? null } });
  };

  const handleTagsChange = (nextTags: TaskTag[]) => {
    if (!task) return;
    updateMutation.mutate({ taskId: task.id, payload: { tag_ids: nextTags.map((t) => t.id) } });
  };

  const navigateToTask = (nextTaskId: number) => {
    router.push(`${basePath}/${nextTaskId}`);
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-10 w-2/3" />
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="space-y-4 lg:col-span-2">
            <Skeleton className="h-40 w-full rounded-xl" />
            <Skeleton className="h-40 w-full rounded-xl" />
          </div>
          <Skeleton className="h-80 w-full rounded-xl" />
        </div>
      </div>
    );
  }

  if (isError || !task) {
    return (
      <div className="space-y-6">
        <Link href={basePath} className="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink">
          <ArrowRight className="h-4 w-4" />
          العودة إلى المهام
        </Link>
        <div className="rounded-xl border border-hairline bg-surface p-8 text-center">
          <p className="text-sm text-muted">تعذر العثور على المهمة المطلوبة أو أنك لا تملك صلاحية عرضها.</p>
        </div>
      </div>
    );
  }

  const sortedHistory = [...(task.history ?? [])].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  return (
    <div key={task.id} className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 flex-1 space-y-2">
          <Link
            href={basePath}
            className="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink"
          >
            <ArrowRight className="h-4 w-4" />
            العودة إلى المهام
          </Link>

          {task.parent_task_id && (
            <button
              type="button"
              onClick={() => navigateToTask(task.parent_task_id!)}
              className="inline-flex items-center gap-1.5 text-xs text-brand-ink hover:underline"
            >
              مهمة فرعية — انتقل إلى المهمة الأصلية
            </button>
          )}

          {editingTitle ? (
            <div className="flex items-center gap-2">
              <Input
                value={titleDraft}
                onChange={(e) => setTitleDraft(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') saveTitle();
                  if (e.key === 'Escape') {
                    setTitleDraft(task.title);
                    setEditingTitle(false);
                  }
                }}
                autoFocus
                className="text-lg font-semibold"
              />
              <Button type="button" size="icon" variant="ghost" onClick={saveTitle} aria-label="حفظ العنوان">
                <Check className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                size="icon"
                variant="ghost"
                onClick={() => {
                  setTitleDraft(task.title);
                  setEditingTitle(false);
                }}
                aria-label="إلغاء"
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          ) : (
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="min-w-0 text-2xl font-bold text-ink">{task.title}</h1>
              <button
                type="button"
                onClick={() => setEditingTitle(true)}
                aria-label="تعديل العنوان"
                className="shrink-0 text-muted hover:text-ink-2"
              >
                <Pencil className="h-4 w-4" />
              </button>
              <TaskStatusBadge status={task.status} />
            </div>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="outline" className="gap-1.5" onClick={() => setEditFormOpen(true)}>
            <Pencil className="h-4 w-4" />
            تعديل
          </Button>
          <Button
            type="button"
            variant="outline"
            className="gap-1.5 text-danger hover:bg-danger-soft hover:text-danger"
            onClick={() => setDeleteConfirmOpen(true)}
          >
            <Trash2 className="h-4 w-4" />
            حذف
          </Button>
          {!task.completed_at && (
            <Button
              type="button"
              disabled={completeMutation.isPending}
              onClick={() => completeMutation.mutate(task.id)}
              className="gap-1.5 bg-success text-white hover:bg-success/90"
            >
              {completeMutation.isPending ? <Spinner className="text-white" /> : <CheckCircle2 className="h-4 w-4" />}
              أكمل المهمة
            </Button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="min-w-0 space-y-6 lg:col-span-2">
          <section className="space-y-3 rounded-xl border border-hairline bg-surface p-5">
            <div className="flex items-center justify-between">
              <Label className="text-xs font-semibold text-ink-2">الوصف</Label>
              {!editingDescription && (
                <button
                  type="button"
                  onClick={() => setEditingDescription(true)}
                  className="text-xs text-brand-ink hover:underline"
                >
                  تعديل
                </button>
              )}
            </div>
            {editingDescription ? (
              <div className="space-y-2">
                <Textarea
                  value={descriptionDraft}
                  onChange={(e) => setDescriptionDraft(e.target.value)}
                  rows={6}
                  autoFocus
                />
                <div className="flex items-center gap-2">
                  <Button
                    type="button"
                    size="sm"
                    onClick={saveDescription}
                    className="bg-brand text-white hover:bg-brand-hover"
                  >
                    حفظ
                  </Button>
                  <Button type="button" size="sm" variant="outline" onClick={() => setEditingDescription(false)}>
                    إلغاء
                  </Button>
                </div>
              </div>
            ) : (
              <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink-2">
                {task.description || 'لا يوجد وصف'}
              </p>
            )}
          </section>

          <section className="space-y-3 rounded-xl border border-hairline bg-surface p-5">
            <div className="flex items-center justify-between">
              <Label className="text-xs font-semibold text-ink-2">
                المهام الفرعية {(task.subtasks?.length ?? 0) > 0 && <span className="num">({(task.subtasks?.length ?? 0)})</span>}
              </Label>
              <Button type="button" size="sm" variant="outline" className="gap-1" onClick={() => setSubtaskFormOpen(true)}>
                <Plus className="h-3.5 w-3.5" />
                إضافة
              </Button>
            </div>
            {(task.subtasks?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted">لا توجد مهام فرعية</p>
            ) : (
              <ul className="space-y-1.5">
                {(task.subtasks ?? []).map((subtask) => (
                  <li key={subtask.id}>
                    <button
                      type="button"
                      onClick={() => navigateToTask(subtask.id)}
                      className="flex w-full items-center justify-between gap-2 rounded-md border border-hairline px-3 py-2 text-start hover:bg-surface-2"
                    >
                      <span className="min-w-0 truncate text-sm text-ink">{subtask.title}</span>
                      <TaskStatusBadge status={subtask.status} className="shrink-0" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="space-y-3 rounded-xl border border-hairline bg-surface p-5">
            <Label className="text-xs font-semibold text-ink-2">
              التعليقات {task.comments_count > 0 && <span className="num">({task.comments_count})</span>}
            </Label>
            <TaskComments taskId={task.id} initialComments={task.comments} />
          </section>

          <section className="space-y-3 rounded-xl border border-hairline bg-surface p-5">
            <Label className="text-xs font-semibold text-ink-2">
              المرفقات {task.attachments_count > 0 && <span className="num">({task.attachments_count})</span>}
            </Label>
            <TaskAttachments taskId={task.id} initialAttachments={task.attachments} />
          </section>

          {sortedHistory.length > 0 && (
            <section className="space-y-3 rounded-xl border border-hairline bg-surface p-5">
              <Label className="text-xs font-semibold text-ink-2">
                سجل النشاط <span className="num">({sortedHistory.length})</span>
              </Label>
              <ul className="space-y-2">
                {sortedHistory.map((entry) => (
                  <li key={entry.id} className="flex items-start gap-2 text-sm text-ink-2">
                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-hairline" />
                    <div className="min-w-0 flex-1">
                      <p>
                        <span className="font-medium text-ink">{entry.user.name}</span>{' '}
                        <span className="text-muted">{HISTORY_ACTION_LABELS[entry.action] ?? entry.action}</span>
                      </p>
                      <p className="text-xs text-muted">{formatRelativeTime(entry.created_at)}</p>
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>

        <aside className="space-y-5 rounded-xl border border-hairline bg-surface p-5 lg:sticky lg:top-6 lg:self-start">
          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
            <Select
              value={String(task.status.id)}
              onValueChange={(v) => updateMutation.mutate({ taskId: task.id, payload: { status_id: Number(v) } })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {sortedStatuses.map((status) => (
                  <SelectItem key={status.id} value={String(status.id)}>
                    {status.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-ink-2">الأولوية</Label>
            <Select
              value={String(task.priority.id)}
              onValueChange={(v) => updateMutation.mutate({ taskId: task.id, payload: { priority_id: Number(v) } })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {sortedPriorities.map((priority) => (
                  <SelectItem key={priority.id} value={String(priority.id)}>
                    {priority.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-ink-2">الموظف المسند إليه</Label>
            <EmployeeSearchSelect value={task.assignee} onChange={handleAssigneeChange} placeholder="بدون إسناد" />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-ink-2">تاريخ البدء</Label>
              <DatePicker
                value={task.start_date ?? ''}
                onChange={(v: string) => {
                  if (v !== (task.start_date ?? '')) {
                    updateMutation.mutate({ taskId: task.id, payload: { start_date: v || null } });
                  }
                }}
                placeholder="اختر تاريخاً"
              />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-ink-2">تاريخ الاستحقاق</Label>
              <DatePicker
                value={task.due_date ?? ''}
                onChange={(v: string) => {
                  if (v !== (task.due_date ?? '')) {
                    updateMutation.mutate({ taskId: task.id, payload: { due_date: v || null } });
                  }
                }}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-ink-2">الساعات المقدرة</Label>
              <Input
                type="number"
                min={0}
                step={0.5}
                dir="ltr"
                className="num text-right"
                defaultValue={task.estimated_hours ?? ''}
                onBlur={(e) => {
                  const next = e.target.value === '' ? null : Number(e.target.value);
                  if (next !== task.estimated_hours) {
                    updateMutation.mutate({ taskId: task.id, payload: { estimated_hours: next } });
                  }
                }}
              />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-ink-2">الساعات الفعلية</Label>
              <p className="num flex h-9 items-center rounded-md border border-hairline bg-surface-2 px-3 text-sm text-ink-2">
                {task.actual_hours ?? '—'}
              </p>
            </div>
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <Label className="text-xs font-semibold text-ink-2">نسبة الإنجاز</Label>
              <span className="num text-xs font-medium text-ink-2">{progressDraft}%</span>
            </div>
            <input
              type="range"
              min={0}
              max={100}
              step={5}
              value={progressDraft}
              onChange={(e) => setProgressDraft(Number(e.target.value))}
              onMouseUp={commitProgress}
              onTouchEnd={commitProgress}
              onKeyUp={commitProgress}
              className="w-full accent-brand"
            />
            <TaskProgressBar value={progressDraft} />
          </div>

          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-ink-2">الوسوم</Label>
            <TaskTagPicker value={task.tags} onChange={handleTagsChange} />
          </div>

          <div className="space-y-1.5 border-t border-hairline pt-4 text-xs text-muted">
            <p>
              أنشأها <span className="font-medium text-ink-2">{task.creator.full_name}</span> بتاريخ{' '}
              <span className="num" dir="ltr">
                {formatDate(task.created_at.slice(0, 10))}
              </span>
            </p>
            {task.completed_at && (
              <p>
                اكتملت بتاريخ{' '}
                <span className="num" dir="ltr">
                  {formatDate(task.completed_at.slice(0, 10))}
                </span>
              </p>
            )}
          </div>
        </aside>
      </div>

      <AlertDialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المهمة</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من حذف المهمة &quot;{task.title}&quot;؟ يمكن استعادتها لاحقاً من قبل مسؤول النظام.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              disabled={deleteMutation.isPending}
              className="bg-danger text-white hover:bg-danger/90"
              onClick={(e) => {
                e.preventDefault();
                deleteMutation.mutate(task.id);
              }}
            >
              {deleteMutation.isPending && <Spinner className="text-white" />}
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <TaskFormDialog open={editFormOpen} onOpenChange={setEditFormOpen} task={task} />

      <TaskFormDialog
        open={subtaskFormOpen}
        onOpenChange={setSubtaskFormOpen}
        defaultParentTask={{ id: task.id, title: task.title }}
      />
    </div>
  );
}
