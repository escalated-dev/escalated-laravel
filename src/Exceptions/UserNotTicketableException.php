<?php

namespace Escalated\Laravel\Exceptions;

use Escalated\Laravel\Contracts\Ticketable;
use LogicException;

/**
 * The signed-in user's model cannot act on tickets because it does not
 * implement Escalated\Laravel\Contracts\Ticketable. Thrown where staff
 * requests resolve the user, instead of a TypeError deep in a service.
 */
class UserNotTicketableException extends LogicException
{
    public static function for(object $user): self
    {
        $class = $user::class;

        return new self(
            "Escalated cannot act as [{$class}]: the user model must implement ".Ticketable::class.'. '
            ."Add `implements Ticketable` and `use HasTickets;` (Escalated\\Laravel\\Contracts\\HasTickets) to {$class}, "
            .'or run `php artisan escalated:install` to add them. If staff use a different model, point '
            .'escalated.user_model at it.'
        );
    }
}
