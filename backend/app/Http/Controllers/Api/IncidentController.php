<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityTimeline;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentFilterService;
use App\Services\IncidentSummaryService;
use App\Services\IncidentTimelineService;
use App\Services\IncidentViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    private const LOGS_PER_PAGE = 50;

    public function __construct(
        private IncidentFilterService $filterService,
        private IncidentTimelineService $timelineService,
        private IncidentSummaryService $summaryService,
        private IncidentViewService $viewService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => 'sometimes|in:open,investigating,resolved',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'server_id' => 'sometimes|exists:servers,id',
            'environment' => 'sometimes|in:production,staging,development',
            'assigned_to' => 'sometimes|exists:users,id',
            'type' => 'sometimes|in:database,auth,network,system,general',
            'search' => 'sometimes|string',
            'created_after' => 'sometimes|date',
            'created_before' => 'sometimes|date',
        ]);

        $query = $this->filterService->filter($filters);
        
        $user = $request->user();
        $viewedIds = $user->viewedIncidents()->pluck('incidents.id');

        $incidents = $query->get()->map(function ($incident) use ($viewedIds) {
            $incident->is_viewed = $viewedIds->contains($incident->id);
            return $incident;
        });

        return response()->json(['incidents' => $incidents]);
    }

    public function myIncidents(Request $request)
    {
        $user = $request->user();
        $query = $this->filterService->getMyIncidents($user);
        
        $viewedIds = $user->viewedIncidents()->pluck('incidents.id');

        $incidents = $query->get()->map(function ($incident) use ($viewedIds) {
            $incident->is_viewed = $viewedIds->contains($incident->id);
            return $incident;
        });

        return response()->json(['incidents' => $incidents]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = $user->cached_unread_count ?? 0;

        return response()->json(['unread_count' => $count]);
    }

    public function show(Request $request, Incident $incident)
    {
        $user = $request->user();
        
        $incident->load(['server', 'creator', 'assignedUser', 'activityLogs.user']);
        
        $isViewed = $this->viewService->isViewed($incident, $user);
        $incident->is_viewed = $isViewed;

        $logsPage = (int) $request->get('logs_page', 1);
        $logsPerPage = (int) $request->get('logs_per_page', self::LOGS_PER_PAGE);
        $logsPerPage = min($logsPerPage, 100);

        $logsQuery = $incident->logs()->orderBy('timestamp', 'desc');
        $totalLogs = $logsQuery->count();
        $logs = $logsQuery->skip(($logsPage - 1) * $logsPerPage)->take($logsPerPage)->get();

        return response()->json([
            'incident' => $incident,
            'logs' => $logs,
            'logs_pagination' => [
                'current_page' => $logsPage,
                'per_page' => $logsPerPage,
                'total' => $totalLogs,
                'total_pages' => ceil($totalLogs / $logsPerPage),
            ],
        ]);
    }

    public function update(Request $request, Incident $incident)
    {
        $user = $request->user();

        if (!$this->canUpdate($user, $incident)) {
            return response()->json(['error' => 'Unauthorized to update this incident'], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:open,investigating,resolved',
            'summary' => 'nullable|string',
        ]);

        $oldStatus = $incident->status;

        if (!empty($validated['status'])) {
            $incident->status = $validated['status'];
        }

        if (!empty($validated['summary'])) {
            $incident->summary = $validated['summary'];
        }

        $incident->save();

        if (!empty($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->timelineService->logStatusChanged($incident, $user, $oldStatus, $validated['status']);
        }

        return response()->json(['incident' => $incident->fresh()->load('server', 'creator', 'assignedUser')]);
    }

    public function assign(Request $request, Incident $incident)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['error' => 'Only admins can assign incidents'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $assignee = User::findOrFail($validated['assigned_to']);

        if (!$assignee->isEngineer() && !$assignee->isAdmin()) {
            return response()->json(['error' => 'Can only assign to engineers or admins'], 400);
        }

        $incident->assigned_to = $validated['assigned_to'];
        $incident->save();

        $this->timelineService->logAssigned($incident, $request->user(), $validated['assigned_to']);

        return response()->json(['incident' => $incident->fresh()->load('assignedUser')]);
    }

    public function addNote(Request $request, Incident $incident)
    {
        $user = $request->user();

        if (!$this->canUpdate($user, $incident)) {
            return response()->json(['error' => 'Unauthorized to add notes to this incident'], 403);
        }

        $validated = $request->validate([
            'note' => 'required|string',
        ]);

        $this->timelineService->logNoteAdded($incident, $user, $validated['note']);

        return response()->json(['message' => 'Note added']);
    }

    public function timeline(Incident $incident)
    {
        $timeline = ActivityTimeline::where('incident_id', $incident->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['timeline' => $timeline]);
    }

    public function generateSummary(Request $request, Incident $incident)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $incident->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized to generate summary'], 403);
        }

        $summary = $this->summaryService->generate($incident);

        if (!$summary) {
            return response()->json(['error' => 'Failed to generate summary'], 500);
        }

        $incident->summary = $summary;
        $incident->save();

        $this->timelineService->logSummaryGenerated($incident, $user);

        return response()->json([
            'incident_id' => $incident->id,
            'summary' => $summary,
            'saved' => true,
        ]);
    }

    public function view(Request $request, Incident $incident)
    {
        $user = $request->user();
        
        $wasNewView = !$this->viewService->isViewed($incident, $user);
        
        $this->viewService->markAsViewed($incident, $user);
        
        if ($wasNewView) {
            $user->decrement('cached_unread_count');
        }

        return response()->json(['message' => 'Marked as viewed']);
    }

    private function canUpdate(User $user, Incident $incident): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $incident->assigned_to === $user->id;
    }
}