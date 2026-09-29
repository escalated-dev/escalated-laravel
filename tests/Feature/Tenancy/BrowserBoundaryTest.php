<?php

namespace Escalated\Laravel\Tests\Feature\Tenancy;

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\SelectTestTenant;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Escalated\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class BrowserBoundaryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('escalated.tenancy.middleware', [SelectTestTenant::class]);
        $app['config']->set('escalated.routes.admin_middleware', ['web', 'auth:merchant']);
        $app['config']->set('auth.guards.merchant', ['driver' => 'session', 'provider' => 'users']);
    }

    public function test_browser_selection_runs_after_session_and_before_binding_with_a_custom_guard(): void
    {
        [$admin, $ticketA, $ticketB] = $this->seedMerchants();
        $this->actingAs($admin, 'merchant')->withSession(['current_account' => 'a']);
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.agent.tickets.show', $ticketA->reference))->assertOk();
        $this->get(route('escalated.agent.tickets.show', $ticketB->reference))->assertNotFound();
        $this->assertNull(app(TenantContext::class)->current());
        $this->withSession(['current_account' => 'b'])->get(route('escalated.agent.tickets.index'))->assertForbidden();
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_reports_and_csv_exports_only_include_selected_merchant(): void
    {
        [$admin] = $this->seedMerchants();
        $this->actingAs($admin, 'merchant')->withSession(['current_account' => 'a']);
        $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.admin.reports.dashboard'))
            ->assertOk()->assertJsonCount(1, 'props.agent_performance')
            ->assertJsonPath('props.agent_performance.0.agent_name', 'Merchant A Admin');
        $response = $this->get(route('escalated.admin.reports.export', ['type' => 'agent_performance']));
        $response->assertOk()->assertSee('Merchant A Admin')->assertDontSee('Merchant B Admin');
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_merchant_admin_cannot_change_global_host_roles(): void
    {
        [$admin] = $this->seedMerchants();
        $this->actingAs($admin, 'merchant')->withSession(['current_account' => 'a'])
            ->patchJson(route('escalated.admin.users.role', $admin->id), ['role' => 'admin', 'value' => false])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_admin);
    }

    private function seedMerchants(): array
    {
        Event::fake([Events\TicketCreated::class, Events\TicketUpdated::class]);
        Gate::define('escalated-agent', fn ($user) => $user->is_admin || $user->is_agent);
        Gate::define('escalated-admin', fn ($user) => $user->is_admin);
        $admin = $this->createAdmin(['name' => 'Merchant A Admin']);
        $other = $this->createAdmin(['email' => 'b@example.test', 'name' => 'Merchant B Admin']);
        $resolver = new TestTenantResolver;
        $resolver->members = ['a' => [$admin->id], 'b' => [$other->id]];
        app()->instance(TenantResolver::class, $resolver);
        config(['escalated.tenancy.enabled' => true]);
        $context = app(TenantContext::class);

        return [$admin,
            $context->run('a', fn () => Ticket::factory()->create(['assigned_to' => $admin->id])),
            $context->run('b', fn () => Ticket::factory()->create(['assigned_to' => $other->id])),
        ];
    }

    public function test_merchant_administration_does_not_grant_platform_access(): void
    {
        [$admin] = $this->seedMerchants();
        $this->actingAs($admin, 'merchant')->withSession(['current_account' => 'a']);
        $this->get(route('escalated.admin.settings.database'))->assertForbidden();
        $this->postJson(route('escalated.admin.settings.database.test'), ['connection' => 'testing'])->assertForbidden();
        $this->postJson(route('escalated.admin.settings.database.update'), ['connection' => 'testing'])->assertForbidden();
        $this->get(route('escalated.admin.plugins.index'))->assertForbidden();
        $this->postJson(route('escalated.admin.plugins.activate', 'example'))->assertForbidden();
    }
}
