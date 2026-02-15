<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class AuthController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $user = $request->user();
        $apiToken = $request->attributes->get('api_token');

        return response()->json([
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
            'abilities' => $apiToken->abilities ?? [],
            'is_agent' => Gate::forUser($user)->allows('escalated-agent'),
            'is_admin' => Gate::forUser($user)->allows('escalated-admin'),
            'token_name' => $apiToken->name,
            'expires_at' => $apiToken->expires_at?->toIso8601String(),
        ]);
    }
}
