<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Contracts\TicketAccountResolver;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Policies\TicketPolicy;
use Escalated\Laravel\Services\ReportingService;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

trait ExercisesTicketVisibility
{
    protected function assertTicketVisibilityBoundaries(): void
    {
        Gate::define('escalated-agent', fn ($user) => $user->is_agent);
        Gate::define('escalated-admin', fn ($user) => $user->is_admin);
        $agent = $this->createAgent();
        $admin = $this->createAdmin();
        $resolver = new TestTenantResolver;
        $resolver->members = ['first' => [$agent->id, $admin->id], 'second' => [$agent->id, $admin->id]];
        app()->instance(TenantResolver::class, $resolver);
        config(['escalated.tenancy.enabled' => true, 'escalated.visibility' => 'department']);
        $context = app(TenantContext::class);
        $foreign = $context->run('second', fn () => Ticket::factory()->create());
        $context->run('first', function () use ($agent, $admin, $foreign) {
            $department = Department::factory()->create();
            $department->agents()->attach($agent);
            $ticket = Ticket::factory()->create(['department_id' => $department->id]);
            Ticket::factory()->create();
            $this->actingAs($agent);
            $this->assertSame([$ticket->id], Ticket::pluck('id')->all());
            $this->assertSame(1, array_sum(array_column(app(ReportingService::class)->ticketsByDepartment(7), 'volume')));
            $this->assertTrue((new TicketPolicy)->view($agent, $ticket));
            $this->assertFalse((new TicketPolicy)->view($agent, $foreign));
            $this->actingAs($admin);
            $this->assertSame(2, Ticket::count());
            $this->assertFalse((new TicketPolicy)->view($admin, $foreign));

            app()->instance(TicketAccountResolver::class, new class($admin) implements TicketAccountResolver
            {
                public function __construct(private Model $account) {}

                public function resolve(Model $model): ?Model
                {
                    return $this->account;
                }
            });
            config(['escalated.accounts.enabled' => true, 'escalated.accounts.scope_agents' => true]);
            $accountTicket = Ticket::factory()->create(['requester_type' => $agent->getMorphClass(), 'requester_id' => $agent->id, 'department_id' => $department->id]);
            $this->assertTrue($accountTicket->fresh()->account->is($admin));
            $this->actingAs($agent);
            $this->assertSame([$accountTicket->id], Ticket::pluck('id')->all());
        });
    }
}
