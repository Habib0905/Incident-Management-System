import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { serverService } from '@/services';

export function useServers() {
  return useQuery({
    queryKey: ['servers'],
    queryFn: () => serverService.getAll(),
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useCreateServer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: { name: string; description?: string; environment: string }) =>
      serverService.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['servers'] });
    },
  });
}

export function useUpdateServer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { name?: string; description?: string } }) =>
      serverService.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['servers'] });
    },
  });
}

export function useRegenerateKey() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => serverService.regenerateKey(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['servers'] });
    },
  });
}

export function useRevokeKey() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => serverService.revokeKey(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['servers'] });
    },
  });
}

export function useActivateKey() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => serverService.activateKey(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['servers'] });
    },
  });
}

export function useDeleteServer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => serverService.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['servers'] });
    },
  });
}
