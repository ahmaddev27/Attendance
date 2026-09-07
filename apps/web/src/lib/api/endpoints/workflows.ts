import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  Workflow,
  WorkflowPayload,
  WorkflowStep,
  WorkflowStepPayload,
  WorkflowStepReorderPayload,
} from '@/lib/api/types';

/**
 * Admin workflow CRUD. Workflows are a small, hand-curated config list (like
 * leave types) rather than a paginated collection, so `list` returns the
 * full set in one call.
 */
export const workflowsApi = {
  list: () => apiClient.get<ApiResource<Workflow[]>>('/workflows'),
  get: (id: number) => apiClient.get<ApiResource<Workflow>>(`/workflows/${id}`),
  create: (payload: WorkflowPayload) => apiClient.post<ApiResource<Workflow>>('/workflows', payload),
  update: (id: number, payload: WorkflowPayload) =>
    apiClient.put<ApiResource<Workflow>>(`/workflows/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/workflows/${id}`),
};

/** Step editor for a single workflow — nested under the workflow resource. */
export const workflowStepsApi = {
  list: (workflowId: number) => apiClient.get<ApiResource<WorkflowStep[]>>(`/workflows/${workflowId}/steps`),
  create: (workflowId: number, payload: WorkflowStepPayload) =>
    apiClient.post<ApiResource<WorkflowStep>>(`/workflows/${workflowId}/steps`, payload),
  update: (workflowId: number, stepId: number, payload: WorkflowStepPayload) =>
    apiClient.put<ApiResource<WorkflowStep>>(`/workflows/${workflowId}/steps/${stepId}`, payload),
  delete: (workflowId: number, stepId: number) =>
    apiClient.delete(`/workflows/${workflowId}/steps/${stepId}`),
  reorder: (workflowId: number, payload: WorkflowStepReorderPayload) =>
    apiClient.post(`/workflows/${workflowId}/steps/reorder`, payload),
};
