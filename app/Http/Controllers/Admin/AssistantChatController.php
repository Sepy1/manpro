<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Assistant\OpenAiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantChatController extends Controller
{
    public function chat(Request $request, OpenAiAssistantService $assistant): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'messages' => ['required', 'array', 'max:40'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $openAiMessages = [];
        foreach ($validated['messages'] as $msg) {
            $openAiMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        $result = $assistant->chat($openAiMessages);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }
}
