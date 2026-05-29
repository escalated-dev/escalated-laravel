<?php

namespace Escalated\Laravel\Contracts;

interface TicketAttachable
{
    public function ticketAttachableUrl(): ?string;

    public function ticketAttachableTitle(): string;

    public function ticketAttachableSubtitle(): ?string;

    public function ticketAttachableColor(): string;

    public function ticketAttachableIcon(): string;

    public function ticketAttachableBadge(): ?string;

    public function ticketAttachableMetadata(): array;

    public function getKey();

    public function getMorphClass();
}
