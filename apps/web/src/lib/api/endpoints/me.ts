import { apiClient } from '@/lib/api/client';
import type { ApiResource } from '@/lib/api/types';

export type MotivationCard = {
  message: string;
  generated_at: string;
  cached: boolean;
};

export const meMotivationApi = {
  get: () => apiClient.get<ApiResource<MotivationCard>>('/me/motivation'),
};

export const getMotivation = async (): Promise<MotivationCard> =>
  (await meMotivationApi.get()).data.data;
