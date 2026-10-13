<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** Additional staff restrictions within the current tenant. */
final class TicketVisibility
{
    public static function actor(): mixed
    {
        return request()->user() ?? auth()->user();
    }

    public static function restricted(mixed $user): bool
    {
        if (! in_array(config('escalated.visibility', 'all'), ['all', 'department'], true)) {
            throw new \LogicException('Escalated visibility must be all or department.');
        }

        return (config('escalated.visibility', 'all') === 'department' || config('escalated.accounts.scope_agents', false))
            && StaffAccess::isStaff($user) && ! StaffAccess::isAdmin($user);
    }

    public static function apply(Builder $query, mixed $user): void
    {
        if (! self::restricted($user)) {
            return;
        }

        if (config('escalated.visibility', 'all') === 'department') {
            $query->whereIn($query->qualifyColumn('department_id'), self::departments($user));
        }
        if (config('escalated.accounts.scope_agents', false)) {
            $account = TicketAccounts::resolve($user);
            if (! $account) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where($query->qualifyColumn('account_type'), $account->getMorphClass())
                    ->where($query->qualifyColumn('account_id'), (string) $account->getKey());
            }
        }
    }

    public static function allows(mixed $user, Ticket $ticket): bool
    {
        if (! self::restricted($user)) {
            return true;
        }
        if (config('escalated.visibility', 'all') === 'department'
            && ($ticket->department_id === null || ! self::departments($user)->where('department_id', $ticket->department_id)->exists())) {
            return false;
        }
        if (config('escalated.accounts.scope_agents', false)) {
            $account = TicketAccounts::resolve($user);

            return $account && $ticket->account_type === $account->getMorphClass()
                && (string) $ticket->account_id === (string) $account->getKey();
        }

        return true;
    }

    private static function departments(mixed $user): QueryBuilder
    {
        // Query the package pivot directly: host identities may use another connection.
        return (new Department)->agents()->newPivotStatement()
            ->where('agent_id', $user->getKey())->select('department_id');
    }

    /** Check an assignment candidate or a per-agent broadcast recipient. */
    public static function agentCanAccess(int|string $agentId, Ticket|ChatSession $record): bool
    {
        if (config('escalated.visibility', 'all') === 'all' && ! config('escalated.accounts.scope_agents', false)) {
            return true;
        }
        $agent = Escalated::findUser($agentId);
        $ticket = $record instanceof Ticket ? $record : $record->ticket;

        return $ticket !== null && StaffAccess::isStaff($agent) && self::allows($agent, $ticket);
    }

    /** Raw report queries must explicitly inherit the Ticket visibility boundary. */
    public static function query(string $table, string $ticketColumn = 'ticket_id'): QueryBuilder
    {
        $query = Escalated::query($table);
        if (self::restricted(self::actor())) {
            $query->whereIn($table.'.'.$ticketColumn, Ticket::query()->select((new Ticket)->qualifyColumn('id')));
        }

        return $query;
    }
}
