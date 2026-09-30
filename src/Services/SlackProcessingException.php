<?php

namespace Escalated\Laravel\Services;

/** Stable diagnostic codes that contain no message text or credentials. */
class SlackProcessingException extends \RuntimeException
{
    /** A terminal failure is dead-lettered at once; retrying cannot succeed. */
    public function __construct(string $code, public readonly bool $terminal = false)
    {
        parent::__construct($code);
    }
}
