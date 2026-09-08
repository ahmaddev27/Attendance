'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Check, CheckCircle2, Pencil, Plus, Trash2, X } from 'lucide-react';

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
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
import type { EmployeeSummary, TaskPayload, TaskTag } from '@/lib/api/types';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

type TaskDetailDialogProps = {
  taskId: number | null;
  onOpenChange: (open: boolean) => void;
  /** Lets the caller jump the dialog to another task (a subtask, or the parent). */
  onNavigate?: (taskId: number) => void;
};

/**
 * Full task detail — a two-column layout on desktop: title/description/
 * subtasks/comments on the left, status/priority/assignee/dates/tags/
 * progress/attachments in a sidebar on the right. Every sidebar edit is an
 * independent PUT that patches the cached detail in place.
 */
export function TaskDetailDialog({ taskId, onOpenChange, onNavigate }: TaskDetailDialogProps) {
  const queryClient = useQueryClient();
  const open = taskId !== null;

  const { data: task, isLoading } = useQuery({
    queryKey: ['tasks', 'detail', taskId],
    queryFn: async () => (await tasksApi.get(taskId!)).data.data,
    enabled: open,
  });

  const { data: statuses } = useQuery({
    queryKey: ['task-statuses'],
    queryFn: async () => (await taskStatusesApi.list()).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const { data: priorities } = useQuery({
    queryKey: ['task-priorities'],
    queryFn: async () => (await taskPrioritiesApi.list()).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const [editingTitle, setEditingTitle] = React.useState(false);
  const [titleDraft, setTitleDraft] = React.useState('');
  const [editingDescription, setEditingDescription] = React.useState(false);
  const [descriptionDraft, setDescriptionDraft] = React.useState('');
  const [progressDraft, setProgressDraft] = React.useState(0);
  const [deleteConfirmOpen, setDeleteConfirmOpen] = React.useState(false);
  const [subtaskFormOpen, setSubtaskFormOpen] = React.useState(false);

  React.useEffect(() => {
    if (!task) return;
    setEditingTitle(false);
    setTitleDraft(task.title);
    setEditingDescription(false);
    setDescriptionDraft(task.description ?? '');
    setProgressDraft(task.progress_percent);
  }, [task]);

  const updateMutation = useMutation({
    mutationFn: (payload: Partial<TaskPayload>) => tasksApi.update(taskId!, payload),
    onSuccess: (response) => {
      queryClient.setQueryData(['tasks', 'detail', taskId], response.data.data);
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban'] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر تحديث المهمة')),
  });

  const completeMutation = useMutation({
    mutationFn: () => tasksApi.complete(taskId!),
    onSuccess: (response) => {
      toast.success('تم إكمال المهمة');
      queryClient.setQueryData(['tasks', 'detail', taskId], response.data.data);
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban'] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر إكمال المهمة')),
  });

  const deleteMutation = useMutation({
    mutationFn: () => tasksApi.delete(taskId!),
    onSuccess: () => {
      toast.success('تم حذف المهمة');
      queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban'] });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
      setDeleteConfirmOpen(false);
      onOpenChange(false);
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
    if (trimmed !== task.title) updateMutation.mutate({ title: trimmed });
    setEditingTitle(false);
  };

  const saveDescription = () => {
    if (!task) return;
    if (descriptionDraft !== (task.description ?? '')) {
      updateMutation.mutate({ description: descriptionDraft || undefined });
    }
    setEditingDescription(false);
  };

  const commitProgress = () => {
    if (!task || progressDraft === task.progress_percent) return;
    updateMutation.mutate({ progress_percent: progressDraft });
  };

  const handleAssigneeChange = (employee: EmployeeSummary | null) => {
    updateMutation.mutate({ assigned_to: employee?.id ?? null });
  };

  const handleTagsChange = (nextTags: TaskTag[]) => {
    updateMutation.mutate({ tag_ids: nextTags.map((t) => t.id) });
  };

  const handleDialogOpenChange = (next: boolean) => {
    if (!next) onOpenChange(false);
  };

  return (
    <>
      <Dialog open={open} onOpenChange={handleDialogOpenChange}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
          {isLoading && (
            <div className="space-y-4 py-4">
              <Skeleton className="h-7 w-2/3" />
              <Skeleton className="h-24 w-full" />
              <Skeleton className="h-40 w-full" />
            </div>
          )}

          {!isLoading && task && (
            // Keyed on the task id so uncontrolled fields (date/number inputs
            // using `defaultValue`) remount with fresh values when the
            // dialog is redirected to another task (e.g. via subtask/parent
            // navigation) instead of keeping the previous task's DOM value.
            <div key={task.id} className="contents">
              <DialogHeader>
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
                  <DialogTitle className="flex items-center gap-2">
                    <span className="min-w-0 truncate">{task.title}</span>
                    <button
                      type="button"
                      onClick={() => setEditingTitle(true)}
                      aria-label="تعديل العنوان"
                      className="shrink-0 text-muted hover:text-ink-2"
                    >
                      <Pencil className="h-3.5 w-3.5" />
                    </button>
                  </DialogTitle>
                )}
              </DialogHeader>

              <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
                {/* Main column */}
                <div className="min-w-0 space-y-6">
                  <section className="space-y-2">
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
                          rows={4}
                          autoFocus
                        />
                        <div className="flex items-center gap-2">
                          <Button type="button" size="sm" onClick={saveDescription} className="bg-brand text-white hover:bg-brand-hover">
                            حفظ
                          </Button>
                          <Button type="button" size="sm" variant="outline" onClick={() => setEditingDescription(false)}>
                            إلغاء
                          </Button>
                        </div>
                      </div>
                    ) : (
                      <p className="whitespace-pre-wrap text-sm text-ink-2">{task.description || 'لا يوجد وصف'}</p>
                    )}
                  </section>

                  <section className="space-y-2">
                    <div className="flex items-center justify-between">
                      <Label className="text-xs font-semibold text-ink-2">
                        المهام الفرعية {task.subtasks.length > 0 && <span className="num">({task.subtasks.length})</span>}
                      </Label>
                      <Button type="button" size="sm" variant="outline" className="gap-1" onClick={() => setSubtaskFormOpen(true)}>
                        <Plus className="h-3.5 w-3.5" />
                        إضافة
                      </Button>
                    </div>
                    {task.subtasks.length === 0 ? (
                      <p className="text-sm text-muted">لا توجد مهام فرعية</p>
                    ) : (
                      <ul className="space-y-1.5">
                        {task.subtasks.map((subtask) => (
                          <li key={subtask.id}>
                            <button
                              type="button"
                              onClick={() => onNavigate?.(subtask.id)}
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

                  <section className="space-y-2 border-t border-hairline pt-4">
                    <Label className="text-xs font-semibold text-ink-2">
                      التعليقات {task.comments_count > 0 && <span className="num">({task.comments_count})</span>}
                    </Label>
                    <TaskComments taskId={task.id} initialComments={task.comments} />
                  </section>
                </div>

                {/* Sidebar */}
                <div className="space-y-5">
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
                    <Select value={String(task.status.id)} onValueChange={(v) => updateMutation.mutate({ status_id: Number(v) })}>
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
                    <Select value={String(task.priority.id)} onValueChange={(v) => updateMutation.mutate({ priority_id: Number(v) })}>
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
                      <Input
                        type="date"
                        dir="ltr"
                        defaultValue={task.start_date ?? ''}
                        onBlur={(e) => {
                          if (e.target.value !== (task.start_date ?? '')) {
                            updateMutation.mutate({ start_date: e.target.value || null });
                          }
                        }}
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-ink-2">تاريخ الاستحقاق</Label>
                      <Input
                        type="date"
                        dir="ltr"
                        defaultValue={task.due_date ?? ''}
                        onBlur={(e) => {
                          if (e.target.value !== (task.due_date ?? '')) {
                            updateMutation.mutate({ due_date: e.target.value || null });
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
                          if (next !== task.estimated_hours) updateMutation.mutate({ estimated_hours: next });
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

                  <div className="space-y-1.5 border-t border-hairline pt-4">
                    <Label className="text-xs font-semibold text-ink-2">المرفقات</Label>
                    <TaskAttachments taskId={task.id} initialAttachments={task.attachments} />
                  </div>
                </div>
              </div>

              <div className="flex flex-col-reverse gap-2 border-t border-hairline pt-4 sm:flex-row sm:justify-end sm:gap-2">
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                  إغلاق
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  className="text-danger hover:bg-danger-soft hover:text-danger"
                  onClick={() => setDeleteConfirmOpen(true)}
                >
                  <Trash2 className="h-4 w-4" />
                  حذف
                </Button>
                {!task.completed_at && (
                  <Button
                    type="button"
                    disabled={completeMutation.isPending}
                    onClick={() => completeMutation.mutate()}
                    className="gap-1.5 bg-success text-white hover:bg-success/90"
                  >
                    {completeMutation.isPending ? <Spinner className="text-white" /> : <CheckCircle2 className="h-4 w-4" />}
                    أكمل المهمة
                  </Button>
                )}
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المهمة</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من حذف المهمة &quot;{task?.title}&quot;؟ يمكن استعادتها لاحقاً من قبل مسؤول النظام.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              disabled={deleteMutation.isPending}
              className="bg-danger text-white hover:bg-danger/90"
              onClick={(e) => {
                e.preventDefault();
                deleteMutation.mutate();
              }}
            >
              {deleteMutation.isPending && <Spinner className="text-white" />}
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {task && (
        <TaskFormDialog
          open={subtaskFormOpen}
          onOpenChange={setSubtaskFormOpen}
          defaultParentTask={{ id: task.id, title: task.title }}
        />
      )}
    </>
  );
}
