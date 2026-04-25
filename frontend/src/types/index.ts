export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'engineer';
  created_at: string;
  updated_at: string;
}

export interface Server {
  id: number;
  name: string;
  description: string | null;
  environment: 'production' | 'staging' | 'development';
  api_key: string;
  is_active: boolean;
  created_by: number;
  created_at: string;
  updated_at: string;
}

export interface Log {
  id: number;
  server_id: number;
  message: string;
  log_level: 'error' | 'warn' | 'info' | 'debug' | null;
  source: string | null;
  timestamp: string;
  raw_payload: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
  server?: {
    id: number;
    name: string;
  };
}

export interface Incident {
  id: number;
  server_id: number | null;
  created_by: number | null;
  assigned_to: number | null;
  title: string;
  type: 'database' | 'auth' | 'network' | 'system' | 'general';
  severity: 'low' | 'medium' | 'high' | 'critical';
  status: 'open' | 'investigating' | 'resolved';
  summary: string | null;
  created_at: string;
  updated_at: string;
  is_viewed?: boolean;
  server?: Server | null;
  assigned_user?: User | null;
  creator?: User | null;
  logs?: Log[];
  activityLogs?: ActivityLog[];
}

export interface IncidentLog {
  id: number;
  incident_id: number;
  log_id: number;
  created_at: string;
}

export interface ActivityLog {
  id: number;
  incident_id: number;
  user_id: number | null;
  event_type: 'created' | 'assigned' | 'status_changed' | 'note_added' | 'summary_generated';
  note: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
  };
}

export interface IncidentView {
  id: number;
  incident_id: number;
  user_id: number;
  viewed_at: string;
}

export interface LogsPagination {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export interface IncidentFilters {
  status?: string;
  severity?: string;
  server_id?: number;
  environment?: string;
  assigned_to?: number;
  type?: string;
  search?: string;
  created_after?: string;
  created_before?: string;
}

export type SeverityLevel = 'low' | 'medium' | 'high' | 'critical';
export type IncidentStatus = 'open' | 'investigating' | 'resolved';
export type IncidentType = 'database' | 'auth' | 'network' | 'system' | 'general';
export type LogLevel = 'error' | 'warn' | 'info' | 'debug';