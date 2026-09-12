import { apiClient, primeCsrfCookie } from '@/lib/api/client';

/**
 * Public auth endpoints (no bearer token required). Every call primes
 * the Sanctum CSRF cookie first — the routes are unauthenticated but
 * they still travel through Laravel's stateful middleware from a
 * browser session, and skipping the prime step 419s the request.
 */
export const authApi = {
  forgotPassword: async (identifier: string): Promise<{ message: string }> => {
    await primeCsrfCookie();
    const { data } = await apiClient.post('/auth/forgot-password', { identifier });
    return data;
  },

  resetPassword: async (payload: {
    identifier: string;
    code: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> => {
    await primeCsrfCookie();
    const { data } = await apiClient.post('/auth/reset-password', payload);
    return data;
  },
};
