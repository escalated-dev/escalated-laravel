<?php

namespace Escalated\Laravel\Tests\Feature\Tenancy;

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Policies\TicketPolicy;
use Escalated\Laravel\Services\AttachmentService;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\UnconfiguredTenantResolver;
use Escalated\Laravel\Tests\Fixtures\SelectTestTenant;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Escalated\Laravel\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Host-global agent/admin flags never grant staff access to a merchant where
 * the user holds no tenant-local seat.
 */
class StaffSeatBoundaryTest extends TestCase
{
    private TestTenantResolver $resolver;

    private TestUser $user;

    private Ticket $ticketA;

    private Ticket $ticketB;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('escalated.tenancy.middleware', [SelectTestTenant::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([Events\TicketCreated::class, Events\TicketUpdated::class, Events\ReplyCreated::class]);
        Storage::fake('local');
        Gate::define('escalated-agent', fn ($user) => $user->is_admin || $user->is_agent);
        Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
        // Staff (agent + admin) at merchant A; only a customer at merchant B.
        $this->user = $this->createTestUser(['name' => 'Shared', 'email' => 'shared@example.test', 'is_agent' => true, 'is_admin' => true]);
        $this->resolver = new TestTenantResolver;
        $this->resolver->members = ['a' => [$this->user->id], 'b' => [$this->user->id]];
        $this->resolver->agents = ['a' => [$this->user->id], 'b' => []];
        $this->resolver->admins = ['a' => [$this->user->id], 'b' => []];
        app()->instance(TenantResolver::class, $this->resolver);
        config(['escalated.tenancy.enabled' => true]);
        $context = app(TenantContext::class);
        $requester = ['requester_type' => $this->user->getMorphClass(), 'requester_id' => $this->user->id];
        $this->ticketA = $context->run('a', fn () => Ticket::factory()->create($requester));
        $this->ticketB = $context->run('b', fn () => Ticket::factory()->create($requester));
    }

    public function test_customer_seat_at_another_merchant_denies_agent_and_admin_browser_routes(): void
    {
        $this->actingAs($this->user)->withSession(['current_account' => 'b']);
        $this->get(route('escalated.agent.tickets.index'))->assertForbidden();
        $this->get(route('escalated.agent.tickets.show', $this->ticketB->reference))->assertForbidden();
        $this->post(route('escalated.agent.tickets.note', $this->ticketB->reference), ['body' => 'Internal'])->assertForbidden();
        $this->get(route('escalated.admin.tickets.index'))->assertForbidden();
        $this->get(route('escalated.admin.reports.dashboard'))->assertForbidden();
        $this->assertSame(0, app(TenantContext::class)->run('b', fn () => Reply::query()->count()));

        $this->withSession(['current_account' => 'a']);
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.agent.tickets.show', $this->ticketA->reference))->assertOk();
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.admin.reports.dashboard'))->assertOk();
    }

    public function test_shared_page_props_and_policies_use_the_tenant_seat(): void
    {
        $this->actingAs($this->user)->withSession(['current_account' => 'b']);
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.customer.tickets.index'))
            ->assertOk()->assertJsonPath('props.escalated.is_agent', false)->assertJsonPath('props.escalated.is_admin', false);

        $policy = new TicketPolicy;
        $context = app(TenantContext::class);
        $context->run('b', function () use ($policy) {
            $this->assertTrue($policy->view($this->user, $this->ticketB), 'Requesters still see their own ticket.');
            $this->assertFalse($policy->update($this->user, $this->ticketB));
            $this->assertFalse($policy->addNote($this->user, $this->ticketB));
            $this->assertFalse($policy->delete($this->user, $this->ticketB));
            $this->assertFalse(StaffAccess::isAgent($this->user));
            $this->assertFalse(StaffAccess::isAdmin($this->user));
        });
        $context->run('a', function () use ($policy) {
            $this->assertTrue($policy->addNote($this->user, $this->ticketA));
            $this->assertTrue($policy->delete($this->user, $this->ticketA));
        });
    }

    public function test_admin_seat_is_separate_from_agent_seat(): void
    {
        $this->resolver->agents['b'] = [$this->user->id];
        $this->actingAs($this->user)->withSession(['current_account' => 'b']);
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.agent.tickets.show', $this->ticketB->reference))->assertOk();
        $this->get(route('escalated.admin.tickets.index'))->assertForbidden();
        $this->get(route('escalated.admin.reports.dashboard'))->assertForbidden();
    }

    public function test_private_attachments_require_a_tenant_staff_seat(): void
    {
        $context = app(TenantContext::class);
        $url = $context->run('b', function () {
            $note = $this->ticketB->replies()->create(['body' => 'Internal', 'is_internal_note' => true]);

            return app(AttachmentService::class)->store($note, UploadedFile::fake()->create('note.txt', 1))->url;
        });
        $this->actingAs($this->user)->withSession(['current_account' => 'b'])->get($url)->assertForbidden();

        $this->resolver->agents['b'] = [$this->user->id];
        $this->get($url)->assertOk()->assertDownload('note.txt');
    }

    public function test_agent_api_tokens_issued_in_a_merchant_require_the_seat_there(): void
    {
        $token = app(TenantContext::class)->run('b', fn () => ApiToken::createToken($this->user, 'B', ['agent', 'admin'])['plainTextToken']);
        $this->withToken($token)->getJson(route('escalated.api.tickets.index'))->assertForbidden();
        $this->resolver->agents['b'] = [$this->user->id];
        $this->withToken($token)->getJson(route('escalated.api.tickets.index'))->assertOk();
    }

    public function test_agent_directories_list_only_tenant_seats(): void
    {
        $staff = $this->createAgent(['email' => 'staff-b@example.test']);
        $this->resolver->members['b'][] = $staff->id;
        $this->resolver->agents['b'] = [$staff->id];
        $token = app(TenantContext::class)->run('b', fn () => ApiToken::createToken($staff, 'B', ['agent'])['plainTextToken']);
        $this->withToken($token)->getJson(route('escalated.api.agents'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $staff->id);
    }

    public function test_resolvers_without_staff_decisions_fail_closed(): void
    {
        app()->instance(TenantResolver::class, new class extends UnconfiguredTenantResolver
        {
            public function canAccess(Model $user, string $tenantId): bool
            {
                return true;
            }
        });
        app(TenantContext::class)->run('a', function () {
            $this->assertTrue(Gate::forUser($this->user)->allows('escalated-admin'));
            $this->assertFalse(StaffAccess::isAgent($this->user));
            $this->assertFalse(StaffAccess::isAdmin($this->user));
        });
    }

    public function test_single_tenant_installations_keep_using_the_host_gates(): void
    {
        config(['escalated.tenancy.enabled' => false]);
        app()->instance(TenantResolver::class, new UnconfiguredTenantResolver);
        $ticket = Ticket::factory()->create();
        $customer = $this->createTestUser(['email' => 'customer@example.test']);
        $this->assertTrue(StaffAccess::isAgent($this->user));
        $this->assertTrue(StaffAccess::isAdmin($this->user));
        $this->assertFalse(StaffAccess::isAgent($customer));
        $this->actingAs($this->user)->withHeaders(['X-Inertia' => 'true'])
            ->get(route('escalated.agent.tickets.show', $ticket->reference))->assertOk();
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.admin.reports.dashboard'))->assertOk();
        $this->actingAs($customer)->get(route('escalated.agent.tickets.index'))->assertForbidden();
    }
}
