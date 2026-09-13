import { apiClient } from '@/lib/api/client';
import type {
  AdvanceJobStagePayload,
  ApiResource,
  Client,
  ClientContact,
  ClientContactPayload,
  ClientListParams,
  ClientPayload,
  ClientProfile,
  ConvertLeadPayload,
  ConvertLeadResult,
  JobRequirement,
  JobRequirementListParams,
  JobRequirementPayload,
  JobRequirementUpdatePayload,
  Lead,
  LeadActivity,
  LeadActivityPayload,
  LeadKanbanData,
  LeadListParams,
  LeadPayload,
  PaginatedResponse,
  RecruitmentCase,
  RecruitmentCaseListParams,
  RecruitmentCasePayload,
  RecruitmentDashboardFunnel,
  RecruitmentDashboardKpis,
  RecruitmentLeaderboard,
  RecruitmentPipeline,
  RecruitmentPipelinePayload,
  RecruitmentPipelineStage,
  RecruitmentPipelineStagePayload,
} from '@/lib/api/types';

/**
 * Recruitment REST clients — one entry per backend route bundle. Every
 * endpoint returns the raw axios response so the React Query hook keeps
 * the freedom to unwrap `.data.data` (Resource envelope) vs `.data`
 * (plain JsonResponse). Kept in a single file because the surface is
 * cross-referential (leads/convert → clients + cases + jobs, jobs read
 * pipelines/stages, etc.) and colocating avoids circular imports.
 */

/** Leads: CRUD + kanban + convert + CSV export. */
export const leadsApi = {
  list: (params?: LeadListParams) =>
    apiClient.get<PaginatedResponse<Lead>>('/leads', { params }),
  get: (id: number) => apiClient.get<ApiResource<Lead>>(`/leads/${id}`),
  create: (payload: LeadPayload) => apiClient.post<ApiResource<Lead>>('/leads', payload),
  update: (id: number, payload: Partial<LeadPayload>) =>
    apiClient.patch<ApiResource<Lead>>(`/leads/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/leads/${id}`),
  /** Kanban shape: `{ data: { [status]: LeadSummary[] } }`. */
  kanban: (params?: Pick<LeadListParams, 'owner_id' | 'source' | 'country' | 'industry' | 'search'>) =>
    apiClient.get<{ data: LeadKanbanData }>('/leads/kanban', { params }),
  convert: (id: number, payload: ConvertLeadPayload) =>
    apiClient.post<ApiResource<ConvertLeadResult>>(`/leads/${id}/convert`, payload),
  /**
   * Backend streams the CSV directly — request as blob so the browser
   * saves the raw bytes instead of axios trying to parse them as JSON.
   */
  exportCsv: (params?: LeadListParams) =>
    apiClient.get<Blob>('/leads/export', { params, responseType: 'blob' }),
};

