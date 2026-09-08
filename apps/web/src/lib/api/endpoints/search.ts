import { apiClient } from '@/lib/api/client';
import type { ApiResource } from '@/lib/api/types';

/** One result row from any of the four indexed types. */
export type SearchResult = {
  type: 'employee' | 'task' | 'request' | 'leave';
  id: number;
  title: string;
  subtitle: string;
  url: string;
};

/**
 * Response shape from GET /api/search. Keys mirror the backend's
 * GlobalSearchService — one bucket per indexed model.
 */
export type SearchResponse = {
  employees: SearchResult[];
  tasks: SearchResult[];
  requests: SearchResult[];
  leaves: SearchResult[];
};

export const searchApi = {
  query: (q: string, limit = 5) =>
    apiClient.get<ApiResource<SearchResponse>>('/search', {
      params: { q, limit },
    }),
};
