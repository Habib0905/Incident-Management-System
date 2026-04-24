#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Server;
use App\Models\Log;
use App\Models\Incident;
use App\Models\IncidentLog;

if ($argc < 2) {
    echo "Usage: php ingest_logs.php <log_file> [server_name]\n";
    echo "Example: php ingest_logs.php storage/logs/sample/production.log \"Production Web Server\"\n";
    exit(1);
}

$logFile = $argv[1];
$serverName = $argv[2] ?? 'Production Web Server';

if (!file_exists($logFile)) {
    echo "Error: Log file not found: $logFile\n";
    exit(1);
}

$server = Server::where('name', 'like', "%{$serverName}%")->first();
if (!$server) {
    echo "Error: Server not found: $serverName\n";
    echo "Available servers:\n";
    foreach (Server::all() as $s) {
        echo "  - {$s->name} (API key: {$s->api_key})\n";
    }
    exit(1);
}

echo "Ingesting logs from {$logFile} for server: {$server->name}\n";
echo "API Key: {$server->api_key}\n\n";

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$ingested = 0;
$createdIncidents = 0;
$attachedToIncidents = 0;

foreach ($lines as $line) {
    if (empty(trim($line))) continue;

    $parsed = parseLogLine($line);
    if (!$parsed) {
        echo "Skipping unparseable line: $line\n";
        continue;
    }

    $log = Log::create([
        'server_id' => $server->id,
        'message' => $parsed['message'],
        'log_level' => $parsed['level'],
        'source' => $parsed['source'],
        'timestamp' => $parsed['timestamp'],
        'raw_payload' => $line,
    ]);

    $incidentLinked = tryLinkToIncident($server, $log, $parsed);
    
    $ingested++;
    if ($incidentLinked === 'new') {
        $createdIncidents++;
    } elseif ($incidentLinked === 'attached') {
        $attachedToIncidents++;
    }

    echo "[{$parsed['level']}] Log #{$log->id}: " . substr($parsed['message'], 0, 60) . "... ";
    if ($incidentLinked === 'new') {
        echo "→ Created new incident!\n";
    } elseif ($incidentLinked === 'attached') {
        echo "→ Attached to existing incident\n";
    } else {
        echo "→ No incident linked\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total logs ingested: $ingested\n";
echo "Created incidents: $createdIncidents\n";
echo "Attached to existing incidents: {$attachedToIncidents}\n";

function parseLogLine(string $line): ?array {
    $pattern = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\s+\[(\w+)\]\s+\[(\w+)\]\s+\[([^\]]+)\]\s+(.*)$/';
    
    if (preg_match($pattern, $line, $matches)) {
        return [
            'timestamp' => $matches[1],
            'level' => strtolower($matches[2]),
            'source' => $matches[3],
            'component' => $matches[4],
            'message' => $matches[5],
        ];
    }

    if (preg_match('/^\[(\w+)\]\s+\[(\w+)\]\s+\[([^\]]+)\]\s+(.*)$/', $line, $matches)) {
        return [
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            'level' => strtolower($matches[1]),
            'source' => $matches[2],
            'component' => $matches[3],
            'message' => $matches[4],
        ];
    }

    return [
        'timestamp' => date('Y-m-d\TH:i:s\Z'),
        'level' => 'info',
        'source' => 'unknown',
        'component' => 'unknown',
        'message' => $line,
    ];
}

function tryLinkToIncident($server, $log, array $parsed): string|false {
    $detection = detectIncidentType($parsed);
    
    if (!$detection) {
        return false;
    }

    $incident = findExistingIncident($server, $detection['type']);
    
    if ($incident) {
        IncidentLog::firstOrCreate([
            'incident_id' => $incident->id,
            'log_id' => $log->id,
        ]);
        return 'attached';
    }

    $incident = Incident::create([
        'server_id' => $server->id,
        'created_by' => null,
        'assigned_to' => null,
        'title' => $detection['title'],
        'type' => $detection['type'],
        'severity' => $detection['severity'],
        'status' => 'open',
        'summary' => null,
    ]);

    IncidentLog::create([
        'incident_id' => $incident->id,
        'log_id' => $log->id,
    ]);

    return 'new';
}

function detectIncidentType(array $parsed): ?array {
    $level = $parsed['level'];
    $message = strtolower($parsed['message']);
    $source = strtolower($parsed['source']);
    $component = strtolower($parsed['component']);

    if ($level === 'error') {
        if (str_contains($message, 'database') || str_contains($message, 'connection') || str_contains($message, 'postgres') || str_contains($message, 'mysql')) {
            return [
                'type' => 'database',
                'severity' => 'critical',
                'title' => "Database connection issue on {$parsed['component']}",
            ];
        }

        if (str_contains($message, 'auth') || str_contains($message, 'login') || str_contains($message, 'authentication')) {
            return [
                'type' => 'auth',
                'severity' => 'high',
                'title' => "Authentication error on {$parsed['component']}",
            ];
        }

        if (str_contains($message, 'network') || str_contains($message, 'connection refused') || str_contains($message, 'timeout')) {
            return [
                'type' => 'network',
                'severity' => 'high',
                'title' => "Network connectivity issue on {$parsed['component']}",
            ];
        }

        return [
            'type' => 'system',
            'severity' => 'medium',
            'title' => "System error on {$parsed['component']}: " . substr($parsed['message'], 0, 50),
        ];
    }

    if ($level === 'warn') {
        if (str_contains($message, 'failover')) {
            return [
                'type' => 'database',
                'severity' => 'high',
                'title' => "Database failover on {$parsed['component']}",
            ];
        }

        if (str_contains($message, 'memory')) {
            return [
                'type' => 'system',
                'severity' => 'medium',
                'title' => "Memory warning on {$parsed['component']}",
            ];
        }
    }

    return null;
}

function findExistingIncident($server, string $type): ?Incident {
    return Incident::where('server_id', $server->id)
        ->where('type', $type)
        ->where('status', '!=', 'resolved')
        ->orderBy('created_at', 'desc')
        ->first();
}