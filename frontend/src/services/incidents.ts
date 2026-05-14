import api from '@/lib/api';
import { Incident, IncidentFilters, ActivityLog, LogsPagination, IncidentStats } from '@/types';

export const incidentService = {
  async getAll(filters?: IncidentFilters): Promise<{ incidents: Incident[]; pagination: LogsPagination }> {
    const response = await api.get<{ incidents: Incident[]; pagination: LogsPagination }>('/incidents', { params: filters });
    return {
      incidents: response.data.incidents,
      pagination: response.data.pagination,
    };
  },

  async getById(id: number): Promise<{ incident: Incident; logs: Incident['logs']; logs_pagination?: LogsPagination }> {
    const response = await api.get(`/incidents/${id}`);
    return {
      incident: { ...response.data.incident, similar_incidents: response.data.similar_incidents || [] },
      logs: response.data.logs || [],
      logs_pagination: response.data.logs_pagination,
    };
  },

  async update(id: number, data: { status?: string; summary?: string }): Promise<Incident> {
    const response = await api.patch<{ incident: Incident }>(`/incidents/${id}`, data);
    return response.data.incident;
  },

  async assign(id: number, assignedTo: number): Promise<Incident> {
    const response = await api.post<{ incident: Incident }>(`/incidents/${id}/assign`, {
      assigned_to: assignedTo,
    });
    return response.data.incident;
  },

  async addNote(id: number, note: string): Promise<void> {
    await api.post(`/incidents/${id}/notes`, { note });
  },

  async getTimeline(id: number): Promise<ActivityLog[]> {
    const response = await api.get<{ timeline: ActivityLog[] }>(`/incidents/${id}/timeline`);
    return response.data.timeline;
  },

  async generateSummary(id: number): Promise<{ summary: string; saved: boolean }> {
    const response = await api.post<{ summary: string; saved: boolean; incident_id: number }>(
      `/incidents/${id}/generate-summary`
    );
    return { summary: response.data.summary, saved: response.data.saved };
  },

  async markAsViewed(id: number): Promise<{ message: string; unread_count: number }> {
    const response = await api.post(`/incidents/${id}/view`);
    return response.data;
  },

  async getMyIncidents(): Promise<Incident[]> {
    const response = await api.get<{ incidents: Incident[] }>('/me/incidents');
    return response.data.incidents;
  },

  async getUnreadCount(): Promise<number> {
    const response = await api.get<{ unread_count: number }>('/me/unread-count');
    return response.data.unread_count;
  },

  async getStats(): Promise<IncidentStats> {
    const response = await api.get<IncidentStats>('/incidents/stats');
    return response.data;
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/incidents/${id}`);
  },
};