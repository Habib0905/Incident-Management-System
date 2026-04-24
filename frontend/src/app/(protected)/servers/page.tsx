'use client';

import { useState } from 'react';
import {
  useServers,
  useCreateServer,
  useRegenerateKey,
  useRevokeKey,
  useActivateKey,
} from '@/hooks';
import { useAuth } from '@/store/auth';
import { AdminRoute } from '@/components/Providers';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Button,
  Input,
  Select,
  EmptyState,
  LoadingSpinner,
  Alert,
  ActiveBadge,
  EnvironmentBadge,
} from '@/components/ui';
import { Server, Plus, Copy, Key, Trash2, Power, RefreshCw, Loader2 } from 'lucide-react';
import { formatDate, maskApiKey } from '@/lib/utils';

export default function ServersPage() {
  const { isAdmin } = useAuth();
  const { data: servers, isLoading, isError } = useServers();
  const createServer = useCreateServer();
  const regenerateKey = useRegenerateKey();
  const revokeKey = useRevokeKey();
  const activateKey = useActivateKey();

  const [showCreateForm, setShowCreateForm] = useState(false);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [environment, setEnvironment] = useState('development');
  const [showKey, setShowKey] = useState<number | null>(null);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [newServerKey, setNewServerKey] = useState<string | null>(null);

  async function handleCreate() {
    if (!name) return;
    const result = await createServer.mutateAsync({ name, description, environment });
    setShowCreateForm(false);
    setName('');
    setDescription('');
    setEnvironment('development');
    setNewServerKey(result.api_key);
  }

  async function handleRegenerateKey(id: number) {
    const result = await regenerateKey.mutateAsync(id);
    setNewServerKey(result.api_key);
  }

  function handleCopy(key: string) {
    navigator.clipboard.writeText(key);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  }

  if (!isAdmin) {
    return (
      <div className="p-8">
        <Alert variant="error" title="Access Denied">
          Only administrators can access server management.
        </Alert>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Servers</h1>
          <p className="text-gray-500">Manage server registrations and API keys</p>
        </div>
        <Button onClick={() => setShowCreateForm(!showCreateForm)}>
          <Plus className="h-4 w-4" />
          Add Server
        </Button>
      </div>

      {showCreateForm && (
        <Card>
          <CardHeader>
            <CardTitle>Register New Server</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Input
              label="Server Name"
              placeholder="e.g., Production Web Server"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
            <Input
              label="Description (optional)"
              placeholder="Brief description of this server"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
            <Select
              label="Environment"
              value={environment}
              onChange={(e) => setEnvironment(e.target.value)}
              options={[
                { value: 'production', label: 'Production' },
                { value: 'staging', label: 'Staging' },
                { value: 'development', label: 'Development' },
              ]}
            />
            <div className="flex gap-2">
              <Button onClick={handleCreate} loading={createServer.isPending}>
                Create Server
              </Button>
              <Button variant="ghost" onClick={() => setShowCreateForm(false)}>
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {newServerKey && (
        <Alert variant="success" title="Server Created!" className="border-green-200 bg-green-50">
          <p className="mb-2">Your new server API key:</p>
          <code className="block bg-white p-2 rounded border border-green-200 text-sm font-mono break-all">
            {newServerKey}
          </code>
          <p className="mt-2 text-xs text-green-700">
            Make sure to copy this key now. You wont be able to see it again!
          </p>
          <Button variant="secondary" size="sm" className="mt-2" onClick={() => setNewServerKey(null)}>
            Dismiss
          </Button>
        </Alert>
      )}

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <LoadingSpinner />
          ) : isError ? (
            <div className="p-8 text-center text-red-600">Failed to load servers</div>
          ) : !servers || servers.length === 0 ? (
            <EmptyState message="No servers registered yet. Add your first server above." />
          ) : (
            <div className="divide-y divide-gray-200">
              {servers.map((server) => (
                <div key={server.id} className="p-4">
                  <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                      <div className="p-2 bg-gray-100 rounded-lg">
                        <Server className="h-5 w-5 text-gray-600" />
                      </div>
                      <div>
                        <div className="flex items-center gap-2 mb-1">
                          <h3 className="font-medium text-gray-900">{server.name}</h3>
                          <ActiveBadge isActive={server.is_active} />
                          <EnvironmentBadge environment={server.environment} />
                        </div>
                        {server.description && (
                          <p className="text-sm text-gray-500 mb-2">{server.description}</p>
                        )}
                        <div className="flex items-center gap-4 text-sm text-gray-500">
                          <span>Created: {formatDate(server.created_at)}</span>
                          <div className="flex items-center gap-2">
                            <span>API Key:</span>
                            {showKey === server.id ? (
                              <code className="text-xs bg-gray-100 px-2 py-1 rounded font-mono">
                                {server.api_key}
                              </code>
                            ) : (
                              <code className="text-xs bg-gray-100 px-2 py-1 rounded font-mono">
                                {maskApiKey(server.api_key)}
                              </code>
                            )}
                            <button
                              onClick={() => setShowKey(showKey === server.id ? null : server.id)}
                              className="text-blue-600 hover:underline text-xs"
                            >
                              {showKey === server.id ? 'Hide' : 'Show'}
                            </button>
                            <button
                              onClick={() => handleCopy(server.api_key)}
                              className="text-blue-600 hover:underline text-xs flex items-center gap-1"
                            >
                              <Copy className="h-3 w-3" />
                              {copiedKey === server.api_key ? 'Copied!' : 'Copy'}
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleRegenerateKey(server.id)}
                        disabled={regenerateKey.isPending}
                        className="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-md"
                        title="Regenerate API Key"
                      >
                        <RefreshCw className="h-4 w-4" />
                      </button>
                      {server.is_active ? (
                        <button
                          onClick={() => revokeKey.mutate(server.id)}
                          disabled={revokeKey.isPending}
                          className="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-md"
                          title="Revoke Key"
                        >
                          <Power className="h-4 w-4" />
                        </button>
                      ) : (
                        <button
                          onClick={() => activateKey.mutate(server.id)}
                          disabled={activateKey.isPending}
                          className="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-md"
                          title="Activate Key"
                        >
                          <Power className="h-4 w-4" />
                        </button>
                      )}
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