<?php

namespace Escalated\Laravel\Tests;

/** A server-rendered host: its admin template is the Inertia root view. */
abstract class EmbedRootViewTestCase extends EmbedTestCase
{
    protected function embedConfig(): array
    {
        return [
            ...parent::embedConfig(),
            'root_view' => 'host-admin',
        ];
    }
}
