import type { AxiosProgressEvent } from 'axios';

import { apiClient } from '@/lib/api/client';
import type { ApiResource, TaskAttachment } from '@/lib/api/types';

/**
 * File attachments for a single task. Uploads go through multipart
 * `FormData` so the API can stream them to storage; `onUploadProgress` drives
 * the per-file progress bar in `<TaskAttachments>`.
 */
export const taskAttachmentsApi = {
  list: (taskId: number) => apiClient.get<ApiResource<TaskAttachment[]>>(`/tasks/${taskId}/attachments`),
  upload: (taskId: number, file: File, onUploadProgress?: (event: AxiosProgressEvent) => void) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<TaskAttachment>>(`/tasks/${taskId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress,
    });
  },
  delete: (attachmentId: number) => apiClient.delete(`/attachments/${attachmentId}`),
};
