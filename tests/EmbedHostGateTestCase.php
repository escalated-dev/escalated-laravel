<?php

namespace Escalated\Laravel\Tests;

/** The host's own gate replaces Escalated's admin gate on the embedded routes. */
abstract class EmbedHostGateTestCase extends EmbedTestCase
{
    protected function embedConfig(): array
    {
        return [
            ...parent::embedConfig(),
            'middleware' => ['web', 'auth', Fixtures\EmbedHostMiddleware::class.':owner'],
            'replace_admin_gate' => true,
        ];
    }
}
