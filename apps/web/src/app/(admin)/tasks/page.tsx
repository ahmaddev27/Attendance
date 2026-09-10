'use client';

import * as React from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';

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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { DeleteTaskDialog } from '@/components/tasks/delete-task-dialog';
import { KanbanBoard } from '@/components/tasks/kanban-board';
import { TaskDetailDialog } from '@/components/tasks/task-detail-dialog';
import { TaskFormDialog } from '@/components/tasks/task-form-dialog';
import { TaskPriorityBadge } from '@/components/tasks/task-priority-badge';
import { TaskProgressBar } from '@/components/tasks/task-progress-bar';
import { TaskStatusBadge } from '@/components/tasks/task-status-badge';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { taskPrioritiesApi, taskStatusesApi, taskTagsApi } from '@/lib/api/endpoints/task-config';
import { tasksApi } from '@/lib/api/endpoints/tasks';
import { formatDate } from '@/lib/attendance-format';
import { DUE_DATE_URGENCY_CLASSNAME, getDueDateUrgency } from '@/lib/task-format';
import type { EmployeeSummary, Task } from '@/lib/api/types';
import { cn } from '@/lib/utils';

const PER_PAGE = 20;

export default function TasksPage() {
  const [view, setView] = React.useState<'kanban' | 'list'>('kanban');

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [statusId, setStatusId] = React.useState<string | undefined>();
  const [priorityId, setPriorityId] = React.useState<string | undefined>();
  const [tagId, setTagId] = React.useState<string | undefined>();
  const [assignee, setAssignee] = React.useState<EmployeeSummary | null>(null);
  const [dueFrom, setDueFrom] = React.useState('');
  const [dueTo, setDueTo] = React.useState('');

  const [selectedTaskId, setSelectedTaskId] = React.useState<number | null>(null);
  const [formOpen, setFormOpen] = React.useState(false);
  const [editingTask, setEditingTask] = React.useState<Task | null>(null);
  const [deletingTask, setDeletingTask] = React.useState<Task | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusId, priorityId, tagId, assignee?.id, dueFrom, dueTo]);

  const { data: statuses } = useQuery({
    queryKey: ['task-statuses'],
    queryFn: async () => (await taskStatusesApi.list()).data.data,
  });
  const { data: priorities } = useQuery({
    queryKey: ['task-priorities'],
    queryFn: async () => (await taskPrioritiesApi.list()).data.data,
  });
  const { data: tags } = useQuery({
    queryKey: ['task-tags'],
    queryFn: async () => (await taskTagsApi.list()).data.data,
  });

  const { data, isLoading } = useQuery({
    queryKey: [
      'tasks',
      'list',
      { page, search: debouncedSearch, statusId, priorityId, tagId, assigneeId: assignee?.id, dueFrom, dueTo },
    ],
    queryFn: async () => {
      const { data } = await tasksApi.list({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch || undefined,
        status_id: statusId ? Number(statusId) : undefined,
        priority_id: priorityId ? Number(priorityId) : undefined,
        tag_id: tagId ? Number(tagId) : undefined,
        assigned_to: assignee?.id,
        due_date_from: dueFrom || undefined,
        due_date_to: dueTo || undefined,
      });
      return data;
    },
    placeholderData: keepPreviousData,
    enabled: view === 'list',
  });

  const openCreateDialog = () => {
    setEditingTask(null);
    setFormOpen(true);
  };

  const openEditDialog = (task: Task) => {
    setEditingTask(task);
    setFormOpen(true);
  };

  const columns: DataTableColumn<Task>[] = [
    {
      key: 'title',
      header: 'العنوان',
      cell: (task) => (
        <button
          type="button"
          onClick={() => setSelectedTaskId(task.id)}
          className="max-w-[220px] truncate text-start font-medium text-brand-ink hover:underline"
          title={task.title}
        >
          {task.title}
        </button>
      ),
    },
    {
      key: 'status',
      header: 'الحالة',
      cell: (task) => <TaskStatusBadge status={task.status} />,
    },
    {
      key: 'priority',
      header: 'الأولوية',
      cell: (task) => <TaskPriorityBadge priority={task.priority} />,
    },
    {
      key: 'assignee',
      header: 'المسند إليه',
      cell: (task) =>
        task.assignee ? (
          <div className="flex items-center gap-2">
            <EmployeeAvatar employee={task.assignee} size={24} />
            <span className="max-w-[120px] truncate text-sm text-ink">{task.assignee.full_name}</span>
          </div>
        ) : (
          <span className="text-xs text-muted">—</span>
        ),
    },
    {
      key: 'due_date',
      header: 'تاريخ الاستحقاق',
      cell: (task) => {
        if (!task.due_date) return <span className="text-xs text-muted">—</span>;
        const urgency = getDueDateUrgency(task.due_date, task.completed_at);
        return (
          <span className={cn('num text-xs font-medium', DUE_DATE_URGENCY_CLASSNAME[urgency])} dir="ltr">
            {formatDate(task.due_date)}
          </span>
        );
      },
    },
    {
      key: 'progress',
      header: 'التقدم',
      className: 'w-32',
      cell: (task) => (
        <div className="flex items-center gap-2">
          <TaskProgressBar value={task.progress_percent} className="w-16" />
          <span className="num text-xs text-muted" dir="ltr">
            {task.progress_percent}%
          </span>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">إدارة العمل</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">المهام</h1>
        </div>
        <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          مهمة جديدة
        </Button>
      </div>

      <Tabs value={view} onValueChange={(v) => setView(v as 'kanban' | 'list')}>
        <TabsList>
          <TabsTrigger value="kanban">Kanban</TabsTrigger>
          <TabsTrigger value="list">قائمة</TabsTrigger>
        </TabsList>

        <TabsContent value="kanban">
          <KanbanBoard onTaskClick={(task) => setSelectedTaskId(task.id)} />
        </TabsContent>

        <TabsContent value="list" className="space-y-4">
          <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div className="lg:col-span-2">
              <Label className="text-xs font-semibold text-ink-2">بحث</Label>
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="ابحث بعنوان المهمة..."
                className="mt-1.5"
              />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
              <Select value={statusId ?? '__all__'} onValueChange={(v) => setStatusId(v === '__all__' ? undefined : v)}>
                <SelectTrigger className="mt-1.5">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">كل الحالات</SelectItem>
                  {(statuses ?? []).map((status) => (
                    <SelectItem key={status.id} value={String(status.id)}>
                      {status.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الأولوية</Label>
              <Select value={priorityId ?? '__all__'} onValueChange={(v) => setPriorityId(v === '__all__' ? undefined : v)}>
                <SelectTrigger className="mt-1.5">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">كل الأولويات</SelectItem>
                  {(priorities ?? []).map((priority) => (
                    <SelectItem key={priority.id} value={String(priority.id)}>
                      {priority.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الوسم</Label>
              <Select value={tagId ?? '__all__'} onValueChange={(v) => setTagId(v === '__all__' ? undefined : v)}>
                <SelectTrigger className="mt-1.5">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">كل الوسوم</SelectItem>
                  {(tags ?? []).map((tag) => (
                    <SelectItem key={tag.id} value={String(tag.id)}>
                      {tag.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">المسند إليه</Label>
              <div className="mt-1.5">
                <EmployeeSearchSelect value={assignee} onChange={setAssignee} />
              </div>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الاستحقاق من</Label>
              <div className="mt-1.5">
                <DatePicker value={dueFrom} onChange={setDueFrom} placeholder="اختر تاريخاً" max={dueTo || undefined} />
              </div>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الاستحقاق إلى</Label>
              <div className="mt-1.5">
                <DatePicker value={dueTo} onChange={setDueTo} placeholder="اختر تاريخاً" min={dueFrom || undefined} />
              </div>
            </div>
          </div>

          <DataTable
            columns={columns}
            data={data?.data ?? []}
            rowKey={(task) => task.id}
            isLoading={isLoading}
            emptyMessage="لا توجد مهام مطابقة لبحثك"
            actions={[
              { label: 'تعديل', icon: Pencil, onClick: openEditDialog },
              { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingTask },
            ]}
            pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
          />
        </TabsContent>
      </Tabs>

      <TaskFormDialog open={formOpen} onOpenChange={setFormOpen} task={editingTask} />
      <DeleteTaskDialog task={deletingTask} open={!!deletingTask} onOpenChange={(open) => !open && setDeletingTask(null)} />
      <TaskDetailDialog
        taskId={selectedTaskId}
        onOpenChange={(open) => !open && setSelectedTaskId(null)}
        onNavigate={setSelectedTaskId}
      />
    </div>
  );
}
