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
      for (const tasks of Object.values(board ?? {})) {
        const found = tasks.find((t) => t.id === taskId);
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
        const movedTask = Object.values(previousBoard)
          .flat()
          .find((t) => t.id === taskId);

        if (movedTask) {
          const nextBoard: KanbanBoardData = {};
          for (const [code, tasks] of Object.entries(previousBoard)) {
            nextBoard[code] = tasks.filter((t) => t.id !== taskId);
          }
          nextBoard[targetStatus.code] = [
            { ...movedTask, status: targetStatus },
            ...(nextBoard[targetStatus.code] ?? []),
          ];
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
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-96 rounded-xl" />
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
      <div className="flex gap-4 overflow-x-auto pb-2">
        {sortedStatuses.map((status) => (
          <KanbanColumn key={status.id} status={status} tasks={board?.[status.code] ?? []} onTaskClick={onTaskClick} />
        ))}
      </div>
      <DragOverlay>{activeTask && <TaskCard task={activeTask} dragOverlay />}</DragOverlay>
    </DndContext>
  );
}
