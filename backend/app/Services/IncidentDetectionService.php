<?php

namespace App\Services;

use App\Models\Log;

class IncidentDetectionService
{
    private array $severityMap = [];

    public function __construct()
    {
        $this->initializeSeverityMap();
    }

    private function initializeSeverityMap(): void
    {
        $this->severityMap = [
            'database' => 'critical',
            'system' => 'critical',
            'network' => 'high',
            'auth' => 'high',
            'container' => 'high',
            'cloud' => 'high',
            'nginx' => 'high',
            'apache' => 'high',
            'api' => 'medium',
            'queue' => 'medium',
            'file' => 'medium',
            'email' => 'medium',
            'general' => 'medium',
            'cache' => 'low',
        ];
    }

    public function analyze(Log $log): ?array
    {
        $source = $log->source ?? 'unknown';

        if ($source === 'unknown') {
            if ($log->log_level === 'error') {
                return [
                    'type' => 'general',
                    'severity' => 'medium',
                    'title' => $this->buildTitleFromMessage($log->message),
                ];
            }
            return null;
        }

        return [
            'type' => $source,
            'severity' => $this->getSeverityForSource($source),
            'title' => $this->buildTitleFromMessage($log->message),
        ];
    }

    public function getSeverityForSource(string $source): string
    {
        return $this->severityMap[$source] ?? 'medium';
    }

    private function buildTitleFromMessage(string $message): string
    {
        $message = trim($message);
        $cleaned = preg_replace('/^(ERROR|WARN|WARNING|INFO|DEBUG|CRITICAL|FATAL)\s*:\s*/i', '', $message);

        if (strlen($cleaned) > 80) {
            $cleaned = substr($cleaned, 0, 77) . '...';
        }

        return ucfirst($cleaned);
    }

    public function getSeverityMap(): array
    {
        return $this->severityMap;
    }
}