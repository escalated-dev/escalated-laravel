<?php

namespace Escalated\Laravel\Tests;

/** Iframe mode with an allow-list (one entry is a wildcard that must be dropped). */
abstract class EmbedIframeTestCase extends EmbedTestCase
{
    protected function embedConfig(): array
    {
        return [
            ...parent::embedConfig(),
            'mode' => 'iframe',
            'frame_ancestors' => ['https://admin.example.com', 'https://*.evil.example', '*', 'https://admin.example.com/'],
        ];
    }
}
