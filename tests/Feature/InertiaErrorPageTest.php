<?php

/*
 * A refusal or a failure inside the panel stays inside the panel.
 *
 * An Inertia visit that gets an HTML error page back has nowhere to put it:
 * the client opens it in a modal over the screen the user was on. So an
 * Inertia visit to one of Escalated's own routes that ends in 403, 404, 419,
 * 429, 500 or 503 gets the shared frontend's Escalated/Error page instead,
 * with the same props Rails sends: the status and a generic message.
 *
 * Everything else is left to the host's handler: full page loads, JSON and
 * API clients, and the host's own routes.
 */

use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function inertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => ''];
}

beforeEach(function () {
    // Stand-ins for failures the test suite cannot otherwise provoke on a real
    // route (the CSRF check is off under test, maintenance mode is global).
    Route::middleware('web')->prefix('support/error-probe')->group(function () {
        Route::get('/419', fn () => throw new TokenMismatchException('CSRF token mismatch.'))->name('escalated.error-probe.419');
        Route::get('/429', fn () => throw new ThrottleRequestsException('Too Many Attempts.'))->name('escalated.error-probe.429');
        Route::get('/500', fn () => throw new RuntimeException('SQLSTATE[42S02] secret table missing'))->name('escalated.error-probe.500');
        Route::post('/422', fn () => throw ValidationException::withMessages(['subject' => 'Required.']))->name('escalated.error-probe.422');
        Route::get('/503', fn () => throw new HttpException(503, 'Down for maintenance'))->name('escalated.error-probe.503');
    });

    Route::middleware('web')->get('/host/missing', fn () => abort(404))->name('host.missing');
});

it('renders the Error page for an Inertia visit refused with a 403', function () {
    $this->actingAs($this->createAgent())
        ->withHeaders(inertiaHeaders())
        ->get(route('escalated.admin.settings'))
        ->assertStatus(403)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Escalated/Error')
        ->assertJsonPath('props.status', 403)
        ->assertJsonPath('props.message', 'You are not authorized to perform this action.');
});

it('renders the Error page for an Inertia visit to a missing record, without naming the model', function () {
    $response = $this->actingAs($this->createAgent())
        ->withHeaders(inertiaHeaders())
        ->get(route('escalated.agent.tickets.show', 'NOPE-404'));

    $response->assertStatus(404)
        ->assertJsonPath('component', 'Escalated/Error')
        ->assertJsonPath('props.status', 404)
        ->assertJsonPath('props.message', 'The requested resource was not found.');

    expect($response->getContent())->not->toContain('No query results')
        ->and($response->getContent())->not->toContain(Ticket::class);
});

it('renders the Error page for each status an Inertia visit can end in', function (int $status, string $message) {
    config(['app.debug' => false]);

    $this->withHeaders(inertiaHeaders())
        ->get("/support/error-probe/{$status}")
        ->assertStatus($status)
        ->assertJsonPath('component', 'Escalated/Error')
        ->assertJsonPath('props.status', $status)
        ->assertJsonPath('props.message', $message);
})->with([
    '419' => [419, 'Your session has expired. Refresh the page and try again.'],
    '429' => [429, 'Too many requests. Wait a moment and try again.'],
    '500' => [500, 'Something went wrong on our end.'],
    '503' => [503, 'The service is temporarily unavailable. Try again shortly.'],
]);

it('sends only the status and the message', function () {
    $props = $this->actingAs($this->createAgent())
        ->withHeaders(inertiaHeaders())
        ->get(route('escalated.admin.settings'))
        ->json('props');

    expect($props)->toHaveKeys(['status', 'message'])
        ->and($props)->not->toHaveKey('home')
        ->and($props)->not->toHaveKey('exception');
});

it('never puts an exception message on the page outside debug mode', function () {
    config(['app.debug' => false]);

    $response = $this->withHeaders(inertiaHeaders())->get('/support/error-probe/500');

    $response->assertStatus(500)->assertJsonPath('component', 'Escalated/Error');
    expect($response->getContent())->not->toContain('SQLSTATE')
        ->and($response->getContent())->not->toContain('secret table');
});

it('leaves a 500 to the debug page while debug mode is on', function () {
    config(['app.debug' => true]);

    $response = $this->withHeaders(inertiaHeaders())->get('/support/error-probe/500');

    $response->assertStatus(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('leaves a full page load to the host handler', function () {
    $response = $this->actingAs($this->createAgent())->get(route('escalated.admin.settings'));

    $response->assertStatus(403);
    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and($response->getContent())->not->toContain('Escalated/Error');
});

it('leaves a JSON request to the host handler', function () {
    $response = $this->actingAs($this->createAgent())->getJson(route('escalated.admin.settings'));

    $response->assertStatus(403)->assertJsonMissingPath('component');
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('leaves an Inertia visit to a host route to the host handler', function () {
    $response = $this->withHeaders(inertiaHeaders())->get('/host/missing');

    $response->assertStatus(404);
    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and($response->getContent())->not->toContain('Escalated/Error');
});

it('leaves validation failures alone', function () {
    $this->withHeaders(inertiaHeaders())
        ->post('/support/error-probe/422')
        ->assertStatus(302)
        ->assertSessionHasErrors('subject');
});
