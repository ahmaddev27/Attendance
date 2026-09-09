import axios from 'axios';

import { useAuthStore } from '@/lib/stores/auth-store';

export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
});

apiClient.interceptors.request.use((config) => {
  // Read the token straight from the zustand-persisted auth store so
  // there's a single source of truth and no chance of it lagging
  // behind after logout / user switch.
  const token = useAuthStore.getState().token;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

apiClient.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401 && typeof window !== 'undefined') {
      // Full logout — clears the persisted zustand user AND
      // disconnects Echo — instead of only wiping the token key
      // (which used to leave `user` hydrated in the store).
      useAuthStore.getState().logout();
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);
