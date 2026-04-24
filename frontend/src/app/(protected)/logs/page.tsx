'use client';

import { useState } from 'react';
import { useLogs } from '@/hooks';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Select,
  LogLevelBadge,
  EmptyState,
  LoadingSpinner,
} from '@/components/ui';
import { FileText, RefreshCw } from 'lucide-react';
import { formatDate } from '@/lib/utils';

export default function LogsPage() {
  const [levelFilter, setLevelFilter] = useState('');
  const [serverFilter, setServerFilter] = useState('');
  const { data: logs, isLoading, isError, refetch, isFetching } = useLogs({
    level: levelFilter || undefined,
    server_id: serverFilter ? parseInt(serverFilter) : undefined,
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Logs</h1>
          <p className="text-gray-500">{logs?.length || 0} log entries</p>
        </div>
        <button
          onClick={() => refetch()}
          disabled={isFetching}
          className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md"
        >
          <RefreshCw className={`h-5 w-5 ${isFetching ? 'animate-spin' : ''}`} />
        </button>
      </div>

      <Card>
        <CardContent className="p-4">
          <div className="flex items-center gap-4">
            <Select
              value={levelFilter}
              onChange={(e) => setLevelFilter(e.target.value)}
              options={[
                { value: '', label: 'All Levels' },
                { value: 'error', label: 'Error' },
                { value: 'warn', label: 'Warning' },
                { value: 'info', label: 'Info' },
                { value: 'debug', label: 'Debug' },
              ]}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <LoadingSpinner />
          ) : isError ? (
            <div className="p-8 text-center text-red-600">Failed to load logs</div>
          ) : !logs || logs.length === 0 ? (
            <EmptyState message="No logs received yet. Start sending logs from your servers." />
          ) : (
            <div className="divide-y divide-gray-200 max-h-[600px] overflow-y-auto">
              {logs.map((log) => (
                <div key={log.id} className="p-4 hover:bg-gray-50">
                  <div className="flex items-start gap-4">
                    <div className="p-2 bg-gray-100 rounded-lg flex-shrink-0">
                      <FileText className="h-4 w-4 text-gray-500" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        {log.log_level && <LogLevelBadge level={log.log_level} />}
                        {log.source && (
                          <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                            {log.source}
                          </span>
                        )}
                        {log.server && (
                          <span className="text-xs text-gray-500">{log.server.name}</span>
                        )}
                      </div>
                      <p className="text-sm font-mono text-gray-700 break-all">{log.message}</p>
                      <p className="text-xs text-gray-400 mt-1">{formatDate(log.timestamp)}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}