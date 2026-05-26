<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Resources\MobileUserResource;
use Escalated\Laravel\Models\ApiToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $userModel = Escalated::userModel();
        /** @var Model|null $user */
        $user = $userModel::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], (string) $user->getAttribute('password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return $this->tokenResponse($user, 'Mobile Login');
    }

    public function register(Request $request): JsonResponse
    {
        $userModel = Escalated::userModel();
        $userTable = (new $userModel)->getTable();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique($userTable, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var Model $user */
        $user = new $userModel;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->save();

        return $this->tokenResponse($user, 'Mobile Registration', 201);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $apiToken */
        $apiToken = $request->attributes->get('api_token');
        $apiToken?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var ApiToken $apiToken */
        $apiToken = $request->attributes->get('api_token');
        $user = $request->user();

        $abilities = $apiToken->abilities ?? ['customer'];
        $apiToken->delete();

        return $this->tokenResponse($user, 'Mobile Refresh', 200, $abilities);
    }

    public function validateToken(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new MobileUserResource($request->user()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new MobileUserResource($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $userTable = $user->getTable();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique($userTable, 'email')->ignore($user->getKey())],
        ]);

        $user->fill($validated);
        $user->save();

        return response()->json([
            'data' => new MobileUserResource($user->fresh()),
        ]);
    }

    protected function tokenResponse(mixed $user, string $tokenName, int $status = 200, array $abilities = ['customer']): JsonResponse
    {
        $expiryDays = config('escalated.api.token_expiry_days');
        $expiresAt = is_numeric($expiryDays) ? now()->addDays((int) $expiryDays) : null;
        $token = ApiToken::createToken($user, $tokenName, $abilities, $expiresAt);

        return response()->json([
            'token' => $token['plainTextToken'],
            'user' => new MobileUserResource($user),
            'data' => [
                'token' => $token['plainTextToken'],
                'user' => new MobileUserResource($user),
            ],
        ], $status);
    }
}
