'use client';

import * as React from 'react';
import {
  closestCorners,
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { KanbanColumn } from '@/components/tasks/kanban-column';
import { TaskCard } from '@/components/tasks/task-card';
import { Skeleton } from '@/components/ui/skeleton';
import { taskStatusesApi } from '@/lib/api/endpoints/task-config';
import { tasksApi } from '@/lib/api/endpoints/tasks';
import type { KanbanBoard as KanbanBoardData, Task } from '@/lib/api/types';

const KANBAN_QUERY_KEY = ['tasks', 'kanban'];

type KanbanBoardProps = {
  onTaskClick: (task: Task) => void;
};

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

/**
 * Drag-and-drop task board grouped by status. Columns come from
 * `/task-statuses` (ordered by `sort_order`); cards come from
 * `/tasks/kanban`, keyed by `status.code`. Dropping a card into another
 * column optimistically moves it in the cache, then PATCHes the task's
 * status — rolled back on failure.
 */
export function KanbanBoard({ onTaskClick }: KanbanBoardProps) {
  const queryClient = useQueryClient();
  const [activeTask, setActiveTask] = React.useState<Task | null>(null);

  const { data: statuses, isLoading: statusesLoading } = useQuery({
    queryKey: ['task-statuses'],
    queryFn: async () => (await taskStatusesApi.list()).data.data,
    staleTime: 60_000,
  });

  const { data: board, isLoading: boardLoading } = useQuery({
    queryKey: KANBAN_QUERY_KEY,
    queryFn: async () => (await tasksApi.kanban()).data.data,
  });

  const sortedStatuses = React.useMemo(
    () => [...(statuses ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [statuses]
  );

  const findTask = React.useCallback(
    (taskId: number): Task | undefined => {
      for (const entry of Object.values(board ?? {})) {
        const found = entry.tasks?.find((t) => t.id === taskId);
        if (found) return found;
      }
      return undefined;
    },
    [board]
  );

  const moveMutation = useMutation({
    mutationFn: ({ taskId, statusId }: { taskId: number; statusId: number }) =>
      tasksApi.update(taskId, { status_id: statusId }),
    onMutate: async ({ taskId, statusId }) => {
      await queryClient.cancelQueries({ queryKey: KANBAN_QUERY_KEY });
      const previousBoard = queryClient.getQueryData<KanbanBoardData>(KANBAN_QUERY_KEY);
      const targetStatus = sortedStatuses.find((s) => s.id === statusId);

      if (previousBoard && targetStatus) {
        // The board is now `Record<code, { status, tasks, count_total }>` —
        // flatten by pulling each entry's `.tasks` array to find the row,
        // then move it across columns while keeping `count_total` in
        // sync so the "+ N more" hint doesn't briefly show a stale total.
        const movedTask = Object.values(previousBoard)
          .flatMap((entry) => entry.tasks ?? [])
          .find((t) => t.id === taskId);
        const sourceCode = movedTask?.status?.code;

        if (movedTask) {
          const nextBoard: KanbanBoardData = {};
          for (const [code, entry] of Object.entries(previousBoard)) {
            const wasHere = (entry.tasks ?? []).some((t) => t.id === taskId);
            nextBoard[code] = {
              status: entry.status,
              tasks: (entry.tasks ?? []).filter((t) => t.id !== taskId),
              count_total: Math.max(0, (entry.count_total ?? entry.tasks?.length ?? 0) - (wasHere ? 1 : 0)),
            };
          }
          const targetEntry = nextBoard[targetStatus.code];
          const previousTargetEntry = previousBoard[targetStatus.code];
          nextBoard[targetStatus.code] = {
            status: targetStatus,
            tasks: [
              { ...movedTask, status: targetStatus },
              ...((targetEntry?.tasks) ?? []),
            ],
            count_total: (previousTargetEntry?.count_total ?? previousTargetEntry?.tasks?.length ?? 0)
              + (sourceCode === targetStatus.code ? 0 : 1),
          };
          queryClient.setQueryData(KANBAN_QUERY_KEY, nextBoard);
        }
      }

      return { previousBoard };
    },
    onError: (err, _vars, context) => {
      if (context?.previousBoard) queryClient.setQueryData(KANBAN_QUERY_KEY, context.previousBoard);
      toast.error(extractErrorMessage(err, 'تعذر نقل المهمة'));
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: KANBAN_QUERY_KEY });
      queryClient.invalidateQueries({ queryKey: ['tasks', 'list'] });
    },
  });

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const handleDragStart = (event: DragStartEvent) => {
    setActiveTask(findTask(Number(event.active.id)) ?? null);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    setActiveTask(null);
    const { active, over } = event;
    if (!over) return;

    const task = findTask(Number(active.id));
    const targetStatusCode = String(over.id);
    if (!task || task.status.code === targetStatusCode) return;

    const targetStatus = sortedStatuses.find((s) => s.code === targetStatusCode);
    if (!targetStatus) return;

    moveMutation.mutate({ taskId: task.id, statusId: targetStatus.id });
  };

  if (statusesLoading || boardLoading) {
    return (
      <div className="flex gap-4 overflow-x-auto pb-2">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-[calc(100vh-260px)] w-80 shrink-0 rounded-xl" />
        ))}
      </div>
    );
  }

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCorners}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <div
        className="flex h-[calc(100vh-260px)] min-h-[520px] gap-4 overflow-x-auto overflow-y-hidden pb-3
          [&::-webkit-scrollbar]:h-2
          [&::-webkit-scrollbar-thumb]:rounded-full
          [&::-webkit-scrollbar-thumb]:bg-hairline
          hover:[&::-webkit-scrollbar-thumb]:bg-muted
          [&::-webkit-scrollbar-track]:bg-transparent"
      >
        {sortedStatuses.map((status) => {
          const entry = board?.[status.code];
          return (
            <KanbanColumn
              key={status.id}
              status={status}
              tasks={entry?.tasks ?? []}
              countTotal={entry?.count_total}
              onTaskClick={onTaskClick}
            />
          );
        })}
      </div>
      <DragOverlay>{activeTask && <TaskCard task={activeTask} dragOverlay />}</DragOverlay>
    </DndContext>
  );
}
