<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentView;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentViewService
{
    public function markAsViewed(Incident $incident, User $user): IncidentView
    {
        return IncidentView::firstOrCreate(
            [
                'incident_id' => $incident->id,
                'user_id' => $user->id,
            ],
            [
                'viewed_at' => now()->utc(),
            ]
        );
    }

    public function isViewed(Incident $incident, User $user): bool
    {
        return IncidentView::where('incident_id', $incident->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function getUnreadCount(User $user): int
    {
        return Incident::whereNotExists(function ($query) use ($user) {
            $query->select(DB::raw(1))
                  ->from('incident_views')
                  ->whereColumn('incident_views.incident_id', 'incidents.id')
                  ->where('incident_views.user_id', $user->id);
        })->count();
    }
}