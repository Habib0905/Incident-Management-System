<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function __construct(private ApiKeyService $apiKeyService) {}

    public function index(Request $request)
    {
        $servers = Server::with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['servers' => $servers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'environment' => 'required|in:production,staging,development',
        ]);

        $validated['api_key'] = $this->apiKeyService->generateKey();
        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = true;

        $server = Server::create($validated);

        return response()->json(['server' => $server, 'api_key' => $validated['api_key']], 201);
    }

    public function show(Server $server)
    {
        $server->load('creator:id,name');

        return response()->json(['server' => $server]);
    }

    public function update(Request $request, Server $server)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'environment' => 'sometimes|in:production,staging,development',
        ]);

        $server->update($validated);

        return response()->json(['server' => $server->fresh()]);
    }

    public function regenerateKey(Server $server)
    {
        $newKey = $this->apiKeyService->regenerateKey($server);

        return response()->json([
            'server' => $server->fresh(),
            'api_key' => $newKey,
        ]);
    }

    public function revokeKey(Server $server)
    {
        $this->apiKeyService->revokeKey($server);

        return response()->json(['server' => $server->fresh()]);
    }

    public function activateKey(Server $server)
    {
        $this->apiKeyService->activateKey($server);

        return response()->json(['server' => $server->fresh()]);
    }

    public function destroy(Server $server)
    {
        $server->delete();

        return response()->json(['message' => 'Server deleted']);
    }
}