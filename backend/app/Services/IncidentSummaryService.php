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
        $this->provider = config('services.ai.provider', 'ollama');
        $this->apiKey = config('services.ai.api_key');
        $this->baseUrl = config('services.ai.base_url', 'http://localhost:11434');
    }

    public function generate(Incident $incident): ?string
    {
        $logs = $this->getRelevantLogs($incident);

        if ($logs->isEmpty()) {
            return null;
        }

        $prompt = $this->buildPrompt($incident, $logs);

        return match ($this->provider) {
            'openai' => $this->callOpenAI($prompt),
            'ollama' => $this->callOllama($prompt),
            default => null,
        };
    }

    private function getRelevantLogs(Incident $incident)
    {
        return $incident->logs()
            ->orderBy('timestamp', 'desc')
            ->limit(50)
            ->get();
    }

    private function buildPrompt(Incident $incident, $logs): string
    {
        $logList = $logs->map(function ($log) {
            return "[{$log->timestamp}] {$log->log_level}: {$log->message}";
        })->implode("\n");

        return <<<PROMPT
You are a DevOps engineer analyzing an incident. Provide a concise summary for engineers.

Incident Details:
- Title: {$incident->title}
- Type: {$incident->type}
- Severity: {$incident->severity}
- Status: {$incident->status}
- Server ID: {$incident->server_id}

Log History (most recent first):
{$logList}

Please provide a concise summary covering:
1. What happened (brief description)
2. Probable cause
3. System/component affected
4. Suggested next steps

Keep it under 200 words and practical for on-call engineers.
PROMPT;
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
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/api/generate", [
                    'model' => 'llama3.2',
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
}