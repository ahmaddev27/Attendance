'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
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
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { TaskPriorityBadge } from '@/components/tasks/task-priority-badge';
import { TaskProgressBar } from '@/components/tasks/task-progress-bar';
import { TaskStatusBadge } from '@/components/tasks/task-status-badge';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { myTasksApi } from '@/lib/api/endpoints/tasks';
import { taskStatusesApi } from '@/lib/api/endpoints/task-config';
import { formatDate } from '@/lib/attendance-format';
import { DUE_DATE_URGENCY_CLASSNAME, getDueDateUrgency } from '@/lib/task-format';
import type { EmployeeSummary, Task } from '@/lib/api/types';
import { cn } from '@/lib/utils';

const PER_PAGE = 20;

type TabKey = 'assigned' | 'created';

/** The "other party" column shown per tab: who assigned it to me, or who I assigned it to. */
function counterpartOf(task: Task, tab: TabKey): EmployeeSummary | null {
  return tab === 'assigned' ? task.creator : task.assignee;
}

export default function MyTasksPage() {
  const router = useRouter();
  const [tab, setTab] = React.useState<TabKey>('assigned');
  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [statusId, setStatusId] = React.useState<string | undefined>();
  const [dueFrom, setDueFrom] = React.useState('');
  const [dueTo, setDueTo] = React.useState('');

  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [tab, debouncedSearch, statusId, dueFrom, dueTo]);

  const { data: statuses } = useQuery({
    queryKey: ['task-statuses'],
    queryFn: async () => (await taskStatusesApi.list()).data.data,
  });

  const listParams = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status_id: statusId ? Number(statusId) : undefined,
    due_date_from: dueFrom || undefined,
    due_date_to: dueTo || undefined,
  };

  const { data, isLoading } = useQuery({
    queryKey: ['my-tasks', tab, listParams],
    queryFn: async () => {
      const response = tab === 'assigned' ? await myTasksApi.assigned(listParams) : await myTasksApi.created(listParams);
      return response.data;
    },
    placeholderData: keepPreviousData,
  });

  const columns: DataTableColumn<Task>[] = [
    {
      key: 'title',
      header: 'العنوان',
      cell: (task) => (
        <button
          type="button"
          onClick={() => router.push(`/my-tasks/${task.id}`)}
          className="max-w-[220px] truncate text-start font-medium text-brand-ink hover:underline"
          title={task.title}
        >
          {task.title}
        </button>
      ),
    },
    { key: 'status', header: 'الحالة', cell: (task) => <TaskStatusBadge status={task.status} /> },
    { key: 'priority', header: 'الأولوية', cell: (task) => <TaskPriorityBadge priority={task.priority} /> },
    {
      key: 'counterpart',
      header: tab === 'assigned' ? 'أنشأها' : 'المسند إليه',
      cell: (task) => {
        const person = counterpartOf(task, tab);
        return person ? (
          <div className="flex items-center gap-2">
            <EmployeeAvatar employee={person} size={24} />
            <span className="max-w-[120px] truncate text-sm text-ink">{person.full_name}</span>
          </div>
        ) : (
          <span className="text-xs text-muted">—</span>
        );
      },
    },
    {
      key: 'due_date',
      header: 'تاريخ الاستحقاق',
      cell: (task) => {
        if (!task.due_date) return <span className="text-xs text-muted">—</span>;
        const urgency = getDueDateUrgency(task.due_date, task.completed_at);
        return (
          <span className={cn('num text-xs font-medium', DUE_DATE_URGENCY_CLASSNAME[urgency])}>
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
      <div>
        <p className="text-xs font-medium text-muted">مهامي</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">المهام</h1>
      </div>

      <Tabs value={tab} onValueChange={(v) => setTab(v as TabKey)}>
        <TabsList>
          <TabsTrigger value="assigned">المسندة إليّ</TabsTrigger>
          <TabsTrigger value="created">التي أنشأتها</TabsTrigger>
        </TabsList>

        {/* One content pane, driven by `tab` — both tabs share the same
            table shape and only the data source/column labels differ, so a
            single dynamic panel avoids duplicating that markup. */}
        <TabsContent value={tab} className="space-y-4">
          <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
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
            emptyMessage={tab === 'assigned' ? 'لا توجد مهام مسندة إليك' : 'لم تنشئ أي مهام بعد'}
            pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
          />
        </TabsContent>
      </Tabs>

    </div>
  );
}
