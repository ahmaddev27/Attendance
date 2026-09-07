import { apiClient } from '@/lib/api/client';
import type { ApiResource, TaskComment, TaskCommentPayload } from '@/lib/api/types';

/** Comment thread for a single task — nested one level via `parent_id`. */
export const taskCommentsApi = {
  list: (taskId: number) => apiClient.get<ApiResource<TaskComment[]>>(`/tasks/${taskId}/comments`),
  create: (taskId: number, payload: TaskCommentPayload) =>
    apiClient.post<ApiResource<TaskComment>>(`/tasks/${taskId}/comments`, payload),
  update: (commentId: number, payload: Pick<TaskCommentPayload, 'body' | 'mentions'>) =>
    apiClient.put<ApiResource<TaskComment>>(`/comments/${commentId}`, payload),
  delete: (commentId: number) => apiClient.delete(`/comments/${commentId}`),
};
