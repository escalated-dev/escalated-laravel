<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Concerns\PresentsAsTicketSubject;

/**
 * A host-app model that can be attached to a ticket as its *subject* — the
 * thing the ticket is about (a Project, Customer, Lead, asset, …), as opposed
 * to the requester (the person who raised it).
 *
 * Implement this on any Eloquent model you want to attach to tickets. The
 * methods drive how the subject is presented in the ticket UI. Use the
 * {@see PresentsAsTicketSubject} trait for sane
 * defaults and override only what you need.
 */
interface TicketSubject
{
    /** Primary label shown for the subject (e.g. "Acme Website Redesign"). */
    public function ticketSubjectTitle(): string;

    /** Secondary line (e.g. "Project · Acme Corp"). Null to omit. */
    public function ticketSubjectSubtitle(): ?string;

    /** Deep link into the host app for this subject. Null for non-clickable. */
    public function ticketSubjectUrl(): ?string;

    /** Accent color as a hex string or design token (e.g. "#2563eb"). Null for default. */
    public function ticketSubjectColor(): ?string;

    /** Icon slug the frontend maps to an icon (e.g. "folder", "building"). Null for default. */
    public function ticketSubjectIcon(): ?string;
}
