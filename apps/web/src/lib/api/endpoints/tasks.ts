import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  KanbanBoard,
  PaginatedResponse,
  Task,
  TaskDetail,
  TaskListParams,
  TaskPayload,
} from '@/lib/api/types';

/** Admin-facing task CRUD, completion/restore workflow, and the Kanban board. */
export const tasksApi = {
  list: (params?: TaskListParams) => apiClient.get<PaginatedResponse<Task>>('/tasks', { params }),
  get: (id: number) => apiClient.get<ApiResource<TaskDetail>>(`/tasks/${id}`),
  create: (payload: TaskPayload) => apiClient.post<ApiResource<TaskDetail>>('/tasks', payload),
  update: (id: number, payload: Partial<TaskPayload>) =>
    apiClient.put<ApiResource<TaskDetail>>(`/tasks/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/tasks/${id}`),
  complete: (id: number) => apiClient.post<ApiResource<TaskDetail>>(`/tasks/${id}/complete`),
  restore: (id: number) => apiClient.post<ApiResource<TaskDetail>>(`/tasks/${id}/restore`),
  kanban: (params?: Pick<TaskListParams, 'assigned_to' | 'created_by' | 'search'>) =>
    apiClient.get<ApiResource<KanbanBoard>>('/tasks/kanban', { params }),
};

/** Logged-in employee's own tasks — assigned to them or created by them. */
export const myTasksApi = {
  assigned: (params?: Omit<TaskListParams, 'assigned_to'>) =>
    apiClient.get<PaginatedResponse<Task>>('/me/tasks', { params }),
  created: (params?: Omit<TaskListParams, 'created_by'>) =>
    apiClient.get<PaginatedResponse<Task>>('/me/tasks/created', { params }),
};
