<?php

namespace Escalated\Laravel\Support;

use Closure;
use Escalated\Laravel\Contracts\TicketAction;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class ArrayTicketAction implements TicketAction
{
    public function __construct(protected array $config)
    {
        if (empty($config['key']) || empty($config['label'])) {
            throw new InvalidArgumentException('Ticket actions require both "key" and "label" values.');
        }
    }

    public function key(): string
    {
        return (string) $this->config['key'];
    }

    public function label(Ticket $ticket, Authenticatable $user): string
    {
        return (string) $this->resolve($this->config['label'], $ticket, $user);
    }

    public function visible(Ticket $ticket, Authenticatable $user): bool
    {
        return (bool) $this->resolve($this->config['visible'] ?? true, $ticket, $user);
    }

    public function enabled(Ticket $ticket, Authenticatable $user): bool
    {
        return (bool) $this->resolve($this->config['enabled'] ?? true, $ticket, $user);
    }

    public function variant(): string
    {
        return (string) ($this->config['variant'] ?? 'secondary');
    }

    public function confirmation(Ticket $ticket, Authenticatable $user): ?string
    {
        $confirmation = $this->resolve($this->config['confirmation'] ?? null, $ticket, $user);

        return $confirmation === null ? null : (string) $confirmation;
    }

    public function metadata(Ticket $ticket, Authenticatable $user): array
    {
        $metadata = $this->resolve($this->config['metadata'] ?? [], $ticket, $user);

        return is_array($metadata) ? $metadata : [];
    }

    protected function resolve(mixed $value, Ticket $ticket, Authenticatable $user): mixed
    {
        if ($value instanceof Closure || (is_array($value) && is_callable($value)) || (is_object($value) && is_callable($value))) {
            return $value($ticket, $user);
        }

        return $value;
    }
}
