<?php

namespace Escalated\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void addAction(string $tag, callable $callback, int $priority = 10)
 * @method static void doAction(string $tag, ...$args)
 * @method static bool hasAction(string $tag)
 * @method static void removeAction(string $tag, ?callable $callback = null)
 * @method static void addFilter(string $tag, callable $callback, int $priority = 10)
 * @method static mixed applyFilters(string $tag, mixed $value, ...$args)
 * @method static bool hasFilter(string $tag)
 * @method static void removeFilter(string $tag, ?callable $callback = null)
 * @method static array getActions()
 * @method static array getFilters()
 *
 * @see \Escalated\Laravel\Support\HookManager
 */
class Hook extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'escalated.hooks';
    }
}
