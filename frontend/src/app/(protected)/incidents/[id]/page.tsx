'use client';

import { use, useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useIncident, useTimeline, useUpdateIncident, useAssignIncident, useAddNote, useMarkAsViewed, useUsers, useGenerateSummary } from '@/hooks';
import { useAuth } from '@/store/auth';
import api from '@/lib/api';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Button,
  Select,
  Textarea,
  SeverityBadge,
  StatusBadge,
  TypeBadge,
  Alert,
} from '@/components/ui';
import { formatDate, formatRelativeTime } from '@/lib/utils';
import {
  ArrowLeft,
  User,
  Server,
  Clock,
  Loader2,
  Send,
  ChevronDown,
  Sparkles,
} from 'lucide-react';
import { Log, LogsPagination } from '@/types';

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function IncidentDetail({ params }: PageProps) {
  const { id } = use(params);
  const incidentId = parseInt(id);
  const { data, isLoading, error, refetch } = useIncident(incidentId);
  const { data: timeline } = useTimeline(incidentId);
  const { user, isAdmin } = useAuth();
  const { data: users } = useUsers();
  const markAsViewed = useMarkAsViewed();
  const updateIncident = useUpdateIncident();
  const assignIncident = useAssignIncident();
  const addNote = useAddNote();
  const generateSummary = useGenerateSummary();

  const [showStatusForm, setShowStatusForm] = useState(false);
  const [showAssignForm, setShowAssignForm] = useState(false);
  const [showNoteForm, setShowNoteForm] = useState(false);
  const [status, setStatus] = useState('');
  const [assignTo, setAssignTo] = useState('');
  const [note, setNote] = useState('');

  const [logs, setLogs] = useState<Log[]>([]);
  const [logsPagination, setLogsPagination] = useState<LogsPagination | null>(null);
  const [isLoadingMoreLogs, setIsLoadingMoreLogs] = useState(false);
  const hasMarkedAsViewed = useRef(false);

  const incident = data?.incident;
  const [summary, setSummary] = useState<string | null>(incident?.summary || null);
  const [summaryError, setSummaryError] = useState<string | null>(null);
  const hasMoreLogs = logsPagination ? logsPagination.current_page < logsPagination.total_pages : false;

  useEffect(() => {
    if (data?.logs) {
      setLogs(data.logs);
    }
    if (data?.logs_pagination) {
      setLogsPagination(data.logs_pagination);
    }
  }, [data]);

  useEffect(() => {
    if (incident && !incident.is_viewed && !hasMarkedAsViewed.current) {
      hasMarkedAsViewed.current = true;
      markAsViewed.mutate(incidentId);
    }
  }, [incident, incidentId, markAsViewed]);

  async function loadMoreLogs() {
    if (!logsPagination || isLoadingMoreLogs) return;
    
    setIsLoadingMoreLogs(true);
    try {
      const nextPage = logsPagination.current_page + 1;
      const response = await api.get(`/incidents/${id}`, {
        params: { logs_page: nextPage, logs_per_page: logsPagination.per_page },
      });
      
      const newLogs = response.data.logs || [];
      setLogs((prev) => [...prev, ...newLogs]);
      setLogsPagination(response.data.logs_pagination);
    } catch (err) {
      console.error('Failed to load more logs:', err);
    } finally {
      setIsLoadingMoreLogs(false);
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center p-12">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (error || !incident) {
    return (
      <Alert variant="error" title="Error">
        Unable to load incident.
      </Alert>
    );
  }

  const canEdit = isAdmin || incident.assigned_to === user?.id;
  const canAssign = isAdmin;

  async function handleStatusUpdate() {
    if (!status) return;
    await updateIncident.mutateAsync({ id: incidentId, data: { status } });
    setShowStatusForm(false);
    setStatus('');
  }

  async function handleAssign() {
    if (!assignTo) return;
    await assignIncident.mutateAsync({ id: incidentId, assignedTo: parseInt(assignTo) });
    setShowAssignForm(false);
    setAssignTo('');
  }

  async function handleAddNote() {
    if (!note.trim()) return;
    await addNote.mutateAsync({ id: incidentId, note: note.trim() });
    setShowNoteForm(false);
    setNote('');
  }

  async function handleGenerateSummary() {
    setSummaryError(null);
    try {
      const result = await generateSummary.mutateAsync(incidentId);
      setSummary(result.summary);
    } catch {
      setSummaryError('Failed to generate summary. Make sure Ollama is running with llama3.2 model.');
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/incidents" className="p-2 hover:bg-gray-100 rounded-md">
          <ArrowLeft className="h-5 w-5" />
        </Link>
        <div className="flex-1">
          <h1 className="text-2xl font-bold text-gray-900">{incident.title}</h1>
          <p className="text-gray-500">Incident #{incident.id}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Details</CardTitle>
                <div className="flex items-center gap-2">
                  <SeverityBadge severity={incident.severity} />
                  <StatusBadge status={incident.status} />
                  <TypeBadge type={incident.type} />
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div className="flex items-center gap-2 text-gray-500">
                  <Server className="h-4 w-4" />
                  <span>Server: {incident.server?.name || 'Unknown'}</span>
                </div>
                <div className="flex items-center gap-2 text-gray-500">
                  <User className="h-4 w-4" />
                  <span>Assigned: {incident.assigned_user?.name || 'Unassigned'}</span>
                </div>
                <div className="flex items-center gap-2 text-gray-500">
                  <Clock className="h-4 w-4" />
                  <span>Created: {formatDate(incident.created_at)}</span>
                </div>
                <div className="flex items-center gap-2 text-gray-500">
                  <Clock className="h-4 w-4" />
                  <span>Updated: {formatDate(incident.updated_at)}</span>
                </div>
              </div>

              <div className="flex flex-wrap gap-2 pt-4 border-t">
                {canEdit && (
                  <Button
                    onClick={() => { 
                      setShowAssignForm(false);
                      setShowNoteForm(false);
                      setShowStatusForm(!showStatusForm); 
                      setStatus(incident.status); 
                    }}
                    variant="secondary"
                    size="sm"
                  >
                    Change Status
                  </Button>
                )}
                {canAssign && (
                  <Button
                    onClick={() => { 
                      setShowStatusForm(false);
                      setShowNoteForm(false);
                      setShowAssignForm(!showAssignForm);
                    }}
                    variant="secondary"
                    size="sm"
                  >
                    {incident.assigned_to ? 'Reassign' : 'Assign'}
                  </Button>
                )}
                {canEdit && (
                  <Button
                    onClick={() => { 
                      setShowStatusForm(false);
                      setShowAssignForm(false);
                      setShowNoteForm(!showNoteForm);
                    }}
                    variant="secondary"
                    size="sm"
                  >
                    Add Note
                  </Button>
                )}
                <Button
                  onClick={handleGenerateSummary}
                  disabled={generateSummary.isPending}
                  variant="secondary"
                  size="sm"
                >
                  <Sparkles className="h-4 w-4" />
                  AI Summarize
                </Button>
              </div>

              {showStatusForm && (
                <div className="mt-4 p-4 bg-gray-50 rounded-lg space-y-3">
                  <Select
                    label="Status"
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    options={[
                      { value: 'open', label: 'Open' },
                      { value: 'investigating', label: 'Investigating' },
                      { value: 'resolved', label: 'Resolved' },
                    ]}
                  />
                  <div className="flex gap-2">
                    <Button onClick={handleStatusUpdate} loading={updateIncident.isPending}>
                      Update
                    </Button>
                    <Button variant="ghost" onClick={() => setShowStatusForm(false)}>
                      Cancel
                    </Button>
                  </div>
                </div>
              )}

              {showAssignForm && (
                <div className="mt-4 p-4 bg-gray-50 rounded-lg space-y-3">
                  <Select
                    label="Assign to Engineer"
                    value={assignTo}
                    onChange={(e) => setAssignTo(e.target.value)}
                    options={[
                      { value: '', label: 'Select an engineer...' },
                      ...(users?.filter(u => u.role === 'engineer').map(u => ({ value: String(u.id), label: u.name })) || []),
                    ]}
                  />
                  <div className="flex gap-2">
                    <Button onClick={handleAssign} loading={assignIncident.isPending} disabled={!assignTo}>
                      Assign
                    </Button>
                    <Button variant="ghost" onClick={() => setShowAssignForm(false)}>
                      Cancel
                    </Button>
                  </div>
                </div>
              )}

              {showNoteForm && (
                <div className="mt-4 p-4 bg-gray-50 rounded-lg space-y-3">
                  <Textarea
                    placeholder="Enter your note..."
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    rows={3}
                  />
                  <div className="flex gap-2">
                    <Button onClick={handleAddNote} loading={addNote.isPending}>
                      <Send className="h-4 w-4" />
                      Add Note
                    </Button>
                    <Button variant="ghost" onClick={() => setShowNoteForm(false)}>
                      Cancel
                    </Button>
                  </div>
                </div>
              )}

              {(summary || summaryError || generateSummary.isPending) && (
                <div className="mt-4 p-4 bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg border border-purple-200">
                  <div className="flex items-center gap-2 mb-3">
                    <Sparkles className="h-4 w-4 text-purple-600" />
                    <h3 className="font-semibold text-purple-900">AI Summarization</h3>
                    {generateSummary.isPending && (
                      <Loader2 className="h-4 w-4 text-purple-600 animate-spin ml-auto" />
                    )}
                  </div>
                  {generateSummary.isPending && (
                    <p className="text-sm text-purple-600">Analyzing logs and generating summary...</p>
                  )}
                  {summaryError && (
                    <p className="text-sm text-red-600">{summaryError}</p>
                  )}
                  {summary && !generateSummary.isPending && (
                    <div className="text-sm text-gray-700 whitespace-pre-wrap">{summary}</div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>
                Related Logs ({logsPagination?.total || 0})
              </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              {logs.length === 0 ? (
                <div className="p-8 text-center text-gray-500">No logs attached</div>
              ) : (
                <>
                  <div className="divide-y divide-gray-200 max-h-[500px] overflow-y-auto">
                    {logs.map((log) => (
                      <div key={log.id} className="p-4">
                        <div className="flex items-center gap-2">
                          <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                            log.log_level === 'error' ? 'bg-red-100 text-red-800' :
                            log.log_level === 'warn' ? 'bg-amber-100 text-amber-800' :
                            log.log_level === 'info' ? 'bg-blue-100 text-blue-800' :
                            'bg-gray-100 text-gray-800'
                          }`}>
                            {log.log_level?.toUpperCase() || 'UNKNOWN'}
                          </span>
                          <span className="text-xs text-gray-500">{log.source || 'unknown'}</span>
                          <span className="text-xs text-gray-400 ml-auto">{formatDate(log.timestamp)}</span>
                        </div>
                        <p className="text-sm text-gray-900 mt-1 font-mono">{log.message}</p>
                      </div>
                    ))}
                  </div>
                  {hasMoreLogs && (
                    <div className="p-4 border-t">
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={loadMoreLogs}
                        loading={isLoadingMoreLogs}
                        className="w-full"
                      >
                        <ChevronDown className="h-4 w-4" />
                        Load More ({logsPagination && logsPagination.total ? logsPagination.total - logsPagination.current_page * logsPagination.per_page : 0} remaining)
                      </Button>
                    </div>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Activity</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              {!timeline || timeline.length === 0 ? (
                <div className="p-8 text-center text-gray-500">No activity yet</div>
              ) : (
                <div className="divide-y divide-gray-200 max-h-[500px] overflow-y-auto">
                  {timeline.map((event) => (
                    <div key={event.id} className="p-4">
                      <p className="text-sm text-gray-900">
                        <span className="font-medium">{event.user?.name || 'System'}</span>
                        <span className="text-gray-500"> - {event.event_type.replace('_', ' ')}</span>
                      </p>
                      {event.note && <p className="text-sm text-gray-600 mt-1">{event.note}</p>}
                      <p className="text-xs text-gray-400 mt-1">{formatRelativeTime(event.created_at)}</p>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}