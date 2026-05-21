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
        abort_if(! $delivery, 404);

        return new Response($this->renderer->render($delivery), 200, ['Content-Type' => 'text/html']);
    }
}
