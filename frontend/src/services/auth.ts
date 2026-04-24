import api from '@/lib/api';
import { AuthResponse, User } from '@/types';

export const authService = {
  async login(email: string, password: string): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/login', { email, password });
    return response.data;
  },

  async logout(): Promise<void> {
    await api.post('/logout');
  },

  async getMe(): Promise<User> {
    const response = await api.get<User>('/me');
    return response.data;
  },
};