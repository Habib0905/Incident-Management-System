<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatBotService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatBotService $chatBotService
    ) {}

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'sometimes|array|max:10',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:1000',
        ]);

        $message = $validated['message'];
        $history = $validated['history'] ?? [];

        $history = array_slice($history, -4);

        $response = $this->chatBotService->handle($message, $history);

        return response()->json([
            'response' => $response,
        ]);
    }
}
