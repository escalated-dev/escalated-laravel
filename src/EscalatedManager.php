<?php

namespace Escalated\Laravel;

use Escalated\Laravel\Contracts\TicketDriver;
use Escalated\Laravel\Drivers\CloudDriver;
use Escalated\Laravel\Drivers\LocalDriver;
use Escalated\Laravel\Drivers\SyncedDriver;
use InvalidArgumentException;

class EscalatedManager
{
    /**
     * The resolved driver instances.
     */
    protected array $drivers = [];

    /**
     * Get a driver instance.
     */
    public function driver(?string $name = null): TicketDriver
    {
        $name = $name ?? $this->getDefaultDriver();

        if ($name === 'cloud' && (config('escalated.visibility', 'all') !== 'all'
            || config('escalated.accounts.enabled', false) || config('escalated.accounts.scope_agents', false)
            || config('escalated.sla.targeted', false))) {
            throw new \LogicException('Ticket visibility, accounts and targeted SLAs require locally stored tickets; cloud mode is not supported.');
        }

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return config('escalated.mode', 'self-hosted');
    }

    /**
     * Create a driver instance.
     */
    protected function createDriver(string $name): TicketDriver
    {
        return match ($name) {
            'self-hosted' => $this->createLocalDriver(),
            'synced' => $this->createSyncedDriver(),
            'cloud' => $this->createCloudDriver(),
            default => throw new InvalidArgumentException("Unsupported Escalated driver [{$name}]."),
        };
    }

    /**
     * Create the local driver.
     */
    public function createLocalDriver(): LocalDriver
    {
        return app(LocalDriver::class);
    }

    /**
     * Create the synced driver.
     */
    public function createSyncedDriver(): SyncedDriver
    {
        return app(SyncedDriver::class);
    }

    /**
     * Create the cloud driver.
     */
    public function createCloudDriver(): CloudDriver
    {
        return app(CloudDriver::class);
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
