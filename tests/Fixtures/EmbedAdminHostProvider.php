<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Escalated;
use Illuminate\Support\ServiceProvider;

/** A host application's provider turning on the embedded admin (ADR 2026-11-05). */
class EmbedAdminHostProvider extends ServiceProvider
{
    public function register(): void
    {
        Escalated::embedAdmin(
            prefix: '/console/support/',
            middleware: ['web', 'auth', EmbedHostMiddleware::class],
            layout: 'Layouts/ConsoleLayout',
        );
    }
}
