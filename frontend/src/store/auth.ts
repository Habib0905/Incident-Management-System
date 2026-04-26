import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { User } from '@/types';

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  setAuth: (user: User, token: string) => void;
  updateUser: (user: User) => void;
  logout: () => void;
  setLoading: (loading: boolean) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: true,

      setAuth: (user, token) => {
        if (typeof window !== 'undefined') {
          localStorage.setItem('token', token);
          localStorage.setItem('user', JSON.stringify(user));
          window.dispatchEvent(new CustomEvent('auth:user-changed', { detail: { userId: user.id } }));
        }
        set({ user, token, isAuthenticated: true, isLoading: false });
      },

      updateUser: (user) => {
        if (typeof window !== 'undefined') {
          localStorage.setItem('user', JSON.stringify(user));
        }
        set({ user });
      },

      logout: () => {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          window.dispatchEvent(new CustomEvent('auth:user-changed', { detail: { userId: null } }));
        }
        set({ user: null, token: null, isAuthenticated: false, isLoading: false });
      },

      setLoading: (loading) => set({ isLoading: loading }),
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        isAuthenticated: state.isAuthenticated,
      }),
      onRehydrateStorage: () => (state) => {
        state?.setLoading(false);
      },
    }
  )
);

export function useAuth() {
  const { user, token, isAuthenticated, isLoading, setAuth, logout, setLoading } = useAuthStore();

  const isAdmin = user?.role === 'admin';
  const isEngineer = user?.role === 'engineer';

  return {
    user,
    token,
    isAuthenticated,
    isLoading,
    isAdmin,
    isEngineer,
    setAuth,
    logout,
    setLoading,
  };
}