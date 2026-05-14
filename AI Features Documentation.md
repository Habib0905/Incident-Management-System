---
tags:
  - ai-feature
  - incident-management
  - documentation
  - architecture
created: 2026-05-13
updated: 2026-05-14
---

# AI Features — Incident Management System

## Overview

The system has **3 AI-powered features**:

1. **AI Incident Summarization** — Generates a concise summary of an incident using its logs
2. **Read-Only Chatbot Assistant** — Answers questions about incidents, logs, servers, and users
3. **Similar Incident Matching** — Finds previously resolved incidents that are semantically similar

All three features use **Google Gemini 2.5 Flash Lite** as the primary AI provider, with fallback support for OpenAI, Groq, and Ollama.

---

## Feature 1: AI Incident Summarization

### What It Does
When viewing an incident, the user can click **"AI Summarize"** to generate a concise summary covering:
- What happened
- Probable cause
- Suggested next steps

The summary is saved to the database and can be exported as a PDF.

### Architecture

```
[Frontend: Incident Detail Page]
        │
        │  POST /api/incidents/{id}/generate-summary
        ▼
[Backend: IncidentController@generateSummary]
        │
        │  calls
        ▼
[Backend: IncidentSummaryService.generate()]
        │
        │  selects provider (gemini/openai/groq/ollama)
        ▼
[Gemini API / OpenAI API / Groq API / Ollama]
        │
        │  returns summary text
        ▼
[Backend saves to incident.summary column]
        │
        ▼
[Frontend displays + offers PDF download]
```

### Code Flow

#### Step 1: Frontend triggers summarization
**File:** `frontend/src/app/(protected)/incidents/[id]/page.tsx`

```typescript
async function handleGenerateSummary() {
  const result = await generateSummary.mutateAsync(incidentId);
  setSummary(result.summary);
}
```

- User clicks "AI Summarize" button
- Calls the `useGenerateSummary` mutation hook
- Hook calls `incidentService.generateSummary(id)`

#### Step 2: Backend receives request
**File:** `backend/app/Http/Controllers/Api/IncidentController.php` (line 243)

```php
public function generateSummary(Request $request, Incident $incident)
{
    $summary = $this->summaryService->generate($incident);
    $incident->summary = $summary;
    $incident->save();
    $this->timelineService->logSummaryGenerated($incident, $user);
    return response()->json(['summary' => $summary, 'saved' => true]);
}
```

#### Step 3: Service builds prompt and calls AI
**File:** `backend/app/Services/IncidentSummaryService.php`

**3a. Fetch relevant logs** (line 39):
```php
private function getRelevantLogs(Incident $incident)
{
    return $incident->logs()
        ->orderBy('timestamp', 'desc')
        ->limit(20)
        ->get();
}
```
- Gets the **20 most recent logs** for the incident

**3b. Build the prompt** (line 47):
```php
private function buildPrompt(Incident $incident, $logs): string
{
    return <<<PROMPT
You are a DevOps engineer. Summarize this incident concisely.

Incident: {$incident->title}
Type: {$incident->type}
Severity: {$incident->severity}

Recent Logs:
{$logList}

Provide a brief summary covering:
1. What happened
2. Probable cause
3. Suggested next steps

Keep it under 150 words.
PROMPT;
}
```

**3c. Call the AI provider** (line 30):
```php
return match ($this->provider) {
    'openai' => $this->callOpenAI($prompt),
    'ollama' => $this->callOllama($prompt),
    'groq'    => $this->callGroq($prompt),
    'gemini'  => $this->callGemini($prompt),
    default   => $this->callGemini($prompt),
};
```

Default provider is **Gemini** (`config('services.ai.provider', 'gemini')`).

**3d. Gemini API call** (line 146):
```php
$response = Http::timeout(60)
    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key={$this->apiKey}", [
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
    ]);
```

- Uses **gemini-2.5-flash-lite** model
- Has **5 retry attempts** with exponential backoff for rate limits (429 errors)
- Returns the generated text or `null` on failure

