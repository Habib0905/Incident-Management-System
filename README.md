# Incident Management System

A full-stack **Log-Based Incident Management System** that automatically ingests server logs, detects incidents, groups related events, and provides a real-time dashboard for DevOps engineers and administrators.

## Features

- **Automated Log Ingestion** — Watch log files and send to API in real-time
- **Intelligent Incident Detection** — Only `error` and `warn` log levels create incidents
- **Smart Log Grouping** — Related logs are grouped into existing open incidents by type
- **AI-Powered Summaries** — Uses Google Gemini 2.5 Flash-Lite for incident analysis
- **Role-Based Access Control** — Admin (full control) and Engineer (assigned incidents only)
- **Per-User Unread Tracking** — Computed dynamically, no cached counters
- **Real-Time Dashboard** — Auto-polling every 30s for live updates
- **14 Incident Types** — database, auth, network, system, cache, container, cloud, nginx, apache, api, queue, file, email, general

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.3), PostgreSQL |
| Frontend | Next.js 16 (React 19), Tailwind CSS v4 |
| State Management | Zustand + TanStack React Query v5 |
| Auth | Laravel Sanctum (Bearer tokens) |
| AI | Google Gemini 2.5 Flash-Lite |
| Log Ingestion | Shell script + Python 3 |

## Setup

### Prerequisites

- PHP 8.3+
- Composer
- PostgreSQL
- Node.js 20+
- Python 3 (for log ingestion)
- OS: Linux (WSL required on Windows)

### 1. Database

Create the PostgreSQL database:

```bash
psql -U postgres -c "CREATE DATABASE logsystem;"
```

### 2. Backend

```bash
cd backend

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env and update DB credentials if different from defaults:
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=logsystem
# DB_USERNAME=postgres
# DB_PASSWORD=postgres

# Run migrations and seed demo data
php artisan migrate
php artisan db:seed

# Start the server
php artisan serve
```

### 3. Frontend

```bash
cd frontend

# Install dependencies
npm install

# Configure environment (backend must be running on port 8000)
echo 'NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api' > .env.local

# Start the dev server
npm run dev
```

### 4. Log Ingestion Agent

```bash
cd Agent

# Configure environment
cp .env.example .env

# Edit .env and set your SERVER_API_KEY (from seed data or admin panel):
# SERVER_API_KEY=sk_demo_server_123456789012345678901234567890123456789

# Start the log watcher in the background
./ingest_logs.sh start

# Or run once
./ingest_logs.sh once

# Check status
./ingest_logs.sh status

# Stop
./ingest_logs.sh stop
```

### 5. Application Log Simulator (Demo)

```bash
cd Application

# Start the simulator (writes realistic logs continuously)
./simulate_app.sh

# Press Ctrl+C to stop
```

### Access

| Service | URL |
|---------|-----|
| Frontend | http://localhost:3000 |
| Backend API | http://localhost:8000 |

### Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | password |
| Engineer | john@example.com | password |
| Engineer | jane@example.com | password |

## Log Format

The ingestion agent expects logs in this format:

```
2026-04-28T12:00:00Z [ERROR] [database] Connection pool exhausted
```

It also supports unstructured lines (parsed as `info` level, `unknown` source):

```
Some random error message without a timestamp
```

## Project Structure

```
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/   # API controllers
│   │   ├── Models/                 # Eloquent models
│   │   └── Services/               # Business logic services
│   ├── database/
│   │   ├── migrations/             # Database schema
│   │   └── seeders/                # Demo data seeder
│   └── routes/api.php              # API routes
├── frontend/                # Next.js frontend
│   ├── src/
│   │   ├── app/                    # Pages (App Router)
│   │   ├── components/             # UI components
│   │   ├── hooks/                  # React Query hooks
│   │   ├── services/               # API service layer
│   │   ├── store/                  # Zustand auth store
│   │   └── types/                  # TypeScript interfaces
│   └── package.json
├── Agent/                   # Log ingestion agent
│   ├── ingest_logs.sh              # Standalone log watcher
│   └── .env.example                # Agent configuration template
├── Application/             # Simulated application (for demo)
│   ├── simulate_app.sh             # Generates realistic application logs
│   └── logs/
│       └── sample/
│           └── production.log      # Log file watched by Agent
├── docker-compose.yml       # Docker orchestration (optional)
└── README.md
```

## API Overview

The backend exposes a REST API at `http://localhost:8000/api`. Key endpoints:

- **Auth** — `POST /login`, `POST /logout`, `GET /me`
- **Incidents** — CRUD at `/api/incidents`, plus `/assign`, `/notes`, `/generate-summary`, `/view`, `/timeline`
- **Servers** — Admin-only CRUD at `/api/admin/servers` with key management
- **Users** — Admin-only CRUD at `/api/admin/users`
- **Logs** — `POST /api/logs` (server API key auth), `GET /api/logs`

All endpoints except login and log ingestion require a Sanctum Bearer token. Log ingestion uses the server's API key. For full endpoint details, see [`backend/routes/api.php`](backend/routes/api.php).

## Incident Types & Severity

| Type | Severity | Triggered By |
|------|----------|-------------|
| database | critical | SQL errors, connection pool exhausted, deadlocks |
| system | critical | OOM killer, disk full, segfaults, CPU spikes |
| network | high | Connection refused, DNS failures, SSL issues |
| auth | high | Brute force, JWT expired, authentication failures |
| container | high | CrashLoopBackOff, OOM killed pods, image pull failures |
| cloud | high | EC2 terminated, S3 failures, Lambda errors |
| nginx | high | Upstream timeout, no live upstreams, worker crashes |
| apache | high | Segfaults, worker process failures, mod_ssl errors |
| api | medium | 502/503 errors, rate limits, gateway timeouts |
| queue | medium | Job failures, RabbitMQ disconnections, retry exhaustion |
| file | medium | File not found, upload failures, permission errors |
| email | medium | SMTP failures, delivery rejections, connection refused |
| cache | low | Redis timeouts, cache eviction spikes, connection errors |
| general | medium | Unknown source, unmapped errors |

## Architecture

```
┌──────────────────┐     writes logs      ┌──────────────────┐
│   Application    │ ────────────────────►│  production.log  │
│ (simulate_app.sh)│                      │  (log file)      │
└──────────────────┘                      └────────┬─────────┘
                                                   │
                                            polls every 2s
                                                   ▼
┌──────────────────┐     HTTP POST      ┌──────────────────┐
│   Backend API    │ ◄──────────────────│      Agent       │
│   (Laravel)      │   ingest_logs.sh   │ (ingest_logs.sh) │
└────────┬─────────┘                    └──────────────────┘
         │
  ┌──────┴───────┐
  │  PostgreSQL  │
  └──────────────┘
         ▲
┌────────┴───────┐
│   Frontend     │
│   (Next.js)    │
└────────────────┘
```

## License

MIT
