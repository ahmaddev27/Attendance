'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { TaskPicker, type TaskPickerOption } from '@/components/tasks/task-picker';
import { TaskTagPicker } from '@/components/tasks/task-tag-picker';
import { taskPrioritiesApi, taskStatusesApi } from '@/lib/api/endpoints/task-config';
import { tasksApi } from '@/lib/api/endpoints/tasks';
import type { EmployeeSummary, Task, TaskDetail, TaskPayload, TaskTag } from '@/lib/api/types';

const taskFormSchema = z
  .object({
    title: z.string().trim().min(1, 'عنوان المهمة مطلوب').max(255, 'العنوان طويل جداً'),
    description: z.string().trim().max(5000, 'الوصف طويل جداً').optional().or(z.literal('')),
    status_id: z.number({ required_error: 'الحالة مطلوبة' }).min(1, 'الحالة مطلوبة'),
    priority_id: z.number({ required_error: 'الأولوية مطلوبة' }).min(1, 'الأولوية مطلوبة'),
    estimated_hours: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر').nullable(),
    start_date: z.string().optional().or(z.literal('')),
    due_date: z.string().optional().or(z.literal('')),
    progress_percent: z.number().min(0).max(100),
  })
  .refine((data) => !data.start_date || !data.due_date || data.due_date >= data.start_date, {
    message: 'تاريخ الاستحقاق يجب أن يكون بعد تاريخ البدء أو يساويه',
    path: ['due_date'],
  });

type TaskFormValues = z.infer<typeof taskFormSchema>;

const EMPTY_VALUES: TaskFormValues = {
  title: '',
  description: '',
  status_id: 0,
  priority_id: 0,
  estimated_hours: null,
  start_date: '',
  due_date: '',
  progress_percent: 0,
};

function buildDefaultValues(task?: Task | TaskDetail | null): TaskFormValues {
  if (!task) return EMPTY_VALUES;
  return {
    title: task.title,
    description: task.description ?? '',
    status_id: task.status.id,
    priority_id: task.priority.id,
    estimated_hours: task.estimated_hours,
    start_date: task.start_date ?? '',
    due_date: task.due_date ?? '',
    progress_percent: task.progress_percent,
  };
}

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

type TaskFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  task?: Task | TaskDetail | null;
  /** Pre-selects a parent task when creating a subtask from `<TaskDetailDialog>`. Ignored in edit mode. */
  defaultParentTask?: TaskPickerOption | null;
};

