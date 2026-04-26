<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentLog;
use App\Models\Log;
use App\Models\Server;
use App\Models\User;

class LogIngestionService
{
    public function __construct(
        private LogNormalizationService $normalizer,
        private IncidentDetectionService $detector,
        private IncidentGroupingService $grouper,
        private IncidentTimelineService $timeline
    ) {}

    public function ingest(Server $server, array|string $payload): Log
    {
        $normalized = $this->normalizer->normalize($payload);

        $log = Log::create([
            'server_id' => $server->id,
            'message' => $normalized['message'],
            'log_level' => $normalized['log_level'],
            'source' => $normalized['source'],
            'timestamp' => $normalized['timestamp'],
            'raw_payload' => $normalized['raw_payload'],
        ]);

        $this->processIncidentDetection($log, $server);

        return $log;
    }

    private function processIncidentDetection(Log $log, Server $server): void
    {
        $detection = $this->detector->analyze($log);

        if (!$detection) {
            return;
        }

        $existingIncident = $this->grouper->findExistingIncident($server, $detection['type']);

        if ($existingIncident) {
            $this->attachToExistingIncident($existingIncident, $log);
        } else {
            $this->createNewIncident($log, $server, $detection);
        }
    }

    private function attachToExistingIncident(Incident $incident, Log $log): void
    {
        IncidentLog::firstOrCreate(
            ['incident_id' => $incident->id, 'log_id' => $log->id]
        );
    }

    private function createNewIncident(Log $log, Server $server, array $detection): Incident
    {
        try {
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

            $this->timeline->logCreated($incident);

            User::query()->update(['unread_count' => \Illuminate\Support\Facades\DB::raw('unread_count + 1')]);

            return $incident;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create incident: ' . $e->getMessage());
            throw $e;
        }
    }
}