#### Step 4: Frontend displays result
- Summary appears in a purple/blue gradient card
- **Bold text** (`**bold**`) is rendered as `<strong>` tags
- "Download PDF" button uses `jsPDF` to generate a report

### Key Details
| Aspect | Value |
|--------|-------|
| Model | Gemini 2.5 Flash Lite (default) |
| Input | Incident title + type + severity + 20 recent logs |
| Output limit | ~150 words, max 500 tokens |
| Storage | Saved to `incidents.summary` column |
| Retry logic | 5 retries with exponential backoff (Gemini) |
| Error handling | Returns null on failure, shows error message on frontend |
| PDF export | Uses `jsPDF` library |

---

## Feature 2: Read-Only Chatbot Assistant

### What It Does
A floating chat widget (bottom-right corner) that lets authenticated users ask questions about:
- Incident counts and breakdowns (by status, severity, type, server)
- Specific incidents by ID
- Recent incidents
- Server lists
- Keyword search across incidents
- **Assigned incidents** — who is working on what
- **User-related queries** — incidents assigned to a person, their activity, how they solved past incidents
- **Follow-up status questions** — "is this resolved?", "is it fixed?"

The bot is **stateless and read-only** — it cannot modify any data.

### Architecture

```
[Frontend: ChatWidget.tsx]
        │
        │  POST /api/chat
        │  { message: "...", history: [...] }
        ▼
[Backend: ChatController@sendMessage]
        │
        │  validates + truncates history to last 4
        ▼
[Backend: ChatBotService.handle()]
        │
        │  1. Detects intent (help, incident ID, server, user name, etc.)
        │  2. Queries database for relevant context
        │  3. Builds context string
        ▼
[ChatBotService.callGemini(message, history, context)]
        │
        │  Sends system prompt + context + user message to Gemini
        ▼
[Gemini API returns response]
        │
        ▼
[Frontend displays in chat bubble]
```

### Code Flow

#### Step 1: Frontend chat widget
**File:** `frontend/src/components/chat/ChatWidget.tsx`

```typescript
const handleSend = async () => {
  const userMessage = { role: 'user', content: input.trim() };
  const updatedMessages = [...messages, userMessage];
  setMessages(updatedMessages);
  
  const history = updatedMessages.slice(-MAX_HISTORY); // last 4 messages
  const response = await chatService.sendMessage(userMessage.content, history);
  setMessages([...updatedMessages, { role: 'assistant', content: response }]);
};
```

- Maintains conversation state in React `useState`
- Sends last **4 messages** as history for context
- Parses `**bold**` markdown in responses

#### Step 2: Backend controller
**File:** `backend/app/Http/Controllers/Api/ChatController.php`

```php
public function sendMessage(Request $request)
{
    $validated = $request->validate([
        'message' => 'required|string|max:500',
        'history' => 'sometimes|array|max:10',
    ]);
    
    $history = array_slice($validated['history'] ?? [], -4);
    $response = $this->chatBotService->handle($message, $history);
    return response()->json(['response' => $response]);
}
```

#### Step 3: ChatBotService — Intent detection + context building
**File:** `backend/app/Services/ChatBotService.php`

**3a. Entry point** (line 20):
```php
public function handle(string $message, array $history = []): string
{
    $lower = strtolower(trim($message));
    
    if (preg_match('/^(help|what can you do|capabilities|commands)$/', $lower)) {
        return $this->helpResponse();
    }
    
    $context = $this->buildContext($message, $lower, $history);
    
    return $this->callGemini($message, $history, $context);
}
```

**3b. Context building** (line 52) — Core logic:

