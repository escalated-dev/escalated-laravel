<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\TicketAction;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\ArrayTicketAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class TicketActionRegistry
{
    /**
     * @var array<string, TicketAction>
     */
    protected array $actions = [];

    public function __construct(protected Container $container) {}

    public function register(TicketAction|string|array $action): static
    {
        $action = $this->resolveAction($action);

        $this->actions[$action->key()] = $action;

        return $this;
    }

    public function find(string $key): ?TicketAction
    {
        return $this->actions[$key] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTicket(Ticket $ticket, Authenticatable $user): array
    {
        $actions = [];

        foreach ($this->actions as $action) {
            if (! $action->visible($ticket, $user)) {
                continue;
            }

            $actions[] = [
                'key' => $action->key(),
                'label' => $action->label($ticket, $user),
                'variant' => $action->variant(),
                'confirmation' => $action->confirmation($ticket, $user),
                'disabled' => ! $action->enabled($ticket, $user),
                'metadata' => $action->metadata($ticket, $user),
            ];
        }

        return $actions;
    }

    protected function resolveAction(TicketAction|string|array $action): TicketAction
    {
        if ($action instanceof TicketAction) {
            return $action;
        }

        if (is_string($action)) {
            $action = $this->container->make($action);

            if (! $action instanceof TicketAction) {
                throw new InvalidArgumentException('Ticket action classes must implement '.TicketAction::class.'.');
            }

            return $action;
        }

        return new ArrayTicketAction($action);
    }
}
