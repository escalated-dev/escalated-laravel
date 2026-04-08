<?php

namespace Escalated\Laravel\Enums;

enum TicketChannel: string
{
    case Web = 'web';
    case Email = 'email';
    case Chat = 'chat';
    case Widget = 'widget';

    public function label(): string
    {
        return __('escalated::enums.ticket_channel.'.$this->value);
    }
}
