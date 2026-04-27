# Incident Management System - Technical Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture](#architecture)
3. [Tech Stack](#tech-stack)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [Frontend Structure](#frontend-structure)
7. [Log Ingestion Flow](#log-ingestion-flow)
8. [Incident Detection & Grouping](#incident-detection--grouping)
9. [AI Summarization](#ai-summarization)
10. [Authentication & Authorization](#authentication--authorization)
11. [Unread Tracking](#unread-tracking)
12. [Key Services](#key-services)
13. [Configuration](#configuration)
14. [Running the Project](#running-the-project)
15. [Incident Types & Severity Mapping](#incident-types--severity-mapping)
16. [Log Normalization](#log-normalization)
17. [Performance Optimizations](#performance-optimizations)
18. [Cascade Delete Behavior](#cascade-delete-behavior)

---

## Project Overview

A full-stack **Log-Based Incident Management System** that automatically ingests server logs, detects incidents, groups related events, and provides a real-time dashboard for DevOps engineers and administrators. The system features AI-powered incident summarization, role-based access control, and per-user unread tracking.

### Key Features
- **Automated Log Ingestion**: Watch log files and send to API in real-time
- **Intelligent Incident Detection**: Only `error` and `warn` log levels create incidents
- **Smart Log Grouping**: Related logs are grouped into existing open incidents by type
- **AI-Powered Summaries**: Uses Google Gemini 2.5 Flash-Lite for incident analysis
- **Role-Based Access**: Admin (full control) and Engineer (assigned incidents only)
- **Per-User Unread Tracking**: Computed dynamically via `incident_views` table
- **Real-Time Dashboard**: Auto-polling every 30s for live updates
- **14 Incident Types**: database, auth, network, system, cache, container, cloud, nginx, apache, api, queue, file, email, general

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND (Next.js)                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐ │
│  │Dashboard │  │Incidents │  │  Servers  │  │     Users        │ │
│  │  Page    │  │  Page    │  │  Page     │  │     Page         │ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────────┬─────────┘ │
│       │              │              │                 │           │
│  ┌────┴──────────────┴──────────────┴─────────────────┴───────┐  │
│  │                    React Query + Zustand                    │  │
│  │              (30s auto-polling, state management)           │  │
│  └────────────────────────────┬────────────────────────────────┘  │
└───────────────────────────────┼───────────────────────────────────┘
                                │ HTTP/REST (Axios)
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     BACKEND (Laravel 13)                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐ │
│  │  Auth    │  │Incident  │  │   Log    │  │    Server        │ │
│  │Controller│  │Controller│  │Controller│  │   Controller     │ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────────┬─────────┘  │
│       │              │              │                 │            │
│  ┌────┴──────────────┴──────────────┴─────────────────┴────────┐  │
│  │                    Services Layer                           │  │
│  │  ┌──────────────┐ ┌──────────────┐ ┌─────────────────────┐  │  │
│  │  │LogIngestion  │ │IncidentDetect│ │IncidentSummary      │  │  │
│  │  │  Service     │ │   Service    │ │   Service           │  │  │
│  │  └──────────────┘ └──────────────┘ └─────────────────────┘  │  │
│  │  ┌──────────────┐ ┌──────────────┐ ┌─────────────────────┐  │  │
│  │  │LogNormaliza  │ │IncidentGroup │ │IncidentTimeline     │  │  │
│  │  │  tionService │ │  ingService  │ │   Service           │  │  │
│  │  └──────────────┘ └──────────────┘ └─────────────────────┘  │  │
│  └─────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬───────────────────────────────────┘
                                │ Eloquent ORM
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DATABASE (PostgreSQL)                         │
│  users │ servers │ logs │ incidents │ incident_logs │            │
│  activity_timeline │ incident_views │ personal_access_tokens    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                  LOG INGESTION (Shell Script)                    │
│  ingest_logs.sh → watches log file → sends via curl → API       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    AI PROVIDER (Google Gemini)                   │
│  gemini-2.5-flash-lite with exponential backoff (5 retries)     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Tech Stack

### Backend
| Component | Technology |
|-----------|------------|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | PostgreSQL |
| Auth | Laravel Sanctum (Bearer tokens) |
| HTTP Client | Laravel HTTP Client |

### Frontend
| Component | Technology |
|-----------|------------|
| Framework | Next.js 16 (React 19) |
| State Management | Zustand (persisted to localStorage) |
| Data Fetching | TanStack React Query v5 |
| HTTP Client | Axios |
| Styling | Tailwind CSS v4 |
| Icons | Lucide React |
| PDF Generation | jsPDF |

### External Services
| Service | Provider |
|---------|----------|
| AI Summarization | Google Gemini 2.5 Flash-Lite |
| Log Ingestion | Shell script + curl |

---

## Database Schema

### Users
```sql
id              BIGINT PRIMARY KEY
name            VARCHAR(255)
email           VARCHAR(255) UNIQUE
password        VARCHAR(255) -- hashed
role            ENUM('admin', 'engineer') DEFAULT 'engineer'
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Servers
```sql
id              BIGINT PRIMARY KEY
name            VARCHAR(255)
description     TEXT NULLABLE
environment     VARCHAR(255) -- 'production', 'staging', 'development'
api_key         VARCHAR(255) UNIQUE -- format: sk_<60 random chars>
is_active       BOOLEAN DEFAULT true
created_by      BIGINT FK -> users.id (CASCADE DELETE)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Logs
```sql
id              BIGINT PRIMARY KEY
server_id       BIGINT FK -> servers.id (CASCADE DELETE)
message         TEXT
log_level       VARCHAR(255) NULLABLE -- 'error', 'warn', 'info', 'debug'
source          VARCHAR(255) NULLABLE -- 'database', 'auth', 'nginx', etc.
timestamp       TIMESTAMP
raw_payload     JSON NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

Indexes: server_id, log_level, timestamp, created_at
```

### Incidents
```sql
id              BIGINT PRIMARY KEY
server_id       BIGINT FK -> servers.id (CASCADE DELETE)
created_by      BIGINT FK -> users.id (NULL ON DELETE)
assigned_to     BIGINT FK -> users.id (NULL ON DELETE)
title           VARCHAR(255)
type            VARCHAR(255) -- 14 types (see Incident Types section)
severity        VARCHAR(255) -- 'low', 'medium', 'high', 'critical'
status          VARCHAR(255) DEFAULT 'open' -- 'open', 'investigating', 'resolved'
summary         TEXT NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

Indexes: type, status, severity, created_at
```

### Incident Logs (Pivot)
```sql
id              BIGINT PRIMARY KEY
incident_id     BIGINT FK -> incidents.id (CASCADE DELETE)
log_id          BIGINT FK -> logs.id (CASCADE DELETE)
created_at      TIMESTAMP

Unique: (incident_id, log_id)
Index: incident_id
```

### Activity Timeline
```sql
id              BIGINT PRIMARY KEY
incident_id     BIGINT FK -> incidents.id (CASCADE DELETE)
user_id         BIGINT FK -> users.id (NULL ON DELETE)
event_type      VARCHAR(255) -- 'created', 'assigned', 'status_changed', 'note_added', 'summary_generated'
note            TEXT NULLABLE
created_at      TIMESTAMP
```

### Incident Views
```sql
id              BIGINT PRIMARY KEY
incident_id     BIGINT FK -> incidents.id (CASCADE DELETE)
user_id         BIGINT FK -> users.id (CASCADE DELETE)
viewed_at       TIMESTAMP

Unique: (incident_id, user_id)
Index: (user_id, incident_id)
```

### Personal Access Tokens (Sanctum)
```sql
id              BIGINT PRIMARY KEY
tokenable_type  VARCHAR(255)
tokenable_id    BIGINT
name            VARCHAR(255)
token           VARCHAR(64) UNIQUE
abilities       TEXT NULLABLE
last_used_at    TIMESTAMP NULLABLE
expires_at      TIMESTAMP NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

---

## API Endpoints

### Authentication (Public)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Login with email/password, returns user + token |
| POST | `/api/logout` | Logout (requires Sanctum token) |
| GET | `/api/me` | Get current authenticated user |

### Incidents (Authenticated)
| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/api/incidents` | List incidents (filtered, max 50) | All |
| GET | `/api/incidents/{id}` | Get incident detail with logs | All |
| PATCH | `/api/incidents/{id}` | Update status/summary | Admin or assigned |
| DELETE | `/api/incidents/{id}` | Delete incident | Admin only |
| POST | `/api/incidents/{id}/assign` | Assign to user | Admin only |
| POST | `/api/incidents/{id}/notes` | Add note to timeline | Admin or assigned |
| POST | `/api/incidents/{id}/generate-summary` | Generate AI summary | All |
| POST | `/api/incidents/{id}/view` | Mark as viewed | All |
| GET | `/api/incidents/{id}/timeline` | Get activity timeline | All |
| GET | `/api/me/incidents` | Get incidents assigned to user | All |
| GET | `/api/me/unread-count` | Get unread incident count | All |

**Incident Filters**: `status`, `severity`, `server_id`, `environment`, `assigned_to`, `type`, `search`, `created_after`, `created_before`

### Servers (Admin Only)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/servers` | List all servers |
| POST | `/api/admin/servers` | Create server (auto-generates API key) |
| GET | `/api/admin/servers/{id}` | Get server detail |
| PATCH | `/api/admin/servers/{id}` | Update server |
| DELETE | `/api/admin/servers/{id}` | Delete server (cascade deletes logs + incidents) |
| POST | `/api/admin/servers/{id}/regenerate-key` | Regenerate API key |
| POST | `/api/admin/servers/{id}/revoke-key` | Deactivate API key |
| POST | `/api/admin/servers/{id}/activate-key` | Activate API key |

### Users (Admin Only)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/users` | List all users |
| POST | `/api/admin/users` | Create user |
| PATCH | `/api/admin/users/{id}` | Update user (name, email, password, role) |
| DELETE | `/api/admin/users/{id}` | Delete user (cannot delete self) |

### Logs (Public with API Key)
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/logs` | Ingest a log entry | Server API key (Bearer token) |
| GET | `/api/logs` | List logs (filtered) | None |

**Log Ingestion Auth**: Uses `Authorization: Bearer <server_api_key>` header. Server must exist and be active.

---

## Frontend Structure

```
frontend/src/
├── app/
│   ├── layout.tsx                    # Root layout with QueryProvider
│   ├── page.tsx                      # Home (redirects to /login or /dashboard)
│   ├── globals.css                   # Global styles
│   ├── login/
│   │   └── page.tsx                  # Login page
│   └── (protected)/
│       ├── layout.tsx                # Protected layout with AppLayout
│       ├── dashboard/
│       │   └── page.tsx              # Dashboard with stat cards + recent incidents
│       ├── incidents/
│       │   ├── page.tsx              # Incidents list with search/filter
│       │   └── [id]/
│       │       └── page.tsx          # Incident detail, logs, timeline, AI summary
│       ├── servers/
│       │   └── page.tsx              # Server management (admin only)
│       ├── users/
│       │   └── page.tsx              # User management (admin only)
│       └── logs/
│           └── page.tsx              # Log viewer
├── components/
│   ├── Providers.tsx                 # QueryProvider, ProtectedRoute, AdminRoute
│   ├── layout/
│   │   └── AppLayout.tsx             # Sidebar + Header layout
│   └── ui/
│       ├── Badge.tsx                 # SeverityBadge, StatusBadge, TypeBadge, etc.
│       ├── Button.tsx                # Button with variants + loading state
│       ├── Card.tsx                  # Card, CardHeader, CardContent, etc.
│       ├── Feedback.tsx              # Alert, ConfirmDialog, LoadingSpinner, EmptyState
│       ├── Input.tsx                 # Input, Select, Textarea
│       ├── Table.tsx                 # Table, TableHeader, TableBody, etc.
│       └── index.ts
├── hooks/
│   ├── index.ts
│   ├── useIncidents.ts               # useIncidents, useIncident, useMyIncidents, etc.
│   ├── useServers.ts                 # useServers, useCreateServer, etc.
│   ├── useLogs.ts                    # useLogs
│   └── useUsers.ts                   # useUsers, useCreateUser, etc.
├── lib/
│   ├── api.ts                        # Axios instance with auth interceptor
│   └── utils.ts                      # cn(), formatDate, color maps, etc.
├── services/
│   ├── index.ts
│   ├── auth.ts                       # authService (login, logout, getMe)
│   ├── incidents.ts                  # incidentService
│   ├── servers.ts                    # serverService
│   ├── logs.ts                       # logService
│   └── users.ts                      # userService
├── store/
│   ├── index.ts
│   └── auth.ts                       # useAuthStore (Zustand with persist)
└── types/
    └── index.ts                      # TypeScript interfaces
```

### State Management
- **Zustand** (`auth.ts`): Persists user + token to localStorage, dispatches `auth:user-changed` custom event on login/logout
- **React Query**: All data fetching uses React Query with 30s auto-polling on incident-related queries
- **QueryClient**: Singleton instance, cleared on auth state change

### Auto-Polling
| Hook | Polling Interval |
|------|-----------------|
| `useIncidents` | 30s |
| `useMyIncidents` | 30s |
| `useUnreadCount` | 30s |
| `useLogs` | 10s |

---

## Log Ingestion Flow

### Shell Script (`backend/ingest_logs.sh`)
```bash
# Start ingestion daemon
./ingest_logs.sh start

# Run once
./ingest_logs.sh once

# Stop daemon
./ingest_logs.sh stop

# Check status
./ingest_logs.sh status
```

**Configuration** (loaded from `backend/.env`):
- `API_URL` - Backend API base URL
- `SERVER_API_KEY` - Server API key for authentication
- `LOG_FILE` - Path to log file to watch
- `CHECKPOINT_FILE` - Offset tracking file (default: `.ingest_offset`)
- `POLL_INTERVAL` - Seconds between checks (default: 2)

**Log Format**: `timestamp [level] [source] message`
```
2026-04-27T08:00:00Z [ERROR] [database] Connection pool exhausted
```

**Ingestion Pipeline**:
1. Script reads log file from last checkpoint offset
2. Python regex parses each line into structured JSON
3. Each log is sent via `curl` to `POST /api/logs` with server API key
4. Checkpoint offset is saved after successful processing
5. If file is truncated (size < offset), offset resets to 0

### Backend Processing (`LogIngestionService`)
1. **Normalize** (`LogNormalizationService`): Extract message, level, source, timestamp from JSON or text
2. **Store** (`Log` model): Save normalized log to database
3. **Detect** (`IncidentDetectionService`): Analyze if log should create an incident
4. **Group** (`IncidentGroupingService`): Find existing open incident of same type on same server
5. **Create or Attach**: Either create new incident or attach log to existing one
6. **Timeline** (`IncidentTimelineService`): Log "Incident created" event to activity timeline

---

## Incident Detection & Grouping

### Detection Rules (`IncidentDetectionService`)
- Only logs with `log_level` of `error` or `warn` trigger incident creation
- Incident `type` is derived from the log's `source` field
- If source is `unknown`, type defaults to `general`
- Incident `severity` is mapped from the source type (see severity mapping table)
- Incident `title` is built from the log message (cleaned, truncated to 80 chars)

### Grouping Logic (`IncidentGroupingService`)
- Searches for an existing **open** incident on the **same server** with the **same type**
- If found: attaches the new log to the existing incident via `incident_logs` pivot
- If not found: creates a new incident with status `open`

### Severity Mapping
| Source Type | Severity |
|-------------|----------|
| database | critical |
| system | critical |
| network | high |
| auth | high |
| container | high |
| cloud | high |
| nginx | high |
| apache | high |
| api | medium |
| queue | medium |
| file | medium |
| email | medium |
| general | medium |
| cache | low |

---

## AI Summarization

### Provider Configuration
- **Provider**: `gemini` (configurable via `AI_PROVIDER` env var)
- **Model**: `gemini-2.5-flash-lite`
- **Supported Providers**: gemini, openai, ollama, groq
- **API Key**: `AI_API_KEY` env var

### Retry Strategy
- **Max Retries**: 5 attempts
- **Backoff**: Exponential (2s, 4s, 8s, 16s, 32s)
- **Timeout**: 60 seconds per request

### Prompt Structure
```
You are a DevOps engineer. Summarize this incident concisely.

Incident: {title}
Type: {type}
Severity: {severity}

Recent Logs:
[level] message
[level] message
...

Provide a brief summary covering:
1. What happened
2. Probable cause
3. Suggested next steps

Keep it under 150 words.
```

### Log Selection
- Fetches up to 20 most recent logs related to the incident
- Ordered by timestamp descending

### Output
- Summary is saved to `incidents.summary` field
- Activity timeline event `summary_generated` is recorded
- Frontend renders markdown `**bold**` syntax
- PDF download available via jsPDF

---

## Authentication & Authorization

### Auth Flow
1. User logs in via `POST /api/login` with email + password
2. Laravel Sanctum creates a personal access token
3. Token is returned to frontend and stored in localStorage
4. Axios interceptor attaches `Authorization: Bearer <token>` to all requests
5. On 401 response, frontend clears localStorage and redirects to `/login`

### Role-Based Access Control
| Feature | Admin | Engineer |
|---------|-------|----------|
| View all incidents | Yes | Yes |
| Update incident status | Yes | Only assigned |
| Add notes | Yes | Only assigned |
| Generate AI summary | Yes | Yes |
| Assign incidents | Yes | No |
| Delete incidents | Yes | No |
| Manage servers | Yes | No |
| Manage users | Yes | No |
| View logs | Yes | Yes |

### Middleware
- `auth:sanctum` - Laravel Sanctum token authentication
- `role:admin` - Custom `CheckRole` middleware for admin-only routes

### Admin Deletion Protection
- Admin cannot delete their own account
- Admin can delete other admins and engineers

---

## Unread Tracking

### Mechanism
- **No mutable counter** - unread count is computed dynamically
- `incident_views` table tracks per-user read state
- Unread = all incidents NOT in `incident_views` for the current user

### SQL Logic
```sql
SELECT COUNT(*) FROM incidents
WHERE NOT EXISTS (
    SELECT 1 FROM incident_views
    WHERE incident_views.incident_id = incidents.id
    AND incident_views.user_id = :user_id
)
```

### View Tracking
- When a user opens an incident detail page, `POST /api/incidents/{id}/view` is called
- `IncidentView::firstOrCreate` ensures idempotency
- Frontend marks incident as `is_viewed` in cache and invalidates unread count query

### Cascade Behavior
- When an incident is deleted, its `incident_views` records are also deleted (CASCADE)
- When a server is deleted, all associated incidents and their views are deleted (CASCADE)

---

## Key Services

### LogNormalizationService
Normalizes raw log payloads into a consistent format. Supports:
- **JSON logs** with field aliasing (Winston, Python, Serilog, Log4j formats)
- **Plain text logs** with regex-based detection

**Field Aliases**:
| Canonical | Aliases |
|-----------|---------|
| message | msg, @m, text, log, description |
| level | severity, log_level, loglevel, @l, priority |
| timestamp | time, @t, ts, datetime, date, created_at |
| source | service, component, app, logger, channel, facility |

### IncidentFilterService
Applies filters to incident queries:
- `status`, `severity`, `server_id`, `environment`, `assigned_to`, `type`
- `search` (ilike on title and summary)
- `created_after`, `created_before`
- `getMyIncidents(user)` - filters by `assigned_to`

### IncidentTimelineService
Records activity events:
- `created` - Incident created
- `assigned` - Incident assigned to user
- `status_changed` - Status transition (old → new)
- `note_added` - User added a note
- `summary_generated` - AI summary generated

### IncidentViewService
- `markAsViewed(incident, user)` - Creates or retrieves view record
- `isViewed(incident, user)` - Checks if user has viewed incident
- `getUnreadCount(user)` - Computes total unread incidents

### ApiKeyService
- `generateKey()` - Creates `sk_` + 60 random chars
- `regenerateKey(server)` - Replaces existing key
- `revokeKey(server)` - Sets `is_active = false`
- `activateKey(server)` - Sets `is_active = true`

---

## Configuration

### Backend Environment Variables (`backend/.env`)
```env
# Application
APP_NAME=LogSystem
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=logsystem
DB_USERNAME=postgres
DB_PASSWORD=postgres

# AI Provider
AI_PROVIDER=gemini
AI_API_KEY=your-gemini-api-key

# Log Ingestion
SERVER_API_KEY=sk_your-server-api-key
LOG_FILE=storage/logs/sample/production.log
API_URL=http://localhost:8000/api
```

### Frontend Environment Variables (`frontend/.env.local`)
```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
```

### AI Service Config (`backend/config/services.php`)
```php
'ai' => [
    'provider' => env('AI_PROVIDER', 'rule-based'),
    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL', 'http://localhost:11434'),
],
```

---

## Running the Project

### Prerequisites
- PHP 8.3+
- PostgreSQL
- Node.js 18+
- Composer
- Python 3 (for log ingestion script)

### Backend Setup
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Frontend Setup
```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

### Demo Credentials
| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | password |
| Engineer | john@example.com | password |
| Engineer | jane@example.com | password |

### Log Ingestion
```bash
cd backend
# Start daemon
./ingest_logs.sh start

# Or run once
./ingest_logs.sh once

# Check status
./ingest_logs.sh status

# Stop daemon
./ingest_logs.sh stop
```

### Default Ports
- Backend: `http://localhost:8000`
- Frontend: `http://localhost:3000`

---

## Incident Types & Severity Mapping

### Supported Incident Types
| Type | Color | Default Severity |
|------|-------|-----------------|
| database | purple | critical |
| auth | blue | high |
| network | cyan | high |
| system | gray | critical |
| container | indigo | high |
| cloud | sky | high |
| nginx | green | high |
| apache | teal | high |
| api | violet | medium |
| queue | amber | medium |
| file | stone | medium |
| email | rose | medium |
| cache | orange | low |
| general | slate | medium |

### Incident Statuses
| Status | Description |
|--------|-------------|
| open | Newly created, not yet investigated |
| investigating | Engineer is actively working on it |
| resolved | Incident has been resolved |

### Incident Severities
| Severity | Description |
|----------|-------------|
| critical | System/database failure, immediate action required |
| high | Service degradation, needs prompt attention |
| medium | Partial failure, should be addressed soon |
| low | Minor issue, can be handled during normal operations |

---

## Log Normalization

### Text Log Format
```
2026-04-27T08:00:00Z [ERROR] [database] Connection pool exhausted
```
Parsed into:
```json
{
  "timestamp": "2026-04-27T08:00:00Z",
  "level": "error",
  "source": "database",
  "message": "Connection pool exhausted"
}
```

### JSON Log Format (with aliasing)
```json
{
  "msg": "Connection refused",
  "severity": "err",
  "time": "2026-04-27T08:00:00Z",
  "service": "api-gateway"
}
```
Normalized to:
```json
{
  "message": "Connection refused",
  "log_level": "error",
  "source": "network",
  "timestamp": "2026-04-27T08:00:00Z",
  "raw_payload": { ... }
}
```

### Source Detection (from text)
When source is not explicitly provided, the system detects it from message content using keyword matching across 14 categories:
- **container**: kubelet error, pod failed, docker error, crashloopbackoff, etc.
- **apache**: apache error, httpd error, mod_rewrite error, etc.
- **nginx**: nginx error, upstream timed out, no live upstreams, etc.
- **cache**: redis error, memcached error, cache miss rate high, etc.
- **database**: sqlstate, database connection refused, too many connections, etc.
- **auth**: jwt expired, authentication failed, access denied, etc.
- **cloud**: aws error, ec2 error, lambda error, etc.
- **queue**: rabbitmq error, job failed, max retries exceeded, etc.
- **email**: smtp error, mail delivery failed, sendgrid error, etc.
- **api**: api error, 502 bad gateway, rate limit exceeded, etc.
- **file**: file not found, upload failed, ENOENT, etc.
- **system**: oom killer, segfault, disk full, cpu spike, etc.
- **network**: dns error, ssl handshake failed, connection refused, etc.
- **unknown**: fallback when no pattern matches

### Log Level Normalization
| Input | Normalized |
|-------|-----------|
| error, err | error |
| warning, warn | warn |
| info, information | info |
| debug, dbg | debug |
| critical, crit, fatal | error |

---

## Performance Optimizations

### Database Indexes
- `logs`: server_id, log_level, timestamp, created_at
- `incidents`: type, status, severity, created_at
- `incident_views`: user_id, (user_id, incident_id) composite
- `incident_logs`: incident_id

### Query Optimizations
- Incident list limited to 50 records
- Logs pagination with configurable page size (default 50, max 100)
- `ilike` for PostgreSQL case-insensitive search
- `NOT EXISTS` subquery for unread count (more efficient than LEFT JOIN + IS NULL)

### Frontend Optimizations
- React Query caching with `staleTime: 300000` (5 min) and `gcTime: 600000` (10 min)
- Auto-polling at 30s intervals (incidents) and 10s (logs)
- Zustand persist middleware for auth state (avoids re-login on refresh)
- Singleton QueryClient instance (prevents multiple client creation)
- `refetchOnWindowFocus: false` to reduce unnecessary refetches

---

## Cascade Delete Behavior

When a server is deleted:
1. All associated `logs` are deleted (FK cascade)
2. All associated `incidents` are deleted (FK cascade)
3. `incident_logs` pivot records are deleted (FK cascade)
4. `activity_timeline` records are deleted (FK cascade)
5. `incident_views` records are deleted (FK cascade)

When an incident is deleted (via API):
1. `activity_logs` are deleted manually
2. `incident_views` are deleted manually
3. `incident_logs` pivot records are detached
4. `incidents` record is deleted
