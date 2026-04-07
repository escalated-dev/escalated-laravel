<?php

namespace Escalated\Laravel\Contracts;

/**
 * Abstraction for rendering UI pages.
 *
 * The default implementation delegates to Inertia. Teams that want
 * Blade, Livewire, or another UI can provide their own implementation.
 */
interface EscalatedUiRenderer
{
    /**
     * Render a named page with the given props.
     *
     * @param  string  $page  Page/component identifier (e.g. 'Escalated/Agent/Dashboard')
     * @param  array  $props  Data to pass to the page
     * @return mixed Response object (Inertia\Response, Illuminate\Http\Response, etc.)
     */
    public function render(string $page, array $props = []): mixed;
}