| User mentions... | Data fetched | Method |
|-----------------|--------------|--------|
| "incident", "log", "server", "error", "critical", "open", etc. | Summary stats (total, open, investigating, resolved) | `getSummaryStats()` |
| "incident #123" or "incident 123" | Full incident details + 5 recent logs | `getIncidentDetail(123)` |
| "server", "which server", "per server" | Incident counts grouped by server | `getIncidentsByServer()` |
| "type", "by type", "most common" | Incident counts grouped by type | `getIncidentsByType()` |
| "severity", "critical", "high", "medium", "low" | Incident counts grouped by severity | `getIncidentsBySeverity()` |
| "status", "open", "resolved", "investigating" | Incident counts grouped by status + top 5 open | `getIncidentsByStatus()` |
| "search", "find", "look for" | Keyword search in title/summary/type | `searchIncidents()` |
| "recent", "latest", "last", "newest" | 10 most recent incidents | `getRecentIncidents()` |
| "list server", "show server" | All servers with environment + status | `listServers()` |
| "assigned", "assigned to", "who is working" | All assigned but unresolved incidents with assignee | `getAssignedIncidents()` |
| **User name detected** (e.g., "john", "John Doe") | Incidents by user, activity summary, resolved incidents | `getIncidentsByUser()`, `getUserActivitySummary()`, `getUserResolvedIncidents()` |
| "is this resolved?", "is it fixed?" | Fresh incident status data (extracts ID from message or history) | `getIncidentDetail()` + `extractIncidentIdFromHistory()` |

Each method returns a **formatted text string** that becomes part of the context.

**3c. User-related queries** — New methods:

**`extractUserName($message, $lower)`** — Extracts user names from natural language:
- Patterns: "assigned to John", "John's incidents", "how did John fix", "incidents for john doe"
- Uses regex to capture names (case-insensitive)
- Calls `findMatchingUser()` to fuzzy-match against the `users` table

**`findMatchingUser($name)`** — Fuzzy user name matching:
1. Exact match (case-insensitive)
2. First name prefix match
3. Partial match on any name part

**`getIncidentsByUser($userName)`** — Full user assignment breakdown:
```
John Doe (engineer):
Total assigned: 15 | Open: 3 | Investigating: 2 | Resolved: 10

Open incidents:
#45 [high] STATUS: open - Database connection timeout (prod-db-01)

Investigating:
#102 [medium] STATUS: investigating - Cache eviction spike (redis-01)
```

**`getUserActivitySummary($userName)`** — Activity stats:
- Total assigned, resolved, open, investigating counts
- Resolution rate percentage
- Average resolution time (calculated from `created_at` to `updated_at`)

**`getUserResolvedIncidents($userName)`** — Latest 5 resolved incidents with summaries:
```
John Doe — Resolved incidents (12 total, showing latest 5):
#12 [high] STATUS: resolved - Disk full on /var/log — Summary: Log rotation failed...
```

**`getAssignedIncidents()`** — Lists all assigned but unresolved incidents:
```
Assigned incidents (8 total, not yet resolved):
#975 [low] STATUS: open - Redis persistence failed (Staging Web Server) → Assigned to: John Doe
```

**`extractIncidentIdFromHistory($history)`** — Scans conversation history for `#NNN` references to support follow-up questions like "is this resolved?"

**3d. Follow-up status fix** (line 64-82):
```php
if ($this->mentions($lower, ['is this resolved', 'is this fixed', 'is it resolved', 'is it fixed', 'has this been resolved', 'status of this'])) {
    if (preg_match('/#?(\d+)/', $lower, $m)) {
        $detail = $this->getIncidentDetail((int) $m[1]);
        if ($detail) $parts[] = "CURRENT STATUS DATA:\n{$detail}";
    } else {
        $historyId = $this->extractIncidentIdFromHistory($history);
        if ($historyId) {
            $detail = $this->getIncidentDetail($historyId);
            if ($detail) $parts[] = "CURRENT STATUS DATA:\n{$detail}";
        }
    }
}
```

**3e. Explicit STATUS format** — All incident listings now use `STATUS: {status}` format:
```
#975 [low] STATUS: open - Redis persistence failed (Staging Web Server)
```

**3f. Gemini call with system prompt** (line 551):
```php
$systemPrompt = "You are a read-only assistant for an Incident Management System. "
    . "You answer questions about incidents, logs, servers, and users using the data provided below. "
    . "You CANNOT perform write actions like assigning, resolving, creating, updating, or deleting records. "
    . "When asked about a user, you can tell which incidents they are assigned to, their resolution rate, and how they solved past incidents. "
    . "IMPORTANT: When asked about an incident's status, ALWAYS use the 'Status:' field from the CURRENT STATUS DATA section. Never guess or assume.";
```

### Key Details
| Aspect | Value |
|--------|-------|
| Model | Gemini 2.5 Flash Lite |
| Temperature | 0.3 (low = more deterministic) |
| Max output | 500 tokens |
| History | Last 4 messages sent with each request |
| Timeout | 30 seconds |
| Retries | 3 with exponential backoff |
| Access | All authenticated users (read-only) |
| State | Stateless — no conversation stored in DB |
| Intent detection | Keyword/pattern matching + regex name extraction |
| Context injection | Database queries → formatted text → Gemini prompt |
| User queries | Name extraction → fuzzy match → DB query → formatted context |
| Follow-up support | Scans history for incident IDs, fetches fresh status data |

---

## Feature 3: Similar Incident Matching

### What It Does
When viewing any incident, the system automatically finds up to **3 previously resolved incidents** that are semantically similar. This helps engineers find past solutions to related problems.

### Architecture

```
[Log Ingestion] → creates incident
        │
        │  calls SimilarityService
        ▼
[Build incident text: title + logs]
        │
        │  HTTP POST to FastAPI
        ▼
[FastAPI /embed endpoint]
        │  SentenceTransformer("all-MiniLM-L6-v2")
        │  model.encode(text) → 384-dim vector
        ▼
[Return embedding to Laravel]
        │
        ▼
[Store in PostgreSQL pgvector column]
        │
        ▼
[On incident view: SQL similarity search]
        │  WHERE status = 'resolved'
        │  AND (embedding <=> ?::vector) < 0.75
        │  ORDER BY similarity DESC LIMIT 3
        ▼
[Display in "Similar Past Incidents" card]
```

### Component Breakdown

#### Component A: FastAPI Embedding Service
**File:** `services/similarity-api/main.py`

```python
from fastapi import FastAPI
from sentence_transformers import SentenceTransformer

app = FastAPI()
model = SentenceTransformer("all-MiniLM-L6-v2")

class EmbedRequest(BaseModel):
    text: str

@app.post("/embed")
def embed(req: EmbedRequest):
    vector = model.encode(req.text).tolist()
    return {"embedding": vector}
```

| Aspect | Value |
|--------|-------|
| Model | `all-MiniLM-L6-v2` |
| Size | 80MB |
| Output | 384-dimensional vector |
| Hardware | CPU-only |
| Speed | ~20ms per embedding |
| Training | None needed (pretrained) |
| License | Apache 2.0 |
| Runs on | `localhost:8001` |

**What it does:** Only one thing — takes text, returns a vector. No database, no incident knowledge, completely stateless.

#### Component B: Laravel SimilarityService
**File:** `backend/app/Services/SimilarityService.php`

**B1. Build incident text** (line 18):
```php
public function buildIncidentText(Incident $incident): string
{
    $text = $incident->title;
    $logs = $incident->logs()->select('message', 'log_level')->orderBy('timestamp')->get();
    
    $unique = [];
    foreach ($logs as $log) {
        $key = $log->message;
        if (!isset($unique[$key])) $unique[$key] = $log->log_level;
    }
    
    $uniqueLogs = array_values($unique);
    $first = array_slice($uniqueLogs, 0, 5);
    $last = array_slice($uniqueLogs, -5);
    $selected = array_unique(array_merge($first, $last));
    
    $text .= "\nLogs:\n" . implode("\n", $logLines);
    return $text;
}
```

