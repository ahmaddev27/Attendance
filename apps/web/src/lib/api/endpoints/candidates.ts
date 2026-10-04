import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  ApplicationListParams,
  AttachCandidatePayload,
  Candidate,
  CandidateApplication,
  CandidateImportDryRunResult,
  CandidateImportJob,
  CandidateListParams,
  CandidatePayload,
  CandidateScreening,
  FeedbackPayload,
  Interview,
  InterviewFeedback,
  InterviewListParams,
  InterviewPayload,
  InterviewRecommendation,
  PaginatedResponse,
  RejectApplicationPayload,
  ScreeningPayload,
  ScreeningSchema,
  UpdateApplicationPayload,
} from '@/lib/api/types';

/**
 * Recruitment Phase 2 REST clients — Candidates, Applications,
 * Shortlist, Screening, Interviews, CSV Import. Mirrors the pattern
 * established in `recruitment.ts` and `employees.ts`: each entry
 * returns the raw axios response so the React Query hook keeps the
 * freedom to unwrap `.data.data` (Resource envelope) vs `.data`
 * (plain JsonResponse).
 */

/** Candidate bank CRUD + resume upload + CSV import pipeline. */
export const candidatesApi = {
  list: (params?: CandidateListParams) =>
    apiClient.get<PaginatedResponse<Candidate>>('/candidates', { params }),
  get: (id: number) => apiClient.get<ApiResource<Candidate>>(`/candidates/${id}`),
  create: (payload: CandidatePayload) =>
    apiClient.post<ApiResource<Candidate>>('/candidates', payload),
  update: (id: number, payload: Partial<CandidatePayload>) =>
    apiClient.patch<ApiResource<Candidate>>(`/candidates/${id}`, payload),
  remove: (id: number) => apiClient.delete(`/candidates/${id}`),

  /**
   * All applications this candidate has ever made across every job —
   * powers the "Applications" tab on the candidate profile.
   */
  applications: (id: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<CandidateApplication>>(`/candidates/${id}/applications`, { params }),

  /**
   * Resume upload. Backend stores on the PRIVATE disk and refreshes
   * the signed download URL on the candidate resource — the caller
   * refetches the candidate to pick up the new link.
   */
  uploadResume: (id: number, file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<Candidate>>(`/candidates/${id}/resume`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  // ---- CSV import (per-job) --------------------------------------------
  //
  // The import pipeline is scoped to a job because each row attaches a
  // (candidate, application) pair to THAT job. Template is a Blob so
  // the browser can save it directly; dry-run is a preview, store is
  // the actual run which returns a job id to poll via importStatus.

  importTemplate: (jobId: number) =>
    apiClient.get<Blob>(`/jobs/${jobId}/applications/import/template`, {
      responseType: 'blob',
    }),

  importDryRun: (jobId: number, file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<CandidateImportDryRunResult>>(
      `/jobs/${jobId}/applications/import/dry-run`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
  },

  importStore: (jobId: number, file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<CandidateImportJob>>(
      `/jobs/${jobId}/applications/import`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
  },

  importStatus: (jobId: number, importJobId: number) =>
    apiClient.get<ApiResource<CandidateImportJob>>(
      `/jobs/${jobId}/applications/import/${importJobId}`,
    ),
};

/** Per-job applications table + attach-existing-candidate + single-app actions. */
export type AiRecommendation = 'advance' | 'reject' | 'maybe';

export interface AiScreeningResult {
  overall_score: number;
  summary: string;
  strengths: string[];
  concerns: string[];
  skill_match: Record<string, boolean>;
  recommendation: AiRecommendation;
}

export const applicationsApi = {
  listForJob: (jobId: number, params?: ApplicationListParams) =>
    apiClient.get<PaginatedResponse<CandidateApplication>>(
      `/jobs/${jobId}/applications`,
      { params },
    ),
  get: (id: number) => apiClient.get<ApiResource<CandidateApplication>>(`/applications/${id}`),
  update: (id: number, payload: UpdateApplicationPayload) =>
    apiClient.patch<ApiResource<CandidateApplication>>(`/applications/${id}`, payload),
  reject: (id: number, payload: RejectApplicationPayload) =>
    apiClient.post<ApiResource<CandidateApplication>>(`/applications/${id}/reject`, payload),
  withdraw: (id: number) =>
    apiClient.post<ApiResource<CandidateApplication>>(`/applications/${id}/withdraw`),
  attach: (jobId: number, payload: AttachCandidatePayload) =>
    apiClient.post<ApiResource<CandidateApplication>>(`/jobs/${jobId}/applications`, payload),
  aiScreen: (applicationId: number) =>
    apiClient
      .post<ApiResource<AiScreeningResult>>(`/applications/${applicationId}/ai-screen`)
      .then((r) => r.data.data),
};

/** Shortlist flag toggle + per-job shortlist listing. */
export const shortlistApi = {
  add: (applicationId: number) =>
    apiClient.post<ApiResource<CandidateApplication>>(`/applications/${applicationId}/shortlist`),
  remove: (applicationId: number) =>
    apiClient.delete<ApiResource<CandidateApplication>>(`/applications/${applicationId}/shortlist`),
  listForJob: (jobId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<CandidateApplication>>(`/jobs/${jobId}/shortlist`, { params }),
};

/** Screening scorecard (`null` body when nothing is submitted yet). */
export const screeningApi = {
  get: (applicationId: number) =>
    apiClient.get<{ data: CandidateScreening | null }>(`/applications/${applicationId}/screening`),
  submit: (applicationId: number, payload: ScreeningPayload) =>
    apiClient.post<ApiResource<CandidateScreening>>(
      `/applications/${applicationId}/screening`,
      payload,
    ),
  schema: (pipelineId: number, stageId: number) =>
    apiClient.get<{ data: ScreeningSchema | null }>(
      `/recruitment-pipelines/${pipelineId}/stages/${stageId}/screening-schema`,
    ),
};

/** Interviews: calendar listing, CRUD, lifecycle actions, panelist feedback. */
export const interviewsApi = {
  list: (params?: InterviewListParams) =>
    apiClient.get<PaginatedResponse<Interview>>('/interviews', { params }),
  get: (id: number) => apiClient.get<ApiResource<Interview>>(`/interviews/${id}`),
  store: (applicationId: number, payload: InterviewPayload) =>
    apiClient.post<ApiResource<Interview>>(`/applications/${applicationId}/interviews`, payload),
  update: (id: number, payload: Partial<InterviewPayload>) =>
    apiClient.patch<ApiResource<Interview>>(`/interviews/${id}`, payload),
  cancel: (id: number, payload?: { cancelled_reason?: string }) =>
    apiClient.post<ApiResource<Interview>>(`/interviews/${id}/cancel`, payload ?? {}),
  reschedule: (id: number, payload: { scheduled_at: string; duration_minutes?: number }) =>
    apiClient.post<ApiResource<Interview>>(`/interviews/${id}/reschedule`, payload),
  complete: (id: number, payload?: { meeting_notes?: string }) =>
    apiClient.post<ApiResource<Interview>>(`/interviews/${id}/complete`, payload ?? {}),

  feedbacks: (interviewId: number) =>
    apiClient.get<{ data: InterviewFeedback[]; meta?: { average_score: number | null } }>(
      `/interviews/${interviewId}/feedback`,
    ),
  submitFeedback: (interviewId: number, payload: FeedbackPayload) =>
    apiClient.post<ApiResource<InterviewFeedback>>(
      `/interviews/${interviewId}/feedback`,
      payload,
    ),
  updateFeedback: (interviewId: number, feedbackId: number, payload: Partial<FeedbackPayload>) =>
    apiClient.patch<ApiResource<InterviewFeedback>>(
      `/interviews/${interviewId}/feedback/${feedbackId}`,
      payload,
    ),
};

/** Human-friendly labels for the Interview enums shown in the UI. */
export const INTERVIEW_KIND_LABEL: Record<string, string> = {
  internal: 'داخلية',
  client: 'مع العميل',
};

export const INTERVIEW_STATUS_LABEL: Record<string, string> = {
  scheduled: 'مجدولة',
  completed: 'مكتملة',
  cancelled: 'ملغاة',
  no_show: 'لم يحضر',
  rescheduled: 'أُعيدت الجدولة',
};

export const INTERVIEW_RECOMMENDATION_LABEL: Record<InterviewRecommendation, string> = {
  strong_hire: 'توصية قوية بالتوظيف',
  hire: 'توصية بالتوظيف',
  maybe: 'ربما',
  no_hire: 'لا يُوصى بالتوظيف',
};

export const CANDIDATE_STATUS_LABEL: Record<string, string> = {
  active: 'نشط',
  blacklisted: 'محظور',
  placed: 'مُوظَّف',
  inactive: 'غير نشط',
};

export const APPLICATION_STATUS_LABEL: Record<string, string> = {
  applied: 'تقدَّم',
  in_screening: 'قيد الفرز',
  screened_in: 'اجتاز الفرز',
  screened_out: 'لم يجتز الفرز',
  shortlisted: 'ضمن القائمة المختصرة',
  interviewing: 'قيد المقابلات',
  client_review: 'قيد المراجعة من العميل',
  offered: 'تم تقديم العرض',
  rejected: 'مرفوض',
  withdrawn: 'انسحب',
  hired: 'تم التوظيف',
};
