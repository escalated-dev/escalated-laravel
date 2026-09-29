<?php

namespace Escalated\Laravel\Http\Controllers\Guest;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Services\GuestEmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VerificationController extends Controller
{
    public function store(Request $request, GuestEmailVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'purpose' => ['required', 'in:ticket,chat,lookup'],
        ]);
        abort_unless($data['purpose'] === 'chat'
            ? EscalatedSettings::getBool('chat_enabled', false)
            : EscalatedSettings::guestTicketsEnabled(), 403);

        return response()->json([
            'verification_id' => $verification->challenge($data['email'], $data['purpose']),
            'expires_in' => 600,
            'message' => 'Check your email for a verification code.',
        ], 202);
    }

    public function lookup(Request $request, GuestEmailVerification $verification): JsonResponse
    {
        abort_unless(EscalatedSettings::guestTicketsEnabled(), 403);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'], 'reference' => ['required', 'string', 'max:255'],
            'verification_id' => ['required', 'uuid'], 'verification_code' => ['required', 'string', 'max:16'],
        ]);
        $tickets = $verification->consume($data['verification_id'], $data['verification_code'], $data['email'], 'lookup', function () use ($data) {
            $email = GuestEmailVerification::email($data['email']);
            $emailColumn = Escalated::db()->getQueryGrammar()->wrap('guest_email');

            return Ticket::query()->where(fn ($q) => $q->where('reference', $data['reference'])->orWhere('external_reference', $data['reference']))
                ->whereRaw('LOWER('.$emailColumn.') = ?', [$email])->latest()->limit(20)->get()
                ->map(function (Ticket $ticket) use ($email) {
                    $ticket->updateQuietly(['guest_verified_email' => $email, 'guest_email_verified_at' => now()]);
                    $token = app(GuestAccess::class)->issue($ticket);

                    return ['reference' => $ticket->reference, 'subject' => $ticket->subject,
                        'guest_access_token' => $token, 'expires_at' => $ticket->guest_access_expires_at->toIso8601String()];
                });
        });

        return response()->json(['data' => $tickets]);
    }
}
