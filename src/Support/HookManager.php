<?php

namespace Escalated\Laravel\Support;

class HookManager
{
    protected array $actions = [];

    protected array $filters = [];

    /**
     * Add an action hook.
     *
     * @param  string  $tag  The name of the action
     * @param  callable  $callback  The function to call
     * @param  int  $priority  Priority (lower numbers run first)
     */
    public function addAction(string $tag, callable $callback, int $priority = 10): void
    {
        $this->actions[$tag][$priority][] = $callback;
    }

    /**
     * Execute all callbacks registered for an action.
     *
     * @param  string  $tag  The action name
     * @param  mixed  ...$args  Arguments to pass to callbacks
     */
    public function doAction(string $tag, ...$args): void
    {
        if (! isset($this->actions[$tag])) {
            return;
        }

        // Sort by priority
        ksort($this->actions[$tag]);

        foreach ($this->actions[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    /**
     * Check if an action has callbacks.
     */
    public function hasAction(string $tag): bool
    {
        return isset($this->actions[$tag]) && ! empty($this->actions[$tag]);
    }

    /**
     * Remove an action hook.
     *
     * @param  string  $tag  The action name
     * @param  callable|null  $callback  The specific callback to remove (null removes all)
     */
    public function removeAction(string $tag, ?callable $callback = null): void
    {
        if ($callback === null) {
            unset($this->actions[$tag]);

            return;
        }

        if (! isset($this->actions[$tag])) {
            return;
        }

        foreach ($this->actions[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $index => $registeredCallback) {
                if ($registeredCallback === $callback) {
                    unset($this->actions[$tag][$priority][$index]);
                }
            }
        }
    }

    /**
     * Add a filter hook.
     *
     * @param  string  $tag  The name of the filter
     * @param  callable  $callback  The function to call
     * @param  int  $priority  Priority (lower numbers run first)
     */
    public function addFilter(string $tag, callable $callback, int $priority = 10): void
    {
        $this->filters[$tag][$priority][] = $callback;
    }

    /**
     * Apply all callbacks registered for a filter.
     *
     * @param  string  $tag  The filter name
     * @param  mixed  $value  The value to filter
     * @param  mixed  ...$args  Additional arguments
     * @return mixed The filtered value
     */
    public function applyFilters(string $tag, mixed $value, ...$args): mixed
    {
        if (! isset($this->filters[$tag])) {
            return $value;
        }

        // Sort by priority
        ksort($this->filters[$tag]);

        foreach ($this->filters[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }

        return $value;
    }

    /**
     * Check if a filter has callbacks.
     */
    public function hasFilter(string $tag): bool
    {
        return isset($this->filters[$tag]) && ! empty($this->filters[$tag]);
    }

    /**
     * Remove a filter hook.
     *
     * @param  string  $tag  The filter name
     * @param  callable|null  $callback  The specific callback to remove (null removes all)
     */
    public function removeFilter(string $tag, ?callable $callback = null): void
    {
        if ($callback === null) {
            unset($this->filters[$tag]);

            return;
        }

        if (! isset($this->filters[$tag])) {
            return;
        }

        foreach ($this->filters[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $index => $registeredCallback) {
                if ($registeredCallback === $callback) {
                    unset($this->filters[$tag][$priority][$index]);
                }
            }
        }
    }

    /**
     * Get all registered actions.
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Get all registered filters.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
