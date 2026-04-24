import api from '@/lib/api';
import { Server } from '@/types';

export const serverService = {
  async getAll(): Promise<Server[]> {
    const response = await api.get<{ servers: Server[] }>('/admin/servers');
    return response.data.servers;
  },

  async getById(id: number): Promise<Server> {
    const response = await api.get<{ server: Server }>(`/admin/servers/${id}`);
    return response.data.server;
  },

  async create(data: { name: string; description?: string; environment: string }): Promise<Server> {
    const response = await api.post<{ server: Server }>('/admin/servers', data);
    return response.data.server;
  },

  async update(id: number, data: { name?: string; description?: string }): Promise<Server> {
    const response = await api.patch<{ server: Server }>(`/admin/servers/${id}`, data);
    return response.data.server;
  },

  async regenerateKey(id: number): Promise<{ server: Server; api_key: string }> {
    const response = await api.post<{ server: Server; api_key: string }>(`/admin/servers/${id}/regenerate-key`);
    return response.data;
  },

  async revokeKey(id: number): Promise<Server> {
    const response = await api.post<{ server: Server }>(`/admin/servers/${id}/revoke-key`);
    return response.data.server;
  },

  async activateKey(id: number): Promise<Server> {
    const response = await api.post<{ server: Server }>(`/admin/servers/${id}/activate-key`);
    return response.data.server;
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/admin/servers/${id}`);
  },
};