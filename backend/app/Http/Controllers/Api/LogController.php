<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Server;
use App\Services\LogIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LogController extends Controller
{
    public function __construct(private LogIngestionService $ingestionService) {}

    public function store(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $server = Server::where('api_key', $token)
            ->where('is_active', true)
            ->first();

        if (!$server) {
            return response()->json(['error' => 'Invalid or inactive API key'], 401);
        }

        $contentType = $request->header('Content-Type', '');
        
        $payload = null;
        
        if (str_contains($contentType, 'application/json')) {
            $payload = $request->all();
        } else {
            $payload = $request->getContent();
        }

        if (empty($payload)) {
            return response()->json(['error' => 'No log data provided'], 400);
        }

        $log = $this->ingestionService->ingest($server, $payload);

        return response()->json([
            'message' => 'Log received',
            'log_id' => $log->id,
        ], 201);
    }

    public function index(Request $request)
    {
        $serverId = $request->query('server_id');
        $level = $request->query('level');
        $limit = $request->query('limit', 50);

        $query = Log::query()
            ->with('server:id,name')
            ->orderBy('created_at', 'desc');

        if ($serverId) {
            $query->where('server_id', $serverId);
        }

        if ($level) {
            $query->where('log_level', $level);
        }

        $logs = $query->limit(min($limit, 100))->get();

        return response()->json(['logs' => $logs]);
    }

    public function show(Log $log)
    {
        $log->load('server:id,name');

        return response()->json(['log' => $log]);
    }
}