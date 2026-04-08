<?php

namespace Escalated\Laravel\Enums;

enum ChatStatus: string
{
    case Online = 'online';
    case Away = 'away';
    case Offline = 'offline';

    public function label(): string
    {
        return __('escalated::enums.chat_status.'.$this->value);
    }
}
