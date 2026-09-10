'use client';

import { useParams } from 'next/navigation';

import { TaskDetailView } from '@/components/tasks/task-detail-view';

export default function AdminTaskDetailPage() {
  const params = useParams<{ id: string }>();
  const taskId = Number(params.id);

  return <TaskDetailView taskId={taskId} basePath="/tasks" />;
}
