<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Resources\AgentResource;
use Escalated\Laravel\Http\Resources\CannedResponseResource;
use Escalated\Laravel\Http\Resources\DepartmentResource;
use Escalated\Laravel\Http\Resources\MacroResource;
use Escalated\Laravel\Http\Resources\TagResource;
use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ApiResourceController extends Controller
{
    public function agents(): JsonResponse
    {
        $userModel = Escalated::newUserModel();
        $agentGate = config('escalated.authorization.agent_gate', 'escalated-agent');

        $query = $userModel->newQuery();

        // Use escalated.authorization.agent_scope if defined, otherwise fall back to Gate filter with a limit
        $agentScope = config('escalated.authorization.agent_scope');
        if ($agentScope && is_callable($agentScope)) {
            $agents = $agentScope($query)->get();
        } else {
            $agents = $query->limit(500)->get()->filter(function ($user) use ($agentGate) {
                return Gate::forUser($user)->allows($agentGate);
            })->values();
        }

        return response()->json(['data' => AgentResource::collection($agents)]);
    }

    public function departments(): JsonResponse
    {
        return response()->json(['data' => DepartmentResource::collection(Department::active()->get())]);
    }

    public function tags(): JsonResponse
    {
        return response()->json(['data' => TagResource::collection(Tag::all())]);
    }

    public function cannedResponses(Request $request): JsonResponse
    {
        $responses = CannedResponse::forAgent($request->user()->getKey())->get();

        return response()->json(['data' => CannedResponseResource::collection($responses)]);
    }

    public function macros(Request $request): JsonResponse
    {
        $macros = Macro::forAgent($request->user()->getKey())->orderBy('order')->get();

        return response()->json(['data' => MacroResource::collection($macros)]);
    }

    public function realtimeConfig(): JsonResponse
    {
        // Return WebSocket config if broadcasting is configured
        $driver = config('broadcasting.default');

        if (in_array($driver, ['reverb', 'pusher'])) {
            $config = config("broadcasting.connections.{$driver}");

            return response()->json([
                'driver' => $driver,
                'key' => $config['key'] ?? null,
                'host' => $driver === 'reverb'
                    ? ($config['options']['host'] ?? null)
                    : 'ws-'.($config['options']['cluster'] ?? 'mt1').'.pusher.com',
                'port' => $config['options']['port'] ?? ($driver === 'reverb' ? 8080 : 443),
                'scheme' => $config['options']['scheme'] ?? 'https',
                'cluster' => $config['options']['cluster'] ?? null,
            ]);
        }

        return response()->json(null);
    }
}
