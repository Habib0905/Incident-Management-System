import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { incidentService } from '@/services';
import { IncidentFilters } from '@/types';

export function useIncidents(filters?: IncidentFilters) {
  return useQuery({
    queryKey: ['incidents', filters],
    queryFn: () => incidentService.getAll(filters),
  });
}

export function useIncident(id: number) {
  return useQuery({
    queryKey: ['incident', id],
    queryFn: () => incidentService.getById(id),
    enabled: !!id,
  });
}

export function useMyIncidents() {
  return useQuery({
    queryKey: ['my-incidents'],
    queryFn: () => incidentService.getMyIncidents(),
  });
}

export function useUnreadCount() {
  return useQuery({
    queryKey: ['unread-count'],
    queryFn: () => incidentService.getUnreadCount(),
    refetchInterval: 30000,
  });
}

export function useTimeline(incidentId: number) {
  return useQuery({
    queryKey: ['timeline', incidentId],
    queryFn: () => incidentService.getTimeline(incidentId),
    enabled: !!incidentId,
  });
}

export function useUpdateIncident() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { status?: string; summary?: string } }) =>
      incidentService.update(id, data),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['incident', data.id] });
      queryClient.invalidateQueries({ queryKey: ['incidents'] });
    },
  });
}

export function useAssignIncident() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, assignedTo }: { id: number; assignedTo: number }) =>
      incidentService.assign(id, assignedTo),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['incident', data.id] });
      queryClient.invalidateQueries({ queryKey: ['incidents'] });
    },
  });
}

export function useAddNote() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, note }: { id: number; note: string }) => incidentService.addNote(id, note),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['timeline', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['incident', variables.id] });
    },
  });
}

export function useGenerateSummary() {
  return useMutation({
    mutationFn: (id: number) => incidentService.generateSummary(id),
  });
}

export function useMarkAsViewed() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => incidentService.markAsViewed(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['unread-count'] });
    },
  });
}