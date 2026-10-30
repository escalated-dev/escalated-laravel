<?php

namespace Escalated\Laravel\Enums;

enum TicketChannel: string
{
    case Web = 'web';
    case Email = 'email';
    case Chat = 'chat';
    case Widget = 'widget';
    case Slack = 'slack';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Slack => 'Slack',
            self::Api => 'API',
            default => __('escalated::enums.ticket_channel.'.$this->value),
        };
    }
}
