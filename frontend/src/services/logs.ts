import api from '@/lib/api';
import { Log } from '@/types';

export const logService = {
  async getAll(params?: { server_id?: number; level?: string; limit?: number }): Promise<Log[]> {
    const response = await api.get<{ logs: Log[] }>('/logs', { params });
    return response.data.logs;
  },

  async getById(id: number): Promise<Log> {
    const response = await api.get<{ log: Log }>(`/logs/${id}`);
    return response.data.log;
  },
};