<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as FacadeLog;

class ChatBotService
{
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.ai.api_key');
    }

    public function handle(string $message, array $history = []): string
    {
        $lower = strtolower(trim($message));

        if (preg_match('/^(help|what can you do|capabilities|commands)$/', $lower)) {
            return $this->helpResponse();
        }

        $context = $this->buildContext($message, $lower, $history);

        return $this->callGemini($message, $history, $context);
    }

    private function helpResponse(): string
    {
        return "I can help you query the Incident Management System. Just ask me anything about:\n\n"
            . "• **Incidents** — counts, breakdowns, specific incidents by ID\n"
            . "• **Logs** — recent logs, logs for a specific incident\n"
            . "• **Servers** — server list, incidents per server\n"
            . "• **Status & Severity** — open/critical/high/medium/low incidents\n"
            . "• **Users** — incidents assigned to someone, their activity, how they resolved incidents\n\n"
            . "Examples:\n"
            . "• \"Which server has the most incidents?\"\n"
            . "• \"Show me open incidents\"\n"
            . "• \"Incident #123\"\n"
            . "• \"What's the breakdown by severity?\"\n"
            . "• \"List incidents which are assigned\"\n"
            . "• \"Which incidents is John assigned to?\"\n"
            . "• \"How many incidents did John resolve?\"\n"
            . "• \"How did John solve the database timeout?\"";
    }

    private function buildContext(string $message, string $lower, array $history = []): string
    {
        $parts = [];

        // Always include summary stats for any incident-related query
        if ($this->mentions($lower, ['incident', 'log', 'server', 'error', 'critical', 'open', 'resolved', 'severity', 'status', 'breakdown', 'count', 'how many', 'most', 'which', 'per'])) {
            $stats = $this->getSummaryStats();
            if ($stats) {
                $parts[] = $stats;
            }
        }

        // Follow-up status questions: "is this resolved?", "is it fixed?", "status of this?"
        if ($this->mentions($lower, ['is this resolved', 'is this fixed', 'is it resolved', 'is it fixed', 'has this been resolved', 'status of this', 'has it been resolved'])) {
            // Try to extract incident ID from the message
            if (preg_match('/#?(\d+)/', $lower, $m)) {
                $detail = $this->getIncidentDetail((int) $m[1]);
                if ($detail) {
                    $parts[] = "CURRENT STATUS DATA:\n{$detail}";
                }
            } else {
                // Extract incident ID from conversation history
                $historyId = $this->extractIncidentIdFromHistory($history);
                if ($historyId) {
                    $detail = $this->getIncidentDetail($historyId);
                    if ($detail) {
                        $parts[] = "CURRENT STATUS DATA:\n{$detail}";
                    }
                }
            }
        }

        // Incident by ID (also catches "#123" standalone)
        if (preg_match('/(?:incident\s*)#?(\d+)/', $lower, $m)) {
            $detail = $this->getIncidentDetail((int) $m[1]);
            if ($detail) {
                $parts[] = $detail;
            }
        }

        // Incidents by server
        if ($this->mentions($lower, ['server', 'which server', 'per server', 'by server', 'server.*most', 'most.*server'])) {
            $byServer = $this->getIncidentsByServer();
            if ($byServer) {
                $parts[] = $byServer;
            }
        }

        // Incidents by type
        if ($this->mentions($lower, ['type', 'by type', 'most common', 'breakdown.*type', 'type.*breakdown'])) {
            $byType = $this->getIncidentsByType();
            if ($byType) {
                $parts[] = $byType;
            }
        }

        // Incidents by severity
        if ($this->mentions($lower, ['severity', 'by severity', 'breakdown.*severity', 'severity.*breakdown', 'critical', 'high', 'medium', 'low'])) {
            $bySeverity = $this->getIncidentsBySeverity();
            if ($bySeverity) {
                $parts[] = $bySeverity;
            }
        }

        // Incidents by status
        if ($this->mentions($lower, ['status', 'open', 'resolved', 'investigating', 'by status'])) {
            $byStatus = $this->getIncidentsByStatus();
            if ($byStatus) {
                $parts[] = $byStatus;
            }
        }

        // Search by keyword
        if ($this->mentions($lower, ['search', 'find', 'look for', 'about'])) {
            $search = $this->searchIncidents($message);
            if ($search) {
                $parts[] = $search;
            }
        }

        // Recent incidents
        if ($this->mentions($lower, ['recent', 'latest', 'last', 'newest'])) {
            $recent = $this->getRecentIncidents();
            if ($recent) {
                $parts[] = $recent;
            }
        }

        // Server list
        if ($this->mentions($lower, ['list server', 'show server', 'all server', 'server list'])) {
            $servers = $this->listServers();
            if ($servers) {
                $parts[] = $servers;
            }
        }

        // List assigned incidents (fix for "list incidents which are assigned")
        if ($this->mentions($lower, ['assigned', 'assign to', 'assigned to', 'my incidents', 'who is working', 'list assigned'])) {
            $assigned = $this->getAssignedIncidents();
            if ($assigned) {
                $parts[] = $assigned;
            }
        }

        // User-related queries: extract name and fetch user data
        $userName = $this->extractUserName($message, $lower);
        if ($userName) {
            // "how did [name] solve/fix" — get resolved incidents with summaries
            if ($this->mentions($lower, ['how did', 'solve', 'fix', 'how.*resolve', 'how.*fix'])) {
                $resolved = $this->getUserResolvedIncidents($userName);
                if ($resolved) {
                    $parts[] = $resolved;
                }
            }

            // "how many.*[name]", "[name] resolved", "[name].*resolved" — activity summary
            if ($this->mentions($lower, ['how many', 'resolved', 'resolve', 'activity', 'performance', 'stats'])) {
                $summary = $this->getUserActivitySummary($userName);
                if ($summary) {
                    $parts[] = $summary;
                }
            }

            // "[name]'s incidents", "incidents for [name]", "assigned to [name]" — incidents by user
            if ($this->mentions($lower, ["{$userName}", 'incident', 'assigned', 'open', 'working'])) {
                $byUser = $this->getIncidentsByUser($userName);
                if ($byUser) {
                    $parts[] = $byUser;
                }
            }

            // Fallback: if user name detected but no specific intent, show full user data
            if (empty($parts)) {
                $byUser = $this->getIncidentsByUser($userName);
                if ($byUser) {
                    $parts[] = $byUser;
                }
                $summary = $this->getUserActivitySummary($userName);
                if ($summary) {
                    $parts[] = $summary;
                }
            }
        }

        // If no specific context matched but query mentions incident-related terms,
        // include general stats + recent incidents
        if (empty($parts) && $this->mentions($lower, ['incident', 'log', 'error'])) {
            $stats = $this->getSummaryStats();
            if ($stats) {
                $parts[] = $stats;
            }
            $recent = $this->getRecentIncidents();
            if ($recent) {
                $parts[] = $recent;
            }
        }

        return implode("\n\n", $parts);
    }

    private function mentions(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if (strpos($text, $term) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getSummaryStats(): ?string
    {
        $total = Incident::count();
        if ($total === 0) return null;

        $open = Incident::where('status', 'open')->count();
        $investigating = Incident::where('status', 'investigating')->count();
        $resolved = Incident::where('status', 'resolved')->count();

        return "Total incidents: {$total} | Open: {$open} | Investigating: {$investigating} | Resolved: {$resolved}";
    }

    private function getIncidentDetail(int $id): ?string
    {
        $incident = Incident::with(['server', 'assignedUser'])->find($id);
        if (!$incident) return null;

        $logs = $incident->logs()->orderBy('timestamp', 'desc')->limit(5)->get();
        $logText = $logs->map(fn($l) => "[{$l->log_level}] {$l->message}")->implode("\n");

        return "Incident #{$incident->id}:\n"
            . "Title: {$incident->title}\n"
            . "Type: {$incident->type}\n"
            . "Severity: {$incident->severity}\n"
            . "Status: {$incident->status}\n"
            . "Server: " . ($incident->server?->name ?? 'Unknown') . "\n"
            . "Assigned: " . ($incident->assignedUser?->name ?? 'Unassigned') . "\n"
            . "Created: {$incident->created_at}\n"
            . ($incident->summary ? "Summary: {$incident->summary}\n" : "")
            . "Recent logs:\n{$logText}";
    }

    private function getIncidentsByServer(): ?string
    {
        $rows = Incident::selectRaw('server_id, count(*) as cnt')
            ->groupBy('server_id')
            ->orderByDesc('cnt')
            ->get();

        if ($rows->isEmpty()) return null;

        $servers = Server::whereIn('id', $rows->pluck('server_id'))->get()->keyBy('id');
        $lines = $rows->map(function ($row) use ($servers) {
            $name = $servers[$row->server_id]?->name ?? 'Unknown';
            return "{$name}: {$row->cnt} incidents";
        })->implode("\n");

        return "Incidents by server:\n{$lines}";
    }

    private function getIncidentsByType(): ?string
    {
        $rows = Incident::selectRaw('type, count(*) as cnt')
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        if ($rows->isEmpty()) return null;

        $lines = $rows->map(fn($r) => "{$r->type}: {$r->cnt}")->implode("\n");
        return "Incidents by type:\n{$lines}";
    }

    private function getIncidentsBySeverity(): ?string
    {
        $rows = Incident::selectRaw('severity, count(*) as cnt')
            ->groupBy('severity')
            ->orderByDesc('cnt')
            ->get();

        if ($rows->isEmpty()) return null;

        $lines = $rows->map(fn($r) => "{$r->severity}: {$r->cnt}")->implode("\n");
        return "Incidents by severity:\n{$lines}";
    }

    private function getIncidentsByStatus(): ?string
    {
        $rows = Incident::selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->orderByDesc('cnt')
            ->get();

        if ($rows->isEmpty()) return null;

        $lines = $rows->map(fn($r) => "{$r->status}: {$r->cnt}")->implode("\n");
        $result = "Incidents by status:\n{$lines}";

        // Also include top 5 open incidents for listing queries
        $open = Incident::with('server')->where('status', 'open')->orderBy('created_at', 'desc')->limit(5)->get();
        if ($open->isNotEmpty()) {
            $openLines = $open->map(function ($i) {
                $name = $i->server ? $i->server->name : 'Unknown';
                return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$name})";
            })->implode("\n");
            $result .= "\n\nTop open incidents:\n{$openLines}";
        }

        return $result;
    }

    private function searchIncidents(string $message): ?string
    {
        $words = array_filter(preg_split('/\s+/', $message), fn($w) => strlen($w) > 3 && !in_array(strtolower($w), ['search', 'find', 'look', 'for', 'about', 'show', 'the', 'can', 'you', 'tell', 'me', 'what', 'is', 'are', 'which', 'has', 'have', 'most', 'many']));

        if (empty($words)) return null;

        $query = Incident::with('server')->orderBy('created_at', 'desc')->limit(10);

        foreach ($words as $word) {
            $query->where(function ($q) use ($word) {
                $q->where('title', 'ilike', "%{$word}%")
                    ->orWhere('summary', 'ilike', "%{$word}%")
                    ->orWhere('type', 'ilike', "%{$word}%");
            });
        }

        $incidents = $query->get();

        if ($incidents->isEmpty()) return null;

        $lines = $incidents->map(function ($i) {
            $serverName = $i->server ? $i->server->name : 'Unknown';
            return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$serverName})";
        })->implode("\n");
        return "Matching incidents:\n{$lines}";
    }

    private function getRecentIncidents(): ?string
    {
        $incidents = Incident::with('server')->orderBy('created_at', 'desc')->limit(10)->get();
        if ($incidents->isEmpty()) return null;

        $lines = $incidents->map(function ($i) {
            $serverName = $i->server ? $i->server->name : 'Unknown';
            return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$serverName})";
        })->implode("\n");
        return "Recent incidents (newest first):\n{$lines}";
    }

    private function listServers(): ?string
    {
        $servers = Server::orderBy('name')->get();
        if ($servers->isEmpty()) return null;

        $lines = $servers->map(fn($s) => "{$s->name} ({$s->environment}) - " . ($s->is_active ? 'active' : 'inactive'))->implode("\n");
        return "Servers:\n{$lines}";
    }

    private function getAssignedIncidents(): ?string
    {
        $incidents = Incident::with(['server', 'assignedUser'])
            ->whereNotNull('assigned_to')
            ->where('status', '!=', 'resolved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($incidents->isEmpty()) return null;

        $lines = $incidents->map(function ($i) {
            $serverName = $i->server ? $i->server->name : 'Unknown';
            $assignee = $i->assignedUser ? $i->assignedUser->name : 'Unassigned';
            return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$serverName}) → Assigned to: {$assignee}";
        })->implode("\n");

        $total = Incident::whereNotNull('assigned_to')->where('status', '!=', 'resolved')->count();
        return "Assigned incidents ({$total} total, not yet resolved):\n{$lines}";
    }

    private function extractUserName(string $message, string $lower): ?string
    {
        // Pattern: "assigned to John", "incidents for John Doe", "how did John fix"
        if (preg_match('/(?:assigned to|for|by|about|did)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/', $message, $m)) {
            return $this->findMatchingUser(trim($m[1]));
        }

        // Pattern: "John's incidents", "John resolved"
        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\'?s?\s+(?:incident|assign|resolv|work|fix|solve)/', $message, $m)) {
            return $this->findMatchingUser(trim($m[1]));
        }

        // Pattern: "incidents assigned to john" (lowercase name)
        if (preg_match('/(?:assigned to|for|by)\s+([a-z][a-z]+(?:\s+[a-z][a-z]+)?)/', $lower, $m)) {
            return $this->findMatchingUser(trim($m[1]));
        }

        // Pattern: "john's incidents" (lowercase)
        if (preg_match('/([a-z][a-z]+(?:\s+[a-z][a-z]+)?)\'?s?\s+(?:incident|assign|resolv|work|fix|solve)/', $lower, $m)) {
            return $this->findMatchingUser(trim($m[1]));
        }

        // Pattern: "how did john solve" (lowercase)
        if (preg_match('/(?:how did|what did)\s+([a-z][a-z]+(?:\s+[a-z][a-z]+)?)/', $lower, $m)) {
            return $this->findMatchingUser(trim($m[1]));
        }

        return null;
    }

    private function findMatchingUser(string $name): ?string
    {
        // Try exact match first (case-insensitive)
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($user) return $user->name;

        // Try partial match on first name
        $parts = explode(' ', $name);
        $firstName = $parts[0];
        $user = User::whereRaw('LOWER(name) LIKE ?', [strtolower($firstName) . '%'])->first();
        if ($user) return $user->name;

        // Try partial match on any part of the name
        foreach ($parts as $part) {
            $user = User::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($part) . '%'])->first();
            if ($user) return $user->name;
        }

        return null;
    }

    private function extractIncidentIdFromHistory(array $history): ?int
    {
        // Scan conversation history for incident references like "#123" or "incident 123"
        foreach (array_reverse($history) as $msg) {
            $content = $msg['content'] ?? '';
            // Match #NNN pattern
            if (preg_match('/#(\d+)/', $content, $m)) {
                return (int) $m[1];
            }
            // Match "incident NNN" pattern
            if (preg_match('/incident\s*#?(\d+)/', strtolower($content), $m)) {
                return (int) $m[1];
            }
        }
        return null;
    }

    private function getIncidentsByUser(string $userName): ?string
    {
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($userName)])->first();
        if (!$user) return null;

        $total = Incident::where('assigned_to', $user->id)->count();
        if ($total === 0) {
            return "{$userName} ({$user->role}): No incidents assigned.";
        }

        $open = Incident::where('assigned_to', $user->id)->where('status', 'open')->count();
        $investigating = Incident::where('assigned_to', $user->id)->where('status', 'investigating')->count();
        $resolved = Incident::where('assigned_to', $user->id)->where('status', 'resolved')->count();

        $result = "{$userName} ({$user->role}):\nTotal assigned: {$total} | Open: {$open} | Investigating: {$investigating} | Resolved: {$resolved}";

        // Open incidents
        $openIncidents = Incident::with('server')
            ->where('assigned_to', $user->id)
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($openIncidents->isNotEmpty()) {
            $openLines = $openIncidents->map(function ($i) {
                $serverName = $i->server ? $i->server->name : 'Unknown';
                return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$serverName})";
            })->implode("\n");
            $result .= "\n\nOpen incidents:\n{$openLines}";
        }

        // Investigating incidents
        $investigatingIncidents = Incident::with('server')
            ->where('assigned_to', $user->id)
            ->where('status', 'investigating')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($investigatingIncidents->isNotEmpty()) {
            $invLines = $investigatingIncidents->map(function ($i) {
                $serverName = $i->server ? $i->server->name : 'Unknown';
                return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$serverName})";
            })->implode("\n");
            $result .= "\n\nInvestigating:\n{$invLines}";
        }

        return $result;
    }

    private function getUserActivitySummary(string $userName): ?string
    {
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($userName)])->first();
        if (!$user) return null;

        $total = Incident::where('assigned_to', $user->id)->count();
        if ($total === 0) return null;

        $resolved = Incident::where('assigned_to', $user->id)->where('status', 'resolved')->count();
        $open = Incident::where('assigned_to', $user->id)->where('status', 'open')->count();
        $investigating = Incident::where('assigned_to', $user->id)->where('status', 'investigating')->count();

        // Calculate average resolution time if we have resolved incidents
        $avgTime = null;
        $resolvedWithTimes = Incident::where('assigned_to', $user->id)
            ->where('status', 'resolved')
            ->whereNotNull('updated_at')
            ->get();

        if ($resolvedWithTimes->isNotEmpty()) {
            $totalMinutes = 0;
            $count = 0;
            foreach ($resolvedWithTimes as $incident) {
                $minutes = $incident->created_at->diffInMinutes($incident->updated_at);
                if ($minutes > 0) {
                    $totalMinutes += $minutes;
                    $count++;
                }
            }
            if ($count > 0) {
                $avgTime = round($totalMinutes / $count);
            }
        }

        $result = "{$userName} ({$user->role}) — Activity Summary:\n"
            . "Total assigned: {$total}\n"
            . "Resolved: {$resolved}\n"
            . "Open: {$open}\n"
            . "Investigating: {$investigating}\n"
            . "Resolution rate: " . ($total > 0 ? round(($resolved / $total) * 100) : 0) . "%";

        if ($avgTime) {
            if ($avgTime >= 60) {
                $hours = round($avgTime / 60, 1);
                $result .= "\nAvg resolution time: {$hours} hours";
            } else {
                $result .= "\nAvg resolution time: {$avgTime} minutes";
            }
        }

        return $result;
    }

    private function getUserResolvedIncidents(string $userName): ?string
    {
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($userName)])->first();
        if (!$user) return null;

        $incidents = Incident::with('server')
            ->where('assigned_to', $user->id)
            ->where('status', 'resolved')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        if ($incidents->isEmpty()) return null;

        $lines = $incidents->map(function ($i) {
            $serverName = $i->server ? $i->server->name : 'Unknown';
            $summaryPart = $i->summary ? " — Summary: {$i->summary}" : '';
            return "#{$i->id} [{$i->severity}] STATUS: {$i->status} - {$i->title} ({$serverName}){$summaryPart}";
        })->implode("\n");

        $total = Incident::where('assigned_to', $user->id)->where('status', 'resolved')->count();
        return "{$userName} — Resolved incidents ({$total} total, showing latest 5):\n{$lines}";
    }

    private function callGemini(string $message, array $history, string $context): string
    {
        $systemPrompt = "You are a read-only assistant for an Incident Management System. "
            . "You answer questions about incidents, logs, servers, and users using the data provided below. "
            . "You can show, list, display, and summarize data freely. "
            . "You CANNOT perform write actions like assigning, resolving, creating, updating, or deleting records. "
            . "If asked to perform a write action, politely explain you are read-only. "
            . "Format responses with clear bullet points. Use the data provided — do not make things up. "
            . "If the data doesn't contain the answer, say so honestly. "
            . "When asked about a user, you can tell which incidents they are assigned to, their resolution rate, and how they solved past incidents. "
            . "IMPORTANT: When asked about an incident's status (resolved, open, fixed), ALWAYS use the 'Status:' field from the CURRENT STATUS DATA section. Never guess or assume. If no status data is provided, say you don't have that information.";

        $content = $context
            ? "Here is the current data from the system:\n\n{$context}\n\nUser question: {$message}"
            : "User question: {$message}";

        $messages = [];
        foreach ($history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = ['parts' => [['text' => $msg['content']]]];
            }
        }
        $messages[] = ['parts' => [['text' => $systemPrompt . "\n\n" . $content]]];

        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key={$this->apiKey}", [
                        'contents' => $messages,
                        'generationConfig' => [
                            'maxOutputTokens' => 500,
                            'temperature' => 0.3,
                        ],
                    ]);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if ($text) {
                        return $text;
                    }
                }

                if ($response->status() === 429) {
                    FacadeLog::warning('Gemini API quota exceeded', ['attempt' => $attempt]);
                }
            } catch (\Exception $e) {
                FacadeLog::error('Gemini API exception', ['error' => $e->getMessage()]);
            }

            if ($attempt < $maxRetries) {
                usleep(pow(2, $attempt) * 1000000);
            }
        }

        return "I'm sorry, I couldn't process your request right now. Please try again later.";
    }
}
