'use client';

import { useState, useMemo, useEffect } from 'react';
import { useIncidents } from '@/hooks';
import { useAuth } from '@/store/auth';
import { useSearchParams } from 'next/navigation';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Input,
  Select,
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  SeverityBadge,
  StatusBadge,
  UnreadBadge,
  EmptyState,
  LoadingSpinner,
} from '@/components/ui';
import { Search, Filter, X } from 'lucide-react';
import Link from 'next/link';
import { formatRelativeTime } from '@/lib/utils';

export default function IncidentsPage() {
  const { user } = useAuth();
  const searchParams = useSearchParams();
  const initialStatus = searchParams.get('status') || '';
  const initialSeverity = searchParams.get('severity') || '';
  
  const { data: incidents, isLoading, isError } = useIncidents({
    status: initialStatus || undefined,
    severity: initialSeverity || undefined,
  });

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState(initialStatus);
  const [severityFilter, setSeverityFilter] = useState(initialSeverity);
  const [typeFilter, setTypeFilter] = useState('');
  const [showFilters, setShowFilters] = useState(false);

  const filteredIncidents = useMemo(() => {
    if (!incidents) return [];

    return incidents.filter((incident) => {
      if (search) {
        const searchLower = search.toLowerCase();
        if (
          !incident.title.toLowerCase().includes(searchLower) &&
          !(incident.summary?.toLowerCase().includes(searchLower))
        ) {
          return false;
        }
      }
      if (statusFilter && incident.status !== statusFilter) return false;
      if (severityFilter && incident.severity !== severityFilter) return false;
      if (typeFilter && incident.type !== typeFilter) return false;
      return true;
    });
  }, [incidents, search, statusFilter, severityFilter, typeFilter]);

  const activeFilters = [statusFilter, severityFilter, typeFilter].filter(Boolean).length;

  function clearFilters() {
    setStatusFilter('');
    setSeverityFilter('');
    setTypeFilter('');
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Incidents</h1>
          <p className="text-gray-500">
            {filteredIncidents.length} incident{filteredIncidents.length !== 1 ? 's' : ''}
          </p>
        </div>
      </div>

      <Card>
        <CardContent className="p-4 space-y-4">
          <div className="flex items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search by title or summary..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-gray-900 placeholder:text-gray-400"
              />
            </div>
            <button
              onClick={() => setShowFilters(!showFilters)}
              className={`flex items-center gap-2 px-4 py-2 border rounded-md transition-colors ${
                showFilters ? 'bg-blue-50 border-blue-300 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'
              }`}
            >
              <Filter className="h-4 w-4" />
              Filters
              {activeFilters > 0 && (
                <span className="bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-full">{activeFilters}</span>
              )}
            </button>
          </div>

          {showFilters && (
            <div className="flex items-center gap-4 pt-4 border-t">
              <Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                options={[
                  { value: '', label: 'All Status' },
                  { value: 'open', label: 'Open' },
                  { value: 'investigating', label: 'Investigating' },
                  { value: 'resolved', label: 'Resolved' },
                ]}
              />
              <Select
                value={severityFilter}
                onChange={(e) => setSeverityFilter(e.target.value)}
                options={[
                  { value: '', label: 'All Severity' },
                  { value: 'critical', label: 'Critical' },
                  { value: 'high', label: 'High' },
                  { value: 'medium', label: 'Medium' },
                  { value: 'low', label: 'Low' },
                ]}
              />
              <Select
                value={typeFilter}
                onChange={(e) => setTypeFilter(e.target.value)}
                options={[
                  { value: '', label: 'All Types' },
                  { value: 'database', label: 'Database' },
                  { value: 'auth', label: 'Auth' },
                  { value: 'network', label: 'Network' },
                  { value: 'system', label: 'System' },
                  { value: 'general', label: 'General' },
                ]}
              />
              {activeFilters > 0 && (
                <button onClick={clearFilters} className="text-sm text-red-600 hover:underline flex items-center gap-1">
                  <X className="h-4 w-4" />
                  Clear
                </button>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <LoadingSpinner />
          ) : isError ? (
            <div className="p-8 text-center text-red-600">Failed to load incidents</div>
          ) : filteredIncidents.length === 0 ? (
            <EmptyState message={incidents?.length === 0 ? 'No incidents yet' : 'No incidents match your filters'} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-8"></TableHead>
                  <TableHead>Title</TableHead>
                  <TableHead>Severity</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Server</TableHead>
                  <TableHead>Assigned To</TableHead>
                  <TableHead>Created</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredIncidents.map((incident) => (
                  <TableRow key={incident.id}>
                    <TableCell>
                      <UnreadBadge show={!incident.is_viewed} />
                    </TableCell>
                    <TableCell>
                      <Link
                        href={`/incidents/${incident.id}`}
                        className="font-medium text-blue-600 hover:text-blue-800 hover:underline"
                      >
                        {incident.title}
                      </Link>
                    </TableCell>
                    <TableCell>
                      <SeverityBadge severity={incident.severity} />
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={incident.status} />
                    </TableCell>
                    <TableCell>
                      {incident.server?.name || <span className="text-gray-400">Unknown</span>}
                    </TableCell>
                    <TableCell>
                      {incident.assigned_user ? (
                        <span className="text-gray-900">{incident.assigned_user.name}</span>
                      ) : (
                        <span className="text-gray-400">Unassigned</span>
                      )}
                    </TableCell>
                    <TableCell className="text-gray-500">{formatRelativeTime(incident.created_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}