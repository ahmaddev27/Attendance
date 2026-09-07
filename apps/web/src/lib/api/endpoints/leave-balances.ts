import { apiClient } from '@/lib/api/client';
import type { ApiResource, LeaveBalance } from '@/lib/api/types';

export const leaveBalancesApi = {
  list: (params: { employee_id: number; year?: number }) =>
    apiClient.get<ApiResource<LeaveBalance[]>>('/leave-balances', { params }),
  adjust: (payload: { employee_id: number; leave_type_id: number; year: number; delta: number; reason: string }) =>
    apiClient.post('/leave-balances/adjust', payload),
};
