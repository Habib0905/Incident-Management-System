import api from '@/lib/api';
import { User } from '@/types';

export const userService = {
  async getAll(): Promise<User[]> {
    const response = await api.get<{ users: User[] }>('/admin/users');
    return response.data.users;
  },

  async create(data: { name: string; email: string; password: string; role?: string }): Promise<User> {
    const response = await api.post<{ user: User }>('/admin/users', data);
    return response.data.user;
  },

  async update(id: number, data: { name?: string; email?: string; password?: string; role?: string }): Promise<User> {
    const response = await api.patch<{ user: User }>(`/admin/users/${id}`, data);
    return response.data.user;
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/admin/users/${id}`);
  },
};