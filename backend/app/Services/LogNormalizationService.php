<?php

namespace App\Services;

class LogNormalizationService
{
    public function normalize(array|string $payload): array
    {
        if (is_array($payload)) {
            return $this->normalizeJson($payload);
        }

        return $this->normalizeText($payload);
    }

    private function normalizeJson(array $data): array
    {
        return [
            'message' => $data['message'] ?? 'Unknown error',
            'log_level' => $this->normalizeLogLevel($data['level'] ?? null),
            'source' => $data['source'] ?? 'unknown',
            'timestamp' => $this->parseTimestamp($data['timestamp'] ?? null),
            'raw_payload' => $data,
        ];
    }

    private function normalizeText(string $text): array
    {
        $logLevel = $this->detectLogLevelFromText($text);
        $source = $this->detectSourceFromText($text);

        return [
            'message' => trim($text),
            'log_level' => $logLevel,
            'source' => $source,
            'timestamp' => now()->utc(),
            'raw_payload' => ['raw' => $text],
        ];
    }

    private function normalizeLogLevel(?string $level): ?string
    {
        if (!$level) {
            return null;
        }

        $level = strtolower($level);

        return match ($level) {
            'error', 'err' => 'error',
            'warning', 'warn' => 'warn',
            'info', 'information' => 'info',
            'debug', 'dbg' => 'debug',
            default => null,
        };
    }

    private function detectLogLevelFromText(string $text): ?string
    {
        $upperText = strtoupper($text);

        if (str_contains($upperText, 'CRITICAL')) {
            return 'error';
        }
        if (str_contains($upperText, 'ERROR') || str_contains($upperText, 'FAILED') || str_contains($upperText, 'FAILURE')) {
            return 'error';
        }
        if (str_contains($upperText, 'WARN')) {
            return 'warn';
        }
        if (str_contains($upperText, 'INFO')) {
            return 'info';
        }
        if (str_contains($upperText, 'DEBUG')) {
            return 'debug';
        }

        return 'info';
    }

    private function detectSourceFromText(string $text): string
    {
        $lowerText = strtolower($text);

        if (str_contains($lowerText, 'database') || str_contains($lowerText, 'db ') || str_contains($lowerText, 'sql')) {
            return 'database';
        }
        if (str_contains($lowerText, 'auth') || str_contains($lowerText, 'login') || str_contains($lowerText, 'token')) {
            return 'auth';
        }
        if (str_contains($lowerText, 'network') || str_contains($lowerText, 'connection') || str_contains($lowerText, 'timeout')) {
            return 'network';
        }
        if (str_contains($lowerText, 'memory') || str_contains($lowerText, 'cpu') || str_contains($lowerText, 'disk')) {
            return 'system';
        }

        return 'unknown';
    }

    private function parseTimestamp(?string $timestamp): \Carbon\Carbon
    {
        if (!$timestamp) {
            return now()->utc();
        }

        try {
            return \Carbon\Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return now()->utc();
        }
    }
}