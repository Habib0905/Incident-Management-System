import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { userService } from '@/services';

export function useUsers() {
  return useQuery({
    queryKey: ['users'],
    queryFn: () => userService.getAll(),
    retry: 1,
    retryDelay: 500,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    staleTime: 300000,
    gcTime: 600000,
  });
}

export function useCreateUser() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: { name: string; email: string; password: string; role?: string }) =>
      userService.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });
}

export function useDeleteUser() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => userService.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });
}

export function useUpdateUser() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { name?: string; email?: string; password?: string; role?: string } }) =>
      userService.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });
}
