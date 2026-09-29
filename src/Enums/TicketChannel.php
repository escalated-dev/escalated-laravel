<?php

namespace Escalated\Laravel\Enums;

enum TicketChannel: string
{
    case Web = 'web';
    case Email = 'email';
    case Chat = 'chat';
    case Widget = 'widget';
    case Slack = 'slack';

    public function label(): string
    {
        return $this === self::Slack ? 'Slack' : __('escalated::enums.ticket_channel.'.$this->value);
    }
}
