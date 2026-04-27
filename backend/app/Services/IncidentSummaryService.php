<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as FacadeLog;

class IncidentSummaryService
{
    private ?string $provider;
    private ?string $apiKey;

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'gemini');
        $this->apiKey = config('services.ai.api_key');
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
            'groq' => $this->callGroq($prompt),
            'gemini' => $this->callGemini($prompt),
            default => $this->callGemini($prompt),
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
        $baseUrl = config('services.ai.base_url', 'http://localhost:11434');

        try {
            $response = Http::timeout(120)
                ->post("{$baseUrl}/api/generate", [
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
        $maxRetries = 5;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key={$this->apiKey}", [
                        'contents' => [
                            ['parts' => [['text' => $prompt]]],
                        ],
                    ]);

                if ($response->successful()) {
                    return $response->json('candidates.0.content.parts.0.text');
                }

                if ($response->status() === 429) {
                    FacadeLog::warning('Gemini API quota exceeded', ['attempt' => $attempt]);
                } else {
                    FacadeLog::error('Gemini API error', [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]);
                }
            } catch (\Exception $e) {
                FacadeLog::error('Gemini API exception', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < $maxRetries) {
                usleep(pow(2, $attempt) * 1000000);
            }
        }

        return null;
    }
}
