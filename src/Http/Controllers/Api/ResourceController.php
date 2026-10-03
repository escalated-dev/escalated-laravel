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
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResourceController extends Controller
{
    public function agents(): JsonResponse
    {
        $userModel = Escalated::newUserModel();
        $query = Escalated::userQuery();

        // Use escalated.authorization.agent_scope if defined, otherwise fall back to Gate filter with a limit
        $agentScope = config('escalated.authorization.agent_scope');
        if ($agentScope && is_callable($agentScope)) {
            $agents = $agentScope($query)->get();
            // A host scope is not account-specific; tenant mode also needs a seat.
            if (app(TenantContext::class)->enabled()) {
                $agents = $agents->filter(fn ($user) => StaffAccess::isAgent($user))->values();
            }
        } else {
            $agents = $query->limit(500)->get()->filter(fn ($user) => StaffAccess::isAgent($user))->values();
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
                'channel_prefix' => TenantBroadcast::prefix(),
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
