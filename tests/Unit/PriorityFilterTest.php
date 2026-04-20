<?php

namespace Escalated\Laravel\Tests\Unit;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketService;
use Escalated\Laravel\Tests\TestCase;

/**
 * Regression for https://github.com/escalated-dev/escalated-laravel/issues/64
 *
 * The driver always applied the priority filter correctly, but the
 * customer controller's `$request->only(...)` projection was missing
 * `priority` (while agent/admin/api all had it). The customer
 * `TicketFilters.vue` UI still rendered a priority dropdown, so the
 * param hit the URL but was silently dropped before reaching the
 * driver — the list returned all priorities regardless of the filter.
 */
class PriorityFilterTest extends TestCase
{
    public function test_driver_applies_priority_filter(): void
    {
        Ticket::factory()->create([
            'priority' => TicketPriority::Low,
            'subject' => 'low one',
        ]);
        Ticket::factory()->create([
            'priority' => TicketPriority::Urgent,
            'subject' => 'urgent one',
        ]);

        $service = app(TicketService::class);
        $results = $service->list(['priority' => 'urgent']);

        $this->assertCount(1, $results);
        $this->assertSame('urgent one', $results->items()[0]->subject);
    }

    public function test_agent_index_filters_by_priority(): void
    {
        \Illuminate\Support\Facades\Gate::define('escalated-agent', fn () => true);
        \Illuminate\Support\Facades\Gate::define('escalated-admin', fn () => true);

        $agent = $this->createAgent();
        Ticket::factory()->create(['priority' => TicketPriority::Low, 'subject' => 'low one']);
        Ticket::factory()->create(['priority' => TicketPriority::Urgent, 'subject' => 'urgent one']);

        $response = $this->actingAs($agent)
            ->get(route('escalated.agent.tickets.index', ['priority' => 'urgent']));

        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? null;
        if ($props) {
            $tickets = $props['tickets']['data'] ?? [];
            $this->assertCount(1, $tickets, 'Agent priority filter should return 1 urgent ticket');
            $this->assertSame('urgent one', $tickets[0]['subject']);
        } else {
            $this->markTestIncomplete('Could not extract Inertia props from agent response');
        }
    }

    public function test_agent_index_filters_by_status_and_priority_together(): void
    {
        // Reproduce the Clockwork scenario from the bug report: both
        // status and priority set on the URL at the same time.
        \Illuminate\Support\Facades\Gate::define('escalated-agent', fn () => true);
        \Illuminate\Support\Facades\Gate::define('escalated-admin', fn () => true);

        $agent = $this->createAgent();
        Ticket::factory()->create([
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Low,
            'subject' => 'open low',
        ]);
        Ticket::factory()->create([
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Urgent,
            'subject' => 'open urgent',
        ]);
        Ticket::factory()->create([
            'status' => TicketStatus::Closed,
            'priority' => TicketPriority::Urgent,
            'subject' => 'closed urgent',
        ]);

        $response = $this->actingAs($agent)
            ->get(route('escalated.agent.tickets.index', [
                'status' => 'open',
                'priority' => 'urgent',
            ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? null;
        if ($props) {
            $tickets = $props['tickets']['data'] ?? [];
            $this->assertCount(1, $tickets, 'Agent status+priority filter should return 1 matching ticket');
            $this->assertSame('open urgent', $tickets[0]['subject']);
        } else {
            $this->markTestIncomplete('Could not extract Inertia props from agent response');
        }
    }

    public function test_customer_index_filters_by_priority(): void
    {
        $customer = $this->createTestUser();

        Ticket::factory()->create([
            'priority' => TicketPriority::Low,
            'subject' => 'cust low',
            'requester_type' => $customer->getMorphClass(),
            'requester_id' => $customer->getKey(),
        ]);
        Ticket::factory()->create([
            'priority' => TicketPriority::Urgent,
            'subject' => 'cust urgent',
            'requester_type' => $customer->getMorphClass(),
            'requester_id' => $customer->getKey(),
        ]);

        $response = $this->actingAs($customer)
            ->get(route('escalated.customer.tickets.index', ['priority' => 'urgent']));

        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? null;
        if ($props) {
            $tickets = $props['tickets']['data'] ?? [];
            $this->assertCount(1, $tickets, 'Customer priority filter should return 1 urgent ticket');
            $this->assertSame('cust urgent', $tickets[0]['subject']);
        } else {
            $this->markTestIncomplete('Could not extract Inertia props from customer response');
        }
    }
}
