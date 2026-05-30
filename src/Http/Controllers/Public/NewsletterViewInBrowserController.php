<?php

namespace Escalated\Laravel\Http\Controllers\Public;

use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Services\Newsletter\NewsletterRendererService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class NewsletterViewInBrowserController extends Controller
{
    public function __construct(private readonly NewsletterRendererService $renderer) {}

    public function show(string $token): Response
    {
        $delivery = NewsletterDelivery::where('tracking_token', $token)->first();

        // Always answer 200 — returning 404 on an unknown token would leak
        // token validity and allow enumeration. Render a generic page instead.
        if (! $delivery) {
            return new Response(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Email unavailable</title></head><body><p>This email is no longer available.</p></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            );
        }

        return new Response($this->renderer->render($delivery), 200, ['Content-Type' => 'text/html']);
    }
}
