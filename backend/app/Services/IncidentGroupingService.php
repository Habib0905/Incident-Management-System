<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Server;

class IncidentGroupingService
{
    public function findExistingIncident(Server $server, string $type): ?Incident
    {
        return Incident::where('server_id', $server->id)
            ->where('type', $type)
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function shouldCreateNewIncident(Server $server, string $type): bool
    {
        return !$this->findExistingIncident($server, $type);
    }
}