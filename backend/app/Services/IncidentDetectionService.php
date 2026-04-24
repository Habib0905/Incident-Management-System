<?php

namespace App\Services;

use App\Models\Log;

class IncidentDetectionService
{
    private array $rules = [];

    public function __construct()
    {
        $this->initializeRules();
    }

    private function initializeRules(): void
    {
        $this->rules = [
            [
                'patterns' => [
                    'database connection failed',
                    'connection refused',
                    'could not connect to database',
                    'database unavailable',
                    'sqlstate',
                ],
                'type' => 'database',
                'severity' => 'critical',
                'title_template' => 'Database connection issue detected',
            ],
            [
                'patterns' => [
                    'service unavailable',
                    '503',
                    'http 503',
                    'service down',
                    'not reachable',
                ],
                'type' => 'network',
                'severity' => 'high',
                'title_template' => 'Service unavailable',
            ],
            [
                'patterns' => [
                    'connection timeout',
                    'request timeout',
                    'operation timed out',
                    'timeout exceeded',
                ],
                'type' => 'network',
                'severity' => 'medium',
                'title_template' => 'Timeout detected',
            ],
            [
                'patterns' => [
                    'retry failure',
                    'max retries exceeded',
                    'retries exhausted',
                    'attempt failed',
                ],
                'type' => 'system',
                'severity' => 'low',
                'title_template' => 'Retry operation failed',
            ],
            [
                'patterns' => [
                    'authentication failed',
                    'auth failed',
                    'invalid credentials',
                    'unauthorized',
                    'access denied',
                ],
                'type' => 'auth',
                'severity' => 'high',
                'title_template' => 'Authentication failure',
            ],
            [
                'patterns' => [
                    'permission denied',
                    'forbidden',
                    'access denied',
                ],
                'type' => 'auth',
                'severity' => 'medium',
                'title_template' => 'Permission denied',
            ],
            [
                'patterns' => [
                    'out of memory',
                    'memory exhausted',
                    'oom',
                    'memory limit',
                ],
                'type' => 'system',
                'severity' => 'critical',
                'title_template' => 'Memory exhaustion',
            ],
            [
                'patterns' => [
                    'disk full',
                    'no space left',
                    'storage full',
                ],
                'type' => 'system',
                'severity' => 'high',
                'title_template' => 'Storage issue',
            ],
            [
                'patterns' => [
                    'panic',
                    'kernel panic',
                    'fatal error',
                    'segmentation fault',
                ],
                'type' => 'system',
                'severity' => 'critical',
                'title_template' => 'Critical system error',
            ],
            [
                'patterns' => [
                    'warning',
                    'warn',
                    'deprecation',
                ],
                'type' => 'general',
                'severity' => 'low',
                'title_template' => 'Warning detected',
            ],
        ];
    }

    public function analyze(Log $log): ?array
    {
        $message = strtolower($log->message);

        foreach ($this->rules as $rule) {
            foreach ($rule['patterns'] as $pattern) {
                if (str_contains($message, strtolower($pattern))) {
                    return [
                        'type' => $rule['type'],
                        'severity' => $rule['severity'],
                        'title' => $this->buildTitle($rule['title_template'], $log),
                    ];
                }
            }
        }

        if ($log->log_level === 'error') {
            return [
                'type' => 'general',
                'severity' => 'medium',
                'title' => 'Error: ' . substr($log->message, 0, 100),
            ];
        }

        return null;
    }

    private function buildTitle(string $template, Log $log): string
    {
        $title = $template;
        
        if ($log->source && $log->source !== 'unknown') {
            $title .= ' on ' . $log->source;
        }

        return $title;
    }
}