import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  TaskPriority,
  TaskPriorityPayload,
  TaskStatus,
  TaskStatusPayload,
  TaskTag,
  TaskTagPayload,
} from '@/lib/api/types';

export const taskStatusesApi = {
  list: () => apiClient.get<ApiResource<TaskStatus[]>>('/task-statuses'),
  create: (payload: TaskStatusPayload) => apiClient.post<ApiResource<TaskStatus>>('/task-statuses', payload),
  update: (id: number, payload: TaskStatusPayload) =>
    apiClient.put<ApiResource<TaskStatus>>(`/task-statuses/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/task-statuses/${id}`),
};

export const taskPrioritiesApi = {
  list: () => apiClient.get<ApiResource<TaskPriority[]>>('/task-priorities'),
  create: (payload: TaskPriorityPayload) => apiClient.post<ApiResource<TaskPriority>>('/task-priorities', payload),
  update: (id: number, payload: TaskPriorityPayload) =>
    apiClient.put<ApiResource<TaskPriority>>(`/task-priorities/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/task-priorities/${id}`),
};

export const taskTagsApi = {
  list: () => apiClient.get<ApiResource<TaskTag[]>>('/task-tags'),
  create: (payload: TaskTagPayload) => apiClient.post<ApiResource<TaskTag>>('/task-tags', payload),
  update: (id: number, payload: TaskTagPayload) => apiClient.put<ApiResource<TaskTag>>(`/task-tags/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/task-tags/${id}`),
};
