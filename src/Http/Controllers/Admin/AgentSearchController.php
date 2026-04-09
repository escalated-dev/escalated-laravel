<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Services\MentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AgentSearchController extends Controller
{
    public function __invoke(Request $request, MentionService $mentionService): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $agents = $mentionService->getAgentSuggestions($query);

        return response()->json($agents);
    }
}
