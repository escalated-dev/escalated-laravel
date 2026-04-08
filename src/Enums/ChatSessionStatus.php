<?php

namespace Escalated\Laravel\Enums;

enum ChatSessionStatus: string
{
    case Waiting = 'waiting';
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return __('escalated::enums.chat_session_status.'.$this->value);
    }
}
