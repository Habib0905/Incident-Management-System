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
use Illuminate\Support\Facades\DB;

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
            'type' => 'sometimes|in:database,auth,network,system,general,container,cloud,nginx,apache,api,queue,file,email,cache',
            'search' => 'sometimes|string',
            'created_after' => 'sometimes|date',
            'created_before' => 'sometimes|date',
        ]);

        $user = $request->user();

        $query = $this->filterService->filter($filters);
        
        $incidents = $query->leftJoin('incident_views', function ($join) use ($user) {
                $join->on('incidents.id', '=', 'incident_views.incident_id')
                     ->where('incident_views.user_id', $user->id);
            })
            ->select('incidents.*', 
                     DB::raw('CASE WHEN incident_views.incident_id IS NOT NULL THEN true ELSE false END as is_viewed'))
            ->limit(50)
            ->get();

        return response()->json(['incidents' => $incidents]);
    }

    public function myIncidents(Request $request)
    {
        $user = $request->user();
        $query = $this->filterService->getMyIncidents($user);
        
        $incidents = $query->leftJoin('incident_views', function ($join) use ($user) {
                $join->on('incidents.id', '=', 'incident_views.incident_id')
                     ->where('incident_views.user_id', $user->id);
            })
            ->select('incidents.*', 
                     DB::raw('CASE WHEN incident_views.incident_id IS NOT NULL THEN true ELSE false END as is_viewed'))
            ->limit(50)
            ->get();

        return response()->json(['incidents' => $incidents]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        return response()->json(['unread_count' => $user->unread_count ?? 0]);
    }

    public function show(Request $request, Incident $incident)
    {
        $user = $request->user();
        
        $incident->load(['server', 'creator', 'assignedUser']);

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
        $this->viewService->markAsViewed($incident, $user);

        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->where('unread_count', '>', 0)
            ->decrement('unread_count');

        $user->refresh();

        return response()->json([
            'message' => 'Marked as viewed',
            'unread_count' => $user->unread_count ?? 0,
        ]);
    }

    private function canUpdate(User $user, Incident $incident): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $incident->assigned_to === $user->id;
    }
}