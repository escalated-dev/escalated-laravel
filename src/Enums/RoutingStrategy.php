<?php

namespace Escalated\Laravel\Enums;

enum RoutingStrategy: string
{
    case AutoAssign = 'auto_assign';
    case RoundRobin = 'round_robin';
    case SkillBased = 'skill_based';
    case LeastBusy = 'least_busy';
    case ManualQueue = 'manual_queue';

    public function label(): string
    {
        return __('escalated::enums.routing_strategy.'.$this->value);
    }
}
