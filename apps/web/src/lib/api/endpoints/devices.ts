import { apiClient } from '../client';
import type { ApiResource, AttendanceDevice, AttendanceDevicePayload } from '../types';

export const devicesApi = {
  list: () =>
    apiClient
      .get<ApiResource<AttendanceDevice[]>>('/attendance-devices')
      .then((r) => r.data.data),

  get: (id: number) =>
    apiClient
      .get<ApiResource<AttendanceDevice>>(`/attendance-devices/${id}`)
      .then((r) => r.data.data),

  create: (payload: AttendanceDevicePayload) =>
    apiClient
      .post<ApiResource<AttendanceDevice>>('/attendance-devices', payload)
      .then((r) => r.data.data),

  update: (id: number, payload: AttendanceDevicePayload) =>
    apiClient
      .put<ApiResource<AttendanceDevice>>(`/attendance-devices/${id}`, payload)
      .then((r) => r.data.data),

  remove: (id: number) => apiClient.delete(`/attendance-devices/${id}`).then(() => undefined),

  /** Regenerates the device's QR token. */
  rotate: (id: number) =>
    apiClient
      .post<ApiResource<AttendanceDevice>>(`/attendance-devices/${id}/rotate`)
      .then((r) => r.data.data),
};
