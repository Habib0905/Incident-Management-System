<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as FacadeLog;

class IncidentSummaryService
{
    private ?string $provider;
    private ?string $apiKey;
    private ?string $baseUrl;

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'rule-based');
        $this->apiKey = config('services.ai.api_key');
        $this->baseUrl = config('services.ai.base_url', 'http://localhost:11434');
    }

    public function generate(Incident $incident): ?string
    {
        $logs = $this->getRelevantLogs($incident);

        if ($logs->isEmpty()) {
            return null;
        }

        return match ($this->provider) {
            'openai' => $this->callOpenAI($this->buildPrompt($incident, $logs)),
            'ollama' => $this->callOllama($this->buildPrompt($incident, $logs)),
            'groq' => $this->callGroq($this->buildPrompt($incident, $logs)),
            'gemini' => $this->callGemini($this->buildPrompt($incident, $logs)),
            default => $this->generateRuleBased($incident, $logs),
        };
    }

    private function getRelevantLogs(Incident $incident)
    {
        return $incident->logs()
            ->orderBy('timestamp', 'desc')
            ->limit(20)
            ->get();
    }

    private function buildPrompt(Incident $incident, $logs): string
    {
        $logList = $logs->map(function ($log) {
            return "[{$log->log_level}] {$log->message}";
        })->implode("\n");

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

    private function generateRuleBased(Incident $incident, $logs): string
    {
        $errorLogs = $logs->filter(fn($l) => in_array($l->log_level, ['error', 'critical', 'fatal']));
        $warnLogs = $logs->filter(fn($l) => $l->log_level === 'warn' || $l->log_level === 'warning');

        $whatHappened = $errorLogs->first()?->message ?? $logs->first()?->message ?? 'Unknown error occurred';
        $component = $this->getComponentName($incident->type);
        $severity = ucfirst($incident->severity);

        $summary = "**{$severity} {$component} Incident**\n\n";
        $summary .= "**What Happened:** {$whatHappened}\n\n";

        if ($errorLogs->count() > 1) {
            $summary .= "**Impact:** {$errorLogs->count()} error-level log entries detected.\n\n";
        }

        $summary .= "**Probable Cause:** ";
        $summary .= $this->getProbableCause($incident->type, $whatHappened) . "\n\n";

        $summary .= "**Suggested Next Steps:**\n";
        $summary .= $this->getNextSteps($incident->type);

        return $summary;
    }

    private function getComponentName(string $type): string
    {
        return match ($type) {
            'database' => 'Database',
            'auth' => 'Authentication',
            'network' => 'Network',
            'system' => 'System',
            'api' => 'API',
            'container' => 'Container',
            'cloud' => 'Cloud Infrastructure',
            'queue' => 'Message Queue',
            'nginx' => 'Nginx Web Server',
            'apache' => 'Apache Web Server',
            'file' => 'File System',
            'cache' => 'Cache',
            'email' => 'Email Service',
            default => 'Service',
        };
    }

    private function getProbableCause(string $type, string $message): string
    {
        $msg = strtolower($message);

        return match ($type) {
            'database' => match (true) {
                str_contains($msg, 'connection pool') => 'Database connection pool exhaustion - all available connections are in use.',
                str_contains($msg, 'deadlock') => 'Database deadlock - concurrent transactions are blocking each other.',
                str_contains($msg, 'replication') => 'Database replication lag or failure.',
                default => 'Database performance degradation or resource exhaustion.',
            },
            'auth' => match (true) {
                str_contains($msg, 'brute force') => 'Brute force attack detected - multiple failed login attempts from same source.',
                str_contains($msg, 'jwt') || str_contains($msg, 'token') => 'Authentication token validation failure - possible token expiration or tampering.',
                default => 'Authentication service malfunction or security breach attempt.',
            },
            'network' => match (true) {
                str_contains($msg, 'down') || str_contains($msg, 'interface') => 'Network interface failure or connectivity loss.',
                str_contains($msg, 'dns') => 'DNS resolution failure - domain names cannot be resolved.',
                default => 'Network connectivity issues affecting service communication.',
            },
            'system' => match (true) {
                str_contains($msg, 'memory') || str_contains($msg, 'oom') => 'System memory exhaustion - OOM killer terminating processes.',
                str_contains($msg, 'disk') => 'Disk space critically low - filesystem approaching capacity.',
                str_contains($msg, 'cpu') => 'CPU resource exhaustion - system overloaded.',
                default => 'System resource degradation affecting service stability.',
            },
            'api' => match (true) {
                str_contains($msg, 'rate limit') => 'API rate limiting triggered - client exceeding allowed request rate.',
                str_contains($msg, 'latency') || str_contains($msg, 'slow') => 'API performance degradation - response times exceeding thresholds.',
                default => 'API service disruption or performance issues.',
            },
            'container' => match (true) {
                str_contains($msg, 'crash') || str_contains($msg, 'oomkill') => 'Container crash due to resource limits or application error.',
                str_contains($msg, 'pending') => 'Container scheduling failure - insufficient cluster resources.',
                default => 'Container orchestration issues affecting service availability.',
            },
            'cloud' => match (true) {
                str_contains($msg, 'access denied') => 'Cloud service access failure - invalid credentials or permissions.',
                str_contains($msg, 'health check') => 'Cloud instance health check failure - instance may be degraded.',
                default => 'Cloud infrastructure service disruption.',
            },
            'queue' => match (true) {
                str_contains($msg, 'backed up') || str_contains($msg, 'pending') => 'Message queue backlog - consumers not processing messages fast enough.',
                str_contains($msg, 'stalled') => 'Queue worker failure - no heartbeat detected.',
                default => 'Message queue processing issues.',
            },
            'nginx' => match (true) {
                str_contains($msg, '502') => 'Nginx upstream failure - backend service unreachable.',
                str_contains($msg, 'segfault') || str_contains($msg, 'sigsegv') => 'Nginx worker crash - possible memory corruption or bug.',
                default => 'Nginx web server malfunction.',
            },
            'apache' => match (true) {
                str_contains($msg, 'segfault') => 'Apache module crash - possible memory corruption.',
                str_contains($msg, 'maxrequestworkers') => 'Apache connection limit reached - all worker slots occupied.',
                default => 'Apache web server performance degradation.',
            },
            'file' => match (true) {
                str_contains($msg, 'descriptor') => 'File descriptor limit reached - too many open files.',
                str_contains($msg, 'inode') => 'Filesystem inode exhaustion - no new files can be created.',
                default => 'File system resource limits exceeded.',
            },
            'cache' => match (true) {
                str_contains($msg, 'eviction') => 'Cache eviction rate high - cache memory under pressure.',
                str_contains($msg, 'timeout') => 'Cache service connectivity failure.',
                default => 'Cache service performance degradation.',
            },
            'email' => match (true) {
                str_contains($msg, 'smtp') => 'SMTP server connectivity failure.',
                str_contains($msg, 'rate limited') => 'Email sending rate limited by provider.',
                default => 'Email service delivery issues.',
            },
            default => 'Service malfunction requiring investigation.',
        };
    }

    private function getNextSteps(string $type): string
    {
        return match ($type) {
            'database' => "1. Check active connections: SHOW PROCESSLIST\n2. Review connection pool settings\n3. Identify and kill long-running queries\n4. Consider increasing max_connections",
            'auth' => "1. Review failed login attempts and source IPs\n2. Check token expiration settings\n3. Verify authentication service health\n4. Consider implementing IP-based rate limiting",
            'network' => "1. Check network interface status: ip link show\n2. Verify DNS resolution: dig/nslookup\n3. Test connectivity to affected endpoints\n4. Review firewall rules",
            'system' => "1. Check resource usage: top, df -h, free -m\n2. Review system logs: journalctl -xe\n3. Identify and restart affected processes\n4. Consider scaling resources",
            'api' => "1. Check API gateway logs\n2. Review rate limit configurations\n3. Monitor response times and error rates\n4. Scale API instances if needed",
            'container' => "1. Check container logs: kubectl logs\n2. Review resource limits and requests\n3. Check cluster node health\n4. Restart failed containers",
            'cloud' => "1. Verify cloud service status page\n2. Check IAM permissions and credentials\n3. Review cloud monitoring dashboards\n4. Contact cloud provider support if needed",
            'queue' => "1. Check queue depth and consumer status\n2. Restart stalled workers\n3. Review message processing rate\n4. Scale consumers if backlog persists",
            'nginx' => "1. Check upstream service health\n2. Review nginx error logs\n3. Test backend connectivity\n4. Restart nginx if workers crashed",
            'apache' => "1. Check Apache error logs\n2. Review MaxRequestWorkers setting\n3. Identify slow requests\n4. Consider switching to event MPM",
            'file' => "1. Check disk usage: df -h\n2. Clean up old logs and temp files\n3. Increase ulimit if needed\n4. Monitor file descriptor usage",
            'cache' => "1. Check cache memory usage\n2. Review eviction policies\n3. Verify cache connectivity\n4. Consider increasing cache size",
            'email' => "1. Check SMTP server status\n2. Verify email credentials\n3. Review sending rate limits\n4. Check email queue for stuck messages",
            default => "1. Review relevant service logs\n2. Check service health metrics\n3. Restart affected services\n4. Escalate if issue persists",
        };
    }

    private function callOpenAI(string $prompt): ?string
    {
        try {
            $response = Http::withHeader('Authorization', 'Bearer ' . $this->apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            FacadeLog::error('OpenAI API error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            FacadeLog::error('OpenAI API exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function callOllama(string $prompt): ?string
    {
        try {
            $response = Http::timeout(120)
                ->post("{$this->baseUrl}/api/generate", [
                    'model' => 'qwen2.5:0.5b',
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                return $response->json('response');
            }

            FacadeLog::error('Ollama API error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            FacadeLog::error('Ollama API exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function callGroq(string $prompt): ?string
    {
        try {
            $response = Http::withHeader('Authorization', 'Bearer ' . $this->apiKey)
                ->timeout(30)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            FacadeLog::error('Groq API error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            FacadeLog::error('Groq API exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function callGemini(string $prompt): ?string
    {
        try {
            $response = Http::timeout(30)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text');
            }

            FacadeLog::error('Gemini API error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            FacadeLog::error('Gemini API exception', ['error' => $e->getMessage()]);
        }

        return null;
    }
}