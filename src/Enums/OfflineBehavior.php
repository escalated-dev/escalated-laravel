<?php

namespace Escalated\Laravel\Enums;

enum OfflineBehavior: string
{
    case Queue = 'queue';
    case TicketFallback = 'ticket_fallback';
    case OfflineForm = 'offline_form';
    case HideChat = 'hide_chat';

    public function label(): string
    {
        return __('escalated::enums.offline_behavior.'.$this->value);
    }
}
