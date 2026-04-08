<?php

namespace Escalated\Laravel\Enums;

enum ActivityType: string
{
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case PriorityChanged = 'priority_changed';
    case TagAdded = 'tag_added';
    case TagRemoved = 'tag_removed';
    case Escalated = 'escalated';
    case SlaBreached = 'sla_breached';
    case Replied = 'replied';
    case NoteAdded = 'note_added';
    case DepartmentChanged = 'department_changed';
    case Reopened = 'reopened';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Snoozed = 'snoozed';
    case Unsnoozed = 'unsnoozed';

    public function label(): string
    {
        return __('escalated::enums.activity_type.'.$this->value);
    }
}
