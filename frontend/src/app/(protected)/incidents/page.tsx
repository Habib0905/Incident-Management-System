'use client';

import { useState, useEffect } from 'react';
import { useIncidents } from '@/hooks';
import { useAuth } from '@/store/auth';
import { useSearchParams, useRouter } from 'next/navigation';
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
import { Search, Filter, X, ChevronLeft, ChevronRight } from 'lucide-react';
import Link from 'next/link';
import { formatRelativeTime } from '@/lib/utils';

export default function IncidentsPage() {
  const { user, isAdmin } = useAuth();
  const searchParams = useSearchParams();
  const router = useRouter();
  const initialStatus = searchParams.get('status') || '';
  const initialSeverity = searchParams.get('severity') || '';
  const initialFilter = searchParams.get('filter') || '';
  const initialPage = parseInt(searchParams.get('page') || '1', 10);
  
  const [page, setPage] = useState(initialPage);
  const perPage = 20;

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState(initialStatus);
  const [severityFilter, setSeverityFilter] = useState(initialSeverity);
  const [typeFilter, setTypeFilter] = useState('');
  const [assignedFilter, setAssignedFilter] = useState(initialFilter);
  const [showFilters, setShowFilters] = useState(false);

  const { data, isLoading, isError } = useIncidents({
    status: statusFilter || undefined,
    severity: severityFilter || undefined,
    type: typeFilter || undefined,
    assigned_to: assignedFilter === 'assigned_to_me' ? user?.id : undefined,
    exclude_resolved: assignedFilter === 'assigned_to_me' ? true : undefined,
    unassigned: assignedFilter === 'unassigned' ? true : undefined,
    search: search || undefined,
    page,
    per_page: perPage,
  });

  const incidents = data?.incidents || [];
  const pagination = data?.pagination;

  const activeFilters = [statusFilter, severityFilter, typeFilter, assignedFilter, search].filter(Boolean).length;

  useEffect(() => {
    setPage(1);
  }, [statusFilter, severityFilter, typeFilter, assignedFilter, search]);

  function clearFilters() {
    setStatusFilter('');
    setSeverityFilter('');
    setTypeFilter('');
    setAssignedFilter('');
    setSearch('');
    setPage(1);
    router.push('/incidents');
  }

  function goToPage(newPage: number) {
    setPage(newPage);
    const params = new URLSearchParams(searchParams.toString());
    params.set('page', newPage.toString());
    router.push(`/incidents?${params.toString()}`);
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Incidents</h1>
          <p className="text-gray-500">
            {pagination ? `${pagination.total} total` : `${incidents.length} incident${incidents.length !== 1 ? 's' : ''}`}
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
                  { value: 'container', label: 'Container' },
                  { value: 'cloud', label: 'Cloud' },
                  { value: 'nginx', label: 'Nginx' },
                  { value: 'apache', label: 'Apache' },
                  { value: 'api', label: 'API' },
                  { value: 'queue', label: 'Queue' },
                  { value: 'file', label: 'File System' },
                  { value: 'email', label: 'Email' },
                  { value: 'cache', label: 'Cache' },
                  { value: 'general', label: 'General' },
                ]}
              />
              {!isAdmin && (
                <Select
                  value={assignedFilter}
                  onChange={(e) => setAssignedFilter(e.target.value)}
                  options={[
                    { value: '', label: 'All Incidents' },
                    { value: 'assigned_to_me', label: 'Assigned to Me' },
                  ]}
                />
              )}
              {isAdmin && (
                <Select
                  value={assignedFilter}
                  onChange={(e) => setAssignedFilter(e.target.value)}
                  options={[
                    { value: '', label: 'All Incidents' },
                    { value: 'unassigned', label: 'Unassigned' },
                  ]}
                />
              )}
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
          ) : incidents.length === 0 ? (
            <EmptyState message={data?.incidents ? 'No incidents match your filters' : 'No incidents yet'} />
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
                {incidents.map((incident) => (
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

      {pagination && pagination.total_pages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-500">
            Showing {(pagination.current_page - 1) * pagination.per_page + 1}–{Math.min(pagination.current_page * pagination.per_page, pagination.total)} of {pagination.total}
          </p>
          <div className="flex items-center gap-2">
            <button
              onClick={() => goToPage(page - 1)}
              disabled={page <= 1}
              className="flex items-center gap-1 px-3 py-1.5 border rounded-md text-sm text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <ChevronLeft className="h-4 w-4" />
              Previous
            </button>
            <div className="flex items-center gap-1">
              {Array.from({ length: Math.min(5, pagination.total_pages) }, (_, i) => {
                let pageNum: number;
                if (pagination.total_pages <= 5) {
                  pageNum = i + 1;
                } else if (page <= 3) {
                  pageNum = i + 1;
                } else if (page >= pagination.total_pages - 2) {
                  pageNum = pagination.total_pages - 4 + i;
                } else {
                  pageNum = page - 2 + i;
                }
                return (
                  <button
                    key={pageNum}
                    onClick={() => goToPage(pageNum)}
                    className={`w-8 h-8 rounded-md text-sm text-gray-900 ${
                      page === pageNum
                        ? 'bg-blue-600 text-white'
                        : 'border border-gray-300 hover:bg-gray-50'
                    }`}
                  >
                    {pageNum}
                  </button>
                );
              })}
            </div>
            <button
              onClick={() => goToPage(page + 1)}
              disabled={page >= pagination.total_pages}
              className="flex items-center gap-1 px-3 py-1.5 border rounded-md text-sm text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              Next
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}