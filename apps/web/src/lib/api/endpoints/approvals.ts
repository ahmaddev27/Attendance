import { apiClient } from '@/lib/api/client';
import { requestsApi } from '@/lib/api/endpoints/requests';
import type { ApiResource, RequestSummary } from '@/lib/api/types';

/**
 * Personalized inbox of requests waiting on the logged-in user's decision.
 * The decision actions themselves live on the request resource
 * (`/requests/{id}/{approve|reject|return|forward}`) — re-exported here so
 * the inbox UI doesn't need to reach into the admin requests module.
 */
export const approvalsInboxApi = {
  list: () => apiClient.get<ApiResource<RequestSummary[]>>('/approvals/inbox'),
  approve: requestsApi.approve,
  reject: requestsApi.reject,
  return: requestsApi.return,
  forward: requestsApi.forward,
};
