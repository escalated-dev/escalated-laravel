<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\ApiTokenCode;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use PDOException;

/**
 * Browser extension sign-in: trade a one-time code from the connect page and
 * its PKCE verifier for an agent token (staff API contract v1, Exchange rules).
 */
class ExtensionAuthController extends Controller
{
    use DetectsConcurrencyErrors;

    /** RFC 7636 code_verifier: 43 to 128 unreserved characters. */
    protected const VERIFIER_PATTERN = '/\A[A-Za-z0-9\-._~]{43,128}\z/';

    public function exchange(Request $request): JsonResponse
    {
        // Validation runs before any code lookup and so never consumes a code.
        $request->headers->set('Accept', 'application/json');
        $data = Validator::make($this->rawInput($request), [
            'code' => ['required', 'string'],
            'code_verifier' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
        ])->validate();

        $minted = null;

        try {
            if (! preg_match(self::VERIFIER_PATTERN, $data['code_verifier'])) {
                // Still present the code so a malformed attempt burns it too.
                ApiTokenCode::consume($data['code'], $data['code_verifier'], $data['redirect_uri']);

                return $this->invalidCode();
            }

            $row = ApiTokenCode::consume(
                $data['code'],
                $data['code_verifier'],
                $data['redirect_uri'],
                function (ApiTokenCode $code) use (&$minted): ?ApiToken {
                    $user = $code->tokenable;

                    if (! $user instanceof Model || ! StaffAccess::isAgent($user)) {
                        return null;
                    }

                    $result = ApiToken::createToken(
                        $user,
                        $code->client_name !== null && $code->client_name !== ''
                            ? 'Browser extension ('.$code->client_name.')'
                            : 'Browser extension',
                        ['agent'],
                        $this->expiresAt(),
                        'browser_extension',
                        $code->extension_id,
                    );
                    $minted = [$result['plainTextToken'], $result['token'], $user];

                    return $result['token'];
                },
            );
        } catch (PDOException $e) {
            // A concurrent exchange of the same code that loses on a lock or a
            // serialization failure is just another used code. QueryException
            // extends PDOException.
            if ($this->causedByConcurrencyError($e)) {
                return $this->invalidCode();
            }

            throw $e;
        }

        if ($row === null || $minted === null) {
            return $this->invalidCode();
        }

        [$plainText, $token, $user] = $minted;

        return response()->json([
            'token' => $plainText,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * The body as sent. The redirect_uri is compared with no normalisation and
     * a malformed verifier must stay malformed, so a JSON body is read before
     * the host's TrimStrings / ConvertEmptyStringsToNull middleware touch it.
     *
     * @return array<string, mixed>
     */
    protected function rawInput(Request $request): array
    {
        if (! $request->isJson()) {
            return $request->post();
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function expiresAt(): ?\DateTimeInterface
    {
        $days = config('escalated.api.extension_token_expiry_days');

        return $days ? now()->addDays((int) $days) : null;
    }

    /** The single answer for every failure that involves a code. */
    protected function invalidCode(): JsonResponse
    {
        return response()->json(['message' => 'Invalid or expired code.'], 400)
            ->header('Cache-Control', 'no-store');
    }
}
