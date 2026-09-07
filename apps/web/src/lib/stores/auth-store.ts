import { create } from 'zustand';
import { persist } from 'zustand/middleware';

type User = {
  id: number;
  employee_number: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
};

type AuthState = {
  user: User | null;
  token: string | null;
  setAuth: (user: User, token: string) => void;
  logout: () => void;
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      setAuth: (user, token) => {
        localStorage.setItem('taqat_token', token);
        set({ user, token });
      },
      logout: () => {
        localStorage.removeItem('taqat_token');
        set({ user: null, token: null });
      },
    }),
    { name: 'taqat-auth' }
  )
);
