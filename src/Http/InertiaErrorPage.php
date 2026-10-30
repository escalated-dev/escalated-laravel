<?php

namespace Escalated\Laravel\Http;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders the shared frontend's Escalated/Error page for an Inertia visit to
 * one of Escalated's own routes that ends in an error.
 *
 * Without it the host's HTML error page comes back to an Inertia visit, and
 * the client can only show it in a modal over the screen the user was on.
 *
 * It is registered as a render callback on the host's exception handler, so
 * it sees the exception after Laravel has mapped it (a missing model is a
 * NotFoundHttpException, a CSRF failure a 419 by then). It answers only when:
 *
 *  - the request is an Inertia visit (X-Inertia), not a full page load and
 *    not a JSON or API client;
 *  - the matched route is one of Escalated's (named `escalated.*`);
 *  - the status is one the page has wording for.
 *
 * Everything else returns null and stays with the host's handler. The props
 * are the ones Rails sends: the status and a generic, translated message. An
 * exception's own message never reaches the page: a missing model names its
 * class, a database error its SQL. While debug mode is on a 500 is left to
 * the framework's debug page, which is where a developer wants it.
 */
final class InertiaErrorPage
{
    public const PAGE = 'Escalated/Error';

    /** The statuses the page renders. */
    public const STATUSES = [403, 404, 419, 429, 500, 503];

    public function __invoke(Throwable $e, Request $request): ?Response
    {
        if (! $request->header('X-Inertia') || ! $this->isEscalatedRoute($request)) {
            return null;
        }

        $status = $this->statusFor($e);

        if ($status === null || ! in_array($status, self::STATUSES, true)) {
            return null;
        }

        if ($status === 500 && config('app.debug')) {
            return null;
        }

        $page = app(EscalatedUiRenderer::class)->render(self::PAGE, [
            'status' => $status,
            'message' => __("escalated::messages.errors.{$status}"),
        ]);

        return Router::toResponse($request, $page)->setStatusCode($status);
    }

    private function isEscalatedRoute(Request $request): bool
    {
        $name = $request->route()?->getName();

        return is_string($name) && str_starts_with($name, 'escalated.');
    }

    /**
     * The status the host's handler would answer with, or null for the
     * exceptions it answers with something other than an error page.
     */
    private function statusFor(Throwable $e): ?int
    {
        if ($e instanceof HttpResponseException
            || $e instanceof AuthenticationException
            || $e instanceof ValidationException) {
            return null;
        }

        return $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
    }
}
