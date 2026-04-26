<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class IncidentFilterService
{
    public function filter(?array $filters): Builder
    {
        $query = Incident::query()
            ->with(['server', 'assignedUser', 'creator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['server_id'])) {
            $query->where('server_id', $filters['server_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['environment'])) {
            $query->whereHas('server', function ($q) use ($filters) {
                $q->where('environment', $filters['environment']);
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('summary', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (!empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        $query->orderBy('created_at', 'desc');

        return $query;
    }

    public function getMyIncidents(User $user): Builder
    {
        return Incident::query()
            ->with(['server', 'assignedUser', 'creator'])
            ->where('assigned_to', $user->id)
            ->orderBy('created_at', 'desc');
    }

    public function filterPaginated(?array $filters, int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->filter($filters);

        return $query->paginate($perPage);
    }
}