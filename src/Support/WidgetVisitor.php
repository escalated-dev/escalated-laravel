<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Services\GuestEmailVerification;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The signed-in visitor a widget request may act as, when
 * escalated.widget.trust_signed_in_visitors is on.
 *
 * Only a same-origin request with a session user of the configured user
 * model qualifies. Requests from embedding sites never do: WidgetCors
 * already runs them as a guest, and they are checked again here. The
 * identity always comes from the account, never from the form, so a
 * visitor cannot raise a ticket or start a chat as someone else.
 */
final class WidgetVisitor
{
    public static function trusted(Request $request): ?Model
    {
        if (! config('escalated.widget.trust_signed_in_visitors', false)) {
            return null;
        }

        if ($request->attributes->has(WidgetOrigins::ATTRIBUTE) || WidgetOrigins::crossOrigin($request) !== null) {
            return null;
        }

        $user = $request->user();
        $model = Escalated::userModel();
        if (! $user instanceof Model || ! $user instanceof $model) {
            return null;
        }

        // An address the host has not verified is no better than a typed one.
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return null;
        }

        if (! filter_var(self::email($user), FILTER_VALIDATE_EMAIL) || ! app(TenantContext::class)->canAccess($user)) {
            return null;
        }

        return $user;
    }

    /** @return array{name: string, email: string} */
    public static function identity(Model $user): array
    {
        $email = self::email($user);

        return ['name' => Escalated::userDisplayName($user, $email), 'email' => $email];
    }

    private static function email(Model $user): string
    {
        return GuestEmailVerification::email((string) $user->getAttribute('email'));
    }
}
