<?php

namespace Escalated\Laravel\UI;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Inertia\Inertia;
use Inertia\Response;

final class InertiaUiRenderer implements EscalatedUiRenderer
{
    public function render(string $page, array $props = []): Response
    {
        return Inertia::render($page, $props);
    }
}