- Combines **incident title** + **up to 10 unique log messages** (first 5 + last 5)
- Deduplicates logs by message content

**B2. Compute embedding** (line 55):
```php
public function computeEmbedding(string $text): array
{
    $response = Http::timeout(30)
        ->post("http://127.0.0.1:8001/embed", ['text' => $text]);
    
    $embedding = $response->json('embedding');
    return $embedding;
}
```

- Sends text to FastAPI via HTTP
- Validates response is exactly 384 dimensions

**B3. Ensure embedding exists** (line 75):
```php
public function ensureEmbedding(Incident $incident): void
{
    try {
        $text = $this->buildIncidentText($incident);
        $embedding = $this->computeEmbedding($text);
        $incident->update(['embedding' => $embedding, 'last_embedded_at' => now()]);
    } catch (\Exception $e) {
        Log::warning('Failed to compute embedding: ' . $e->getMessage());
    }
}
```

- Called on every incident view (lazy recompute)
- Silently fails — logs warning but doesn't break the page

**B4. Find similar incidents** (line 90):
```php
public function findSimilarIncidents(Incident $incident, int $limit = 3): array
{
    if (!$incident->embedding) return [];

    $embeddingStr = '[' . implode(',', $incident->embedding) . ']';

    $results = DB::select(
        "SELECT id, title, type, severity, assigned_to,
                (1 - (embedding <=> ?::vector)) AS similarity
         FROM incidents
         WHERE status = 'resolved' AND id != ? AND embedding IS NOT NULL
           AND (embedding <=> ?::vector) < 0.75
         ORDER BY similarity DESC LIMIT ?",
        [$embeddingStr, $incident->id, $embeddingStr, $limit]
    );

    return array_map(function ($row) {
        return [
            'id' => (int) $row->id,
            'title' => $row->title,
            'similarity' => round((float) $row->similarity, 4),
        ];
    }, $results);
}
```

- Uses pgvector's `<=>` operator (cosine distance)
- `1 - distance = similarity` (cosine similarity)
- Filters: only resolved incidents, exclude self, distance < 0.75 (similarity > 25%)
- Returns top 3 sorted by similarity descending

#### Component C: When embeddings are computed

**On incident creation** — `LogIngestionService.php` (line 83)
**On incident view** — `IncidentController@show` (line 144)
**Bulk seeding** — `php artisan incidents:compute-embeddings`

#### Component D: Database schema

**Migration:** `backend/database/migrations/2026_05_13_000001_add_embedding_to_incidents.php`
- `embedding` column type: `vector(384)` (pgvector extension)
- `last_embedded_at` tracks when embedding was last computed

#### Component E: Frontend display
**File:** `frontend/src/app/(protected)/incidents/[id]/page.tsx` (line 466)

- Shows as a card below the logs section
- Each result is a clickable link to that incident
- Shows similarity percentage badge

### Key Details
| Aspect | Value |
|--------|-------|
| Embedding model | all-MiniLM-L6-v2 |
| Vector dimensions | 384 |
| Storage | PostgreSQL pgvector |
| Similarity metric | Cosine similarity (1 - cosine distance) |
| Threshold | 25% similarity (distance < 0.75) |
| Max results | 3 |
| Filter | Only resolved incidents |
| Compute timing | On creation + lazy recompute on view |
| FastAPI speed | ~20ms per embedding |
| DB search speed | ~10ms (raw SQL) |
| Model location | `/home/habib-hussain/projects/Incident_Management-System-Model/all-MiniLM-L6-v2/` |

---

## Shared Infrastructure

### AI Provider Configuration
**File:** `backend/config/services.php`

```php
'ai' => [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('AI_BASE_URL'),
],
'similarity_api' => [
    'url' => env('SIMILARITY_API_URL', 'http://127.0.0.1:8001'),
],
```

### Supported AI Providers (for summarization and chatbot)

