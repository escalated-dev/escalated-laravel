<?php

namespace Escalated\Laravel\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingOnCustomer = 'waiting_on_customer';
    case WaitingOnAgent = 'waiting_on_agent';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Live = 'live';

    public function label(): string
    {
        return __('escalated::enums.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => '#3B82F6',
            self::InProgress => '#8B5CF6',
            self::WaitingOnCustomer => '#F59E0B',
            self::WaitingOnAgent => '#F97316',
            self::Escalated => '#EF4444',
            self::Resolved => '#10B981',
            self::Closed => '#6B7280',
            self::Reopened => '#3B82F6',
            self::Live => '#06B6D4',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        $transitions = config('escalated.transitions', []);
        $allowed = $transitions[$this->value] ?? [];

        return in_array($target->value, $allowed);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed]);
    }
}
