<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SimilarityService
{
    private string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.similarity_api.url', 'http://127.0.0.1:8001');
    }

    public function buildIncidentText(Incident $incident): string
    {
        $text = $incident->title;

        $logs = $incident->logs()
            ->select('message', 'log_level')
            ->orderBy('timestamp')
            ->get();

        if ($logs->isEmpty()) {
            return $text;
        }

        $unique = [];
        foreach ($logs as $log) {
            $key = $log->message;
            if (!isset($unique[$key])) {
                $unique[$key] = $log->log_level;
            }
        }

        $uniqueLogs = array_values($unique);
        $first = array_slice($uniqueLogs, 0, 5);
        $last = array_slice($uniqueLogs, -5);
        $selected = array_unique(array_merge($first, $last));

        $logLines = [];
        foreach ($selected as $message) {
            $level = $unique[$message] ?? 'unknown';
            $logLines[] = "[{$level}] {$message}";
        }

        $text .= "\nLogs:\n" . implode("\n", $logLines);

        return $text;
    }

    public function computeEmbedding(string $text): array
    {
        $response = Http::timeout(30)
            ->post("{$this->apiUrl}/embed", [
                'text' => $text,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Similarity API error: {$response->status()}");
        }

        $embedding = $response->json('embedding');

        if (!$embedding || count($embedding) !== 384) {
            throw new \Exception('Invalid embedding response from similarity API');
        }

        return $embedding;
    }

    public function ensureEmbedding(Incident $incident): void
    {
        try {
            $text = $this->buildIncidentText($incident);
            $embedding = $this->computeEmbedding($text);

            $incident->update([
                'embedding' => $embedding,
                'last_embedded_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to compute embedding for incident #' . $incident->id . ': ' . $e->getMessage());
        }
    }

    public function findSimilarIncidents(Incident $incident, int $limit = 3): array
    {
        if (!$incident->embedding) {
            return [];
        }

        $embeddingStr = is_array($incident->embedding)
            ? '[' . implode(',', $incident->embedding) . ']'
            : $incident->embedding;

        $results = \Illuminate\Support\Facades\DB::select(
            "SELECT id, title, type, severity, assigned_to,
                    (1 - (embedding <=> ?::vector)) AS similarity
             FROM incidents
             WHERE status = 'resolved'
               AND id != ?
               AND embedding IS NOT NULL
               AND (embedding <=> ?::vector) < 0.75
             ORDER BY similarity DESC
             LIMIT ?",
            [$embeddingStr, $incident->id, $embeddingStr, $limit]
        );

        return array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'title' => $row->title,
                'type' => $row->type,
                'severity' => $row->severity,
                'assigned_to' => $row->assigned_to ? (int) $row->assigned_to : null,
                'similarity' => round((float) $row->similarity, 4),
            ];
        }, $results);
    }
}
