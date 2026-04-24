<?php

namespace App\Services;

use App\Models\ActivityTimeline;
use App\Models\Incident;
use App\Models\User;

class IncidentTimelineService
{
    public function logCreated(Incident $incident): ActivityTimeline
    {
        return $this->createEvent($incident, null, 'created', 'Incident created');
    }

    public function logAssigned(Incident $incident, User $assignedBy, int $assigneeId): ActivityTimeline
    {
        $assignee = User::find($assigneeId);
        $note = "Assigned to: " . ($assignee ? $assignee->name : "User ID: {$assigneeId}");
        return $this->createEvent($incident, $assignedBy, 'assigned', $note);
    }

    public function logStatusChanged(Incident $incident, User $user, string $oldStatus, string $newStatus): ActivityTimeline
    {
        $note = "Status changed from {$oldStatus} to {$newStatus}";
        return $this->createEvent($incident, $user, 'status_changed', $note);
    }

    public function logNoteAdded(Incident $incident, User $user, string $note): ActivityTimeline
    {
        return $this->createEvent($incident, $user, 'note_added', $note);
    }

    public function logSummaryGenerated(Incident $incident, User $user): ActivityTimeline
    {
        return $this->createEvent($incident, $user, 'summary_generated', 'AI summary generated');
    }

    private function createEvent(Incident $incident, ?User $user, string $eventType, string $note = null): ActivityTimeline
    {
        return ActivityTimeline::create([
            'incident_id' => $incident->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'note' => $note,
            'created_at' => now()->utc(),
        ]);
    }
}