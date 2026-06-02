<?php

namespace Escalated\Laravel\Http\Controllers\Public;

use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

class NewsletterUnsubscribeController extends Controller
{
    public function show(string $token): mixed
    {
        $delivery = $this->find($token);

        return response()->view('escalated::newsletters.unsubscribe', [
            'token' => $token,
            'email' => $delivery?->email_at_send,
            'confirmed' => false,
        ]);
    }

    public function store(string $token, Request $request): mixed
    {
        $key = 'unsub:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response('Too Many Requests', 429);
        }
        RateLimiter::hit($key, 60);

        $delivery = $this->find($token);
        if ($delivery && $delivery->contact) {
            $delivery->contact->update(['marketing_opt_out_at' => now()]);
        }

        return response()->view('escalated::newsletters.unsubscribe', [
            'token' => $token,
            'email' => $delivery?->email_at_send,
            'confirmed' => true,
        ]);
    }

    private function find(string $token): ?NewsletterDelivery
    {
        return NewsletterDelivery::where('tracking_token', $token)->first();
    }
}
