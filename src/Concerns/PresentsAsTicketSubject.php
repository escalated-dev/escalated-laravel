<?php

namespace Escalated\Laravel\Concerns;

use Escalated\Laravel\Contracts\TicketSubject;

/**
 * Default implementation of {@see TicketSubject}.
 *
 * Drop this trait onto a host model and it becomes attachable to tickets with
 * a reasonable presentation out of the box: the title falls back to a `name`
 * or `title` attribute, everything else is null (no subtitle/url/color/icon).
 * Override any method to customize.
 *
 *     class Project extends Model implements TicketSubject
 *     {
 *         use PresentsAsTicketSubject;
 *
 *         public function ticketSubjectSubtitle(): ?string
 *         {
 *             return 'Project · '.$this->customer->name;
 *         }
 *     }
 */
trait PresentsAsTicketSubject
{
    public function ticketSubjectTitle(): string
    {
        foreach (['name', 'title', 'label'] as $attribute) {
            $value = $this->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    public function ticketSubjectSubtitle(): ?string
    {
        return null;
    }

    public function ticketSubjectUrl(): ?string
    {
        return null;
    }

    public function ticketSubjectColor(): ?string
    {
        return null;
    }

    public function ticketSubjectIcon(): ?string
    {
        return null;
    }
}
