/**
 * Central Axios instance for the TAQAT API.
 *
 * - Base URL comes from `EXPO_PUBLIC_API_URL` (build-time), falling back to
 *   the production host so a fresh checkout works out of the box.
 * - The auth token is read on every request via the token accessor set by
 *   the auth store — that keeps this module free of a circular import with
 *   the Zustand store while still injecting the freshest value.
 * - Bearer tokens live in `expo-secure-store` (see `auth-store.ts`), NEVER
 *   `AsyncStorage`, because SecureStore is backed by iOS Keychain / Android
 *   Keystore and AsyncStorage is plaintext on disk.
 * - 401s trigger a single logout hook so any screen can react in one place.
 */

import axios, {
  AxiosError,
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from 'axios';

const DEFAULT_BASE_URL = 'https://attendees.taqatgaza.com/api';

export const API_BASE_URL: string =
  process.env.EXPO_PUBLIC_API_URL?.replace(/\/$/, '') ?? DEFAULT_BASE_URL;

type TokenGetter = () => string | null;
type UnauthorizedHandler = () => void | Promise<void>;

let tokenGetter: TokenGetter = () => null;
let onUnauthorized: UnauthorizedHandler = () => {};

/** Called by the auth store after hydration so the interceptor sees the token. */
export function setAuthTokenGetter(getter: TokenGetter): void {
  tokenGetter = getter;
}

/** Called by the auth store; fired on every 401 from the API. */
export function setUnauthorizedHandler(handler: UnauthorizedHandler): void {
  onUnauthorized = handler;
}

export const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = tokenGetter();
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`);
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (error.response?.status === 401) {
      await Promise.resolve(onUnauthorized());
    }
    return Promise.reject(error);
  },
);

/**
 * Narrow the shape of Laravel's validation-error payload so screens can
 * surface `errors.employee_number[0]` without reaching into `any`.
 */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

export function extractApiMessage(err: unknown, fallback = 'حدث خطأ غير متوقع'): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as Partial<ApiValidationError> | undefined;
    if (data?.errors) {
      const first = Object.values(data.errors)[0];
      if (first && first[0]) return first[0];
    }
    if (data?.message) return data.message;
    if (err.message) return err.message;
  }
  return fallback;
}
