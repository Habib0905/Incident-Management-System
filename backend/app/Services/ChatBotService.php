<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Server;
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

        $context = $this->buildContext($message, $lower);

        return $this->callGemini($message, $history, $context);
    }

    private function helpResponse(): string
    {
        return "I can help you query the Incident Management System. Just ask me anything about:\n\n"
            . "• **Incidents** — counts, breakdowns, specific incidents by ID\n"
            . "• **Logs** — recent logs, logs for a specific incident\n"
            . "• **Servers** — server list, incidents per server\n"
            . "• **Status & Severity** — open/critical/high/medium/low incidents\n\n"
            . "Examples:\n"
            . "• \"Which server has the most incidents?\"\n"
            . "• \"Show me open incidents\"\n"
            . "• \"Incident #123\"\n"
            . "• \"What's the breakdown by severity?\"";
    }

    private function buildContext(string $message, string $lower): string
    {
        $parts = [];

        // Always include summary stats for any incident-related query
        if ($this->mentions($lower, ['incident', 'log', 'server', 'error', 'critical', 'open', 'resolved', 'severity', 'status', 'breakdown', 'count', 'how many', 'most', 'which', 'per'])) {
            $stats = $this->getSummaryStats();
            if ($stats) {
                $parts[] = $stats;
            }
        }

        // Incident by ID
        if (preg_match('/incident\s*#?(\d+)/', $lower, $m)) {
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
                return "#{$i->id} [{$i->severity}] {$i->title} ({$name})";
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
            return "#{$i->id} [{$i->severity}] {$i->status} - {$i->title} ({$serverName})";
        })->implode("\n");
        return "Matching incidents:\n{$lines}";
    }

    private function getRecentIncidents(): ?string
    {
        $incidents = Incident::with('server')->orderBy('created_at', 'desc')->limit(10)->get();
        if ($incidents->isEmpty()) return null;

        $lines = $incidents->map(function ($i) {
            $serverName = $i->server ? $i->server->name : 'Unknown';
            return "#{$i->id} [{$i->severity}] {$i->status} - {$i->title} ({$serverName})";
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

    private function callGemini(string $message, array $history, string $context): string
    {
        $systemPrompt = "You are a read-only assistant for an Incident Management System. "
            . "You answer questions about incidents, logs, and servers using the data provided below. "
            . "You can show, list, display, and summarize data freely. "
            . "You CANNOT perform write actions like assigning, resolving, creating, updating, or deleting records. "
            . "If asked to perform a write action, politely explain you are read-only. "
            . "Format responses with clear bullet points. Use the data provided — do not make things up. "
            . "If the data doesn't contain the answer, say so honestly.";

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