| Provider | Model | Config | Notes |
|----------|-------|--------|-------|
| **Gemini** (default) | gemini-2.5-flash-lite | `GEMINI_API_KEY` | 5 retries (summary), 3 retries (chat), 60s/30s timeout |
| OpenAI | gpt-4o-mini | `OPENAI_API_KEY` | 60s timeout |
| Groq | llama-3.3-70b-versatile | `GROQ_API_KEY` | 30s timeout |
| Ollama | qwen2.5:0.5b | `AI_BASE_URL` | Local, 120s timeout |

### Error Handling Strategy

All AI features follow the same pattern:
1. **Try** the API call
2. **Retry** with exponential backoff on rate limits (429)
3. **Log** errors to Laravel log
4. **Gracefully degrade** — return null/error message instead of crashing
5. **Frontend** shows user-friendly error message

---

## Data Flow Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                        AI FEATURES OVERVIEW                      │
├─────────────────┬──────────────────┬────────────────────────────┤
│   SUMMARIZATION │     CHATBOT      │   SIMILAR INCIDENTS        │
├─────────────────┼──────────────────┼────────────────────────────┤
│ Trigger: Manual │ Trigger: User    │ Trigger: Auto (view/create)│
│ Button click    │ message in widget│                            │
├─────────────────┼──────────────────┼────────────────────────────┤
│ Input: Title +  │ Input: Natural   │ Input: Title + logs        │
│ 20 recent logs  │ language query   │                            │
├─────────────────┼──────────────────┼────────────────────────────┤
│ AI: Gemini      │ AI: Gemini       │ AI: all-MiniLM-L6-v2       │
│ (or fallback)   │                  │ (local, no API)            │
├─────────────────┼──────────────────┼────────────────────────────┤
│ Output: Text    │ Output: Text     │ Output: Vector (384-dim)   │
│ summary saved   │ response shown   │ stored in pgvector         │
│ to DB           │ in chat          │                            │
├─────────────────┼──────────────────┼────────────────────────────┤
│ PDF export      │ Stateless,       │ SQL similarity search      │
│ available       │ read-only        │ finds top 3 resolved       │
│                 │ + user queries   │                            │
│                 │ + follow-up Q&A  │                            │
└─────────────────┴──────────────────┴────────────────────────────┘
```

---

## File Reference

### Backend (Laravel)
| File | Purpose |
|------|---------|
| `app/Services/IncidentSummaryService.php` | AI summarization logic + provider calls |
| `app/Services/ChatBotService.php` | Chatbot intent detection + context building + Gemini call + user queries + follow-up status |
| `app/Services/SimilarityService.php` | Embedding build/compute/store + similarity search |
| `app/Services/LogIngestionService.php` | Calls SimilarityService on incident creation |
| `app/Http/Controllers/Api/IncidentController.php` | `generateSummary`, `show` (triggers similarity) |
| `app/Http/Controllers/Api/ChatController.php` | Chat endpoint handler |
| `app/Console/Commands/ComputeIncidentEmbeddings.php` | Bulk embedding seeder |
| `database/migrations/2026_05_13_000001_add_embedding_to_incidents.php` | pgvector column migration |

### FastAPI
| File | Purpose |
|------|---------|
| `services/similarity-api/main.py` | Embedding API endpoint |
| `services/similarity-api/requirements.txt` | Python dependencies |

### Frontend (Next.js)
| File | Purpose |
|------|---------|
| `frontend/src/app/(protected)/incidents/[id]/page.tsx` | Incident detail page (summary + similar incidents UI) |
| `frontend/src/components/chat/ChatWidget.tsx` | Floating chat widget |
| `frontend/src/services/incidents.ts` | API calls for incidents |
| `frontend/src/services/chat.ts` | API calls for chatbot |
| `frontend/src/types/index.ts` | TypeScript interfaces |

### Model
| Path | Purpose |
|------|---------|
| `Incident_Management-System-Model/all-MiniLM-L6-v2/` | Downloaded sentence transformer model |
