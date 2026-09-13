<?php

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Policies\TicketPolicy;

it('lets a requester close their own ticket only while tickets.allow_customer_close is on', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
    ]);
    $policy = app(TicketPolicy::class);

    config()->set('escalated.tickets.allow_customer_close', true);
    expect($policy->close($user, $ticket))->toBeTrue();

    config()->set('escalated.tickets.allow_customer_close', false);
    expect($policy->close($user, $ticket))->toBeFalse();
});