/** Lead timeline: user-logged activities (call/meeting/email/note). */
export const leadActivitiesApi = {
  list: (leadId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<LeadActivity>>(`/leads/${leadId}/activities`, { params }),
  create: (leadId: number, payload: LeadActivityPayload) =>
    apiClient.post<ApiResource<LeadActivity>>(`/leads/${leadId}/activities`, payload),
};

/** Clients: CRUD + profile bundle (contacts + cases summary). */
export const clientsApi = {
  list: (params?: ClientListParams) =>
    apiClient.get<PaginatedResponse<Client>>('/clients', { params }),
  get: (id: number) => apiClient.get<ApiResource<Client>>(`/clients/${id}`),
  create: (payload: ClientPayload) => apiClient.post<ApiResource<Client>>('/clients', payload),
  update: (id: number, payload: Partial<ClientPayload>) =>
    apiClient.patch<ApiResource<Client>>(`/clients/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/clients/${id}`),
  profile: (id: number) => apiClient.get<ApiResource<ClientProfile>>(`/clients/${id}/profile`),
};

/** Client contacts nested under a Client. */
export const clientContactsApi = {
  create: (clientId: number, payload: ClientContactPayload) =>
    apiClient.post<ApiResource<ClientContact>>(`/clients/${clientId}/contacts`, payload),
  update: (clientId: number, contactId: number, payload: Partial<ClientContactPayload>) =>
    apiClient.patch<ApiResource<ClientContact>>(`/clients/${clientId}/contacts/${contactId}`, payload),
  delete: (clientId: number, contactId: number) =>
    apiClient.delete(`/clients/${clientId}/contacts/${contactId}`),
};

/** Recruitment cases: CRUD + nested "for client" listing. */
export const recruitmentCasesApi = {
  list: (params?: RecruitmentCaseListParams) =>
    apiClient.get<PaginatedResponse<RecruitmentCase>>('/recruitment-cases', { params }),
  get: (id: number) => apiClient.get<ApiResource<RecruitmentCase>>(`/recruitment-cases/${id}`),
  create: (payload: RecruitmentCasePayload) =>
    apiClient.post<ApiResource<RecruitmentCase>>('/recruitment-cases', payload),
  update: (id: number, payload: Partial<RecruitmentCasePayload>) =>
    apiClient.patch<ApiResource<RecruitmentCase>>(`/recruitment-cases/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/recruitment-cases/${id}`),
  listForClient: (clientId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<RecruitmentCase>>(`/clients/${clientId}/cases`, { params }),
};

/** Job requirements: CRUD + advance-stage + nested "for case" listing. */
export const jobsApi = {
  list: (params?: JobRequirementListParams) =>
    apiClient.get<PaginatedResponse<JobRequirement>>('/jobs', { params }),
  get: (id: number) => apiClient.get<ApiResource<JobRequirement>>(`/jobs/${id}`),
  create: (payload: JobRequirementPayload) =>
    apiClient.post<ApiResource<JobRequirement>>('/jobs', payload),
  update: (id: number, payload: JobRequirementUpdatePayload) =>
    apiClient.patch<ApiResource<JobRequirement>>(`/jobs/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/jobs/${id}`),
  advanceStage: (id: number, payload: AdvanceJobStagePayload) =>
    apiClient.post<ApiResource<JobRequirement>>(`/jobs/${id}/advance-stage`, payload),
  cancel: (id: number) => apiClient.post<ApiResource<JobRequirement>>(`/jobs/${id}/cancel`),
  listForCase: (caseId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<JobRequirement>>(`/recruitment-cases/${caseId}/jobs`, { params }),
};

/** Pipelines admin: CRUD pipelines + nested stages + reorder. */
export const recruitmentPipelinesApi = {
  list: (params?: { page?: number; per_page?: number; active_only?: boolean }) =>
    apiClient.get<PaginatedResponse<RecruitmentPipeline>>('/recruitment-pipelines', { params }),
  get: (id: number) => apiClient.get<ApiResource<RecruitmentPipeline>>(`/recruitment-pipelines/${id}`),
  create: (payload: RecruitmentPipelinePayload) =>
    apiClient.post<ApiResource<RecruitmentPipeline>>('/recruitment-pipelines', payload),
  update: (id: number, payload: Partial<RecruitmentPipelinePayload>) =>
    apiClient.patch<ApiResource<RecruitmentPipeline>>(`/recruitment-pipelines/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/recruitment-pipelines/${id}`),
};

export const recruitmentPipelineStagesApi = {
  create: (pipelineId: number, payload: RecruitmentPipelineStagePayload) =>
    apiClient.post<ApiResource<RecruitmentPipelineStage>>(
      `/recruitment-pipelines/${pipelineId}/stages`,
      payload,
    ),
  update: (pipelineId: number, stageId: number, payload: Partial<RecruitmentPipelineStagePayload>) =>
    apiClient.patch<ApiResource<RecruitmentPipelineStage>>(
      `/recruitment-pipelines/${pipelineId}/stages/${stageId}`,
      payload,
    ),
  delete: (pipelineId: number, stageId: number) =>
    apiClient.delete(`/recruitment-pipelines/${pipelineId}/stages/${stageId}`),
  reorder: (pipelineId: number, stageIds: number[]) =>
    apiClient.post<{ data: RecruitmentPipelineStage[] }>(
      `/recruitment-pipelines/${pipelineId}/stages/reorder`,
      { stage_ids: stageIds },
    ),
};

/** Recruitment dashboard: KPIs, funnel, leaderboard. */
export const recruitmentDashboardApi = {
  kpis: () => apiClient.get<ApiResource<RecruitmentDashboardKpis>>('/recruitment/dashboard/kpis'),
  funnel: () => apiClient.get<ApiResource<RecruitmentDashboardFunnel>>('/recruitment/dashboard/funnel'),
  leaderboard: () => apiClient.get<RecruitmentLeaderboard>('/recruitment/dashboard/leaderboard'),
};
