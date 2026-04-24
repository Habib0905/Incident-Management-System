import { useQuery } from '@tanstack/react-query';
import { logService } from '@/services';

export function useLogs(params?: { server_id?: number; level?: string; limit?: number }) {
  return useQuery({
    queryKey: ['logs', params],
    queryFn: () => logService.getAll(params),
    refetchInterval: 10000,
  });
}