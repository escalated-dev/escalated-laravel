<?php

namespace Escalated\Laravel\Events\Concerns;

use Illuminate\Broadcasting\InteractsWithBroadcasting;

/**
 * Trait that conditionally enables broadcasting on events.
 *
 * When escalated.broadcasting.enabled is true, the event will be broadcast
 * via Laravel's broadcasting system. When false, the event behaves as a
 * normal non-broadcasting event.
 */
trait BroadcastsWhenEnabled
{
    use InteractsWithBroadcasting;

    public static function broadcastingEnabled(): bool
    {
        return (bool) config('escalated.broadcasting.enabled', false);
    }

    public function broadcastWhen(): bool
    {
        return static::broadcastingEnabled();
    }
}
