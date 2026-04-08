<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\ChatRoutingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatRoutingRuleController extends Controller
{
    /**
     * List all routing rules.
     */
    public function index(): JsonResponse
    {
        $rules = ChatRoutingRule::ordered()->with('department')->get();

        return response()->json(['rules' => $rules]);
    }

    /**
     * Create a new routing rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'routing_strategy' => ['required', 'string', 'in:auto_assign,round_robin,skill_based,least_busy,manual_queue'],
            'offline_behavior' => ['required', 'string', 'in:queue,ticket_fallback,offline_form,hide_chat'],
            'max_queue_size' => ['integer', 'min:1'],
            'max_concurrent_per_agent' => ['integer', 'min:1'],
            'auto_close_after_minutes' => ['integer', 'min:1'],
            'queue_message' => ['nullable', 'string', 'max:1000'],
            'offline_message' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'position' => ['integer', 'min:0'],
        ]);

        $rule = ChatRoutingRule::create($validated);

        return response()->json(['message' => 'Routing rule created.', 'rule' => $rule], 201);
    }

    /**
     * Update an existing routing rule.
     */
    public function update(ChatRoutingRule $routingRule, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'routing_strategy' => ['string', 'in:auto_assign,round_robin,skill_based,least_busy,manual_queue'],
            'offline_behavior' => ['string', 'in:queue,ticket_fallback,offline_form,hide_chat'],
            'max_queue_size' => ['integer', 'min:1'],
            'max_concurrent_per_agent' => ['integer', 'min:1'],
            'auto_close_after_minutes' => ['integer', 'min:1'],
            'queue_message' => ['nullable', 'string', 'max:1000'],
            'offline_message' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'position' => ['integer', 'min:0'],
        ]);

        $routingRule->update($validated);

        return response()->json(['message' => 'Routing rule updated.', 'rule' => $routingRule->fresh()]);
    }

    /**
     * Delete a routing rule.
     */
    public function destroy(ChatRoutingRule $routingRule): JsonResponse
    {
        $routingRule->delete();

        return response()->json(['message' => 'Routing rule deleted.']);
    }
}