/** Create/edit dialog for a task's core fields. Full detail editing (status/assignee/tags/etc. one field at a time, comments, attachments) lives in `<TaskDetailDialog>` instead. */
export function TaskFormDialog({ open, onOpenChange, task, defaultParentTask }: TaskFormDialogProps) {
  const isEdit = !!task;
  const queryClient = useQueryClient();

  const [assignee, setAssignee] = React.useState<EmployeeSummary | null>(null);
  const [parentTask, setParentTask] = React.useState<TaskPickerOption | null>(null);
  const [tags, setTags] = React.useState<TaskTag[]>([]);

  const form = useForm<TaskFormValues>({
    resolver: zodResolver(taskFormSchema),
    defaultValues: buildDefaultValues(task),
  });

  const parentTaskId = task?.parent_task_id ?? null;

  // The task list only carries `parent_task_id`, not the parent's title —
  // fetched separately so the picker can display it when editing a subtask.
  const { data: parentTaskDetail } = useQuery({
    queryKey: ['tasks', 'detail', parentTaskId],
    queryFn: async () => (await tasksApi.get(parentTaskId!)).data.data,
    enabled: open && !!parentTaskId,
    staleTime: 60_000,
  });

  React.useEffect(() => {
    if (!open) return;
    form.reset(buildDefaultValues(task));
    setAssignee(task?.assignee ?? null);
    setTags(task?.tags ?? []);
    if (!task?.parent_task_id) setParentTask(task ? null : defaultParentTask ?? null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, task?.id]);

  React.useEffect(() => {
    if (parentTaskDetail) setParentTask({ id: parentTaskDetail.id, title: parentTaskDetail.title });
  }, [parentTaskDetail]);

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

  const sortedStatuses = React.useMemo(
    () => [...(statuses ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [statuses]
  );
  const sortedPriorities = React.useMemo(
    () => [...(priorities ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [priorities]
  );

  const mutation = useMutation({
    mutationFn: (values: TaskFormValues) => {
      const payload: TaskPayload = {
        title: values.title,
        description: values.description || undefined,
        parent_task_id: parentTask?.id ?? null,
        status_id: values.status_id,
        priority_id: values.priority_id,
        assigned_to: assignee?.id ?? null,
        estimated_hours: values.estimated_hours,
        progress_percent: values.progress_percent,
        start_date: values.start_date || null,
        due_date: values.due_date || null,
        tag_ids: tags.map((t) => t.id),
      };
      return isEdit ? tasksApi.update(task!.id, payload) : tasksApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث المهمة بنجاح' : 'تم إنشاء المهمة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      if (isEdit) queryClient.invalidateQueries({ queryKey: ['tasks', 'detail', task!.id] });
      if (!isEdit && defaultParentTask) {
        queryClient.invalidateQueries({ queryKey: ['tasks', 'detail', defaultParentTask.id] });
      }
      onOpenChange(false);
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر حفظ المهمة')),
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل المهمة' : 'مهمة جديدة'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات المهمة ثم احفظ التغييرات.' : 'أدخل بيانات المهمة الجديدة.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>عنوان المهمة</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>الوصف (اختياري)</FormLabel>
                  <FormControl>
                    <Textarea rows={3} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="flex flex-col gap-2">
              <Label>المهمة الرئيسية (اختياري)</Label>
              <TaskPicker value={parentTask} onChange={setParentTask} excludeId={task?.id} />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="status_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الحالة</FormLabel>
                    <Select value={field.value ? String(field.value) : undefined} onValueChange={(v) => field.onChange(Number(v))}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="اختر الحالة" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {sortedStatuses.map((status) => (
                          <SelectItem key={status.id} value={String(status.id)}>
                            {status.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="priority_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الأولوية</FormLabel>
                    <Select value={field.value ? String(field.value) : undefined} onValueChange={(v) => field.onChange(Number(v))}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="اختر الأولوية" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {sortedPriorities.map((priority) => (
                          <SelectItem key={priority.id} value={String(priority.id)}>
                            {priority.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label>الموظف المسند إليه (اختياري)</Label>
              <EmployeeSearchSelect value={assignee} onChange={setAssignee} placeholder="بدون إسناد" />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <FormField
                control={form.control}
                name="start_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>تاريخ البدء</FormLabel>
                    <FormControl>
                      <Input type="date" dir="ltr" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="due_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>تاريخ الاستحقاق</FormLabel>
                    <FormControl>
                      <Input type="date" dir="ltr" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="estimated_hours"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الساعات المقدرة</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        min={0}
                        step={0.5}
                        dir="ltr"
                        className="num text-right"
                        value={field.value ?? ''}
                        placeholder="—"
                        onChange={(e) => field.onChange(e.target.value === '' ? null : Number(e.target.value))}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label>الوسوم</Label>
              <TaskTagPicker value={tags} onChange={setTags} />
            </div>

            {isEdit && (
              <FormField
                control={form.control}
                name="progress_percent"
                render={({ field }) => (
                  <FormItem>
                    <div className="flex items-center justify-between">
                      <FormLabel>نسبة الإنجاز</FormLabel>
                      <span className="num text-sm font-medium text-ink-2">{field.value}%</span>
                    </div>
                    <FormControl>
                      <input
                        type="range"
                        min={0}
                        max={100}
                        step={5}
                        value={field.value}
                        onChange={(e) => field.onChange(Number(e.target.value))}
                        className="w-full accent-brand"
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التغييرات' : 'إنشاء المهمة'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
