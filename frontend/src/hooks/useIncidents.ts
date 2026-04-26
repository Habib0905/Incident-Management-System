import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { incidentService } from '@/services';
import { Incident, IncidentFilters, LogsPagination } from '@/types';

export function useIncidents(filters?: IncidentFilters) {
  return useQuery({
    queryKey: ['incidents', filters],
    queryFn: () => incidentService.getAll(filters),
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useIncident(id: number) {
  return useQuery({
    queryKey: ['incident', id],
    queryFn: () => incidentService.getById(id),
    enabled: !!id,
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useMyIncidents() {
  return useQuery({
    queryKey: ['my-incidents'],
    queryFn: () => incidentService.getMyIncidents(),
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useUnreadCount() {
  return useQuery({
    queryKey: ['unread-count'],
    queryFn: () => incidentService.getUnreadCount(),
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useTimeline(incidentId: number) {
  return useQuery({
    queryKey: ['timeline', incidentId],
    queryFn: () => incidentService.getTimeline(incidentId),
    enabled: !!incidentId,
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useUpdateIncident() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { status?: string; summary?: string } }) =>
      incidentService.update(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['incident', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['timeline', variables.id] });
    },
  });
}

export function useAssignIncident() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, assignedTo }: { id: number; assignedTo: number }) =>
      incidentService.assign(id, assignedTo),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['incident', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['timeline', variables.id] });
    },
  });
}

export function useAddNote() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, note }: { id: number; note: string }) => incidentService.addNote(id, note),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['timeline', variables.id] });
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
    onSuccess: (_, id) => {
      queryClient.setQueryData(['incident', id], (old: any) => {
        if (!old) return old;
        return {
          ...old,
          incident: { ...old.incident, is_viewed: true },
        };
      });
      queryClient.invalidateQueries({ queryKey: ['unread-count'] });
      queryClient.invalidateQueries({ queryKey: ['incidents'] });
      queryClient.invalidateQueries({ queryKey: ['my-incidents'] });
    },
  });
}
