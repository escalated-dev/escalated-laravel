<?php

namespace Escalated\Laravel\Exceptions;

use RuntimeException;

/**
 * No native or SDK plugin has this slug.
 */
class UnknownPluginException extends RuntimeException
{
    public function __construct(public readonly string $slug)
    {
        parent::__construct("No plugin is installed as '{$slug}'.");
    }
}
