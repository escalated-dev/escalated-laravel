<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/*
 * routes.customer_middleware adds guards to the customer portal and customer
 * knowledge base. A staff-only install sets it to ['can:escalated-agent'] so
 * a signed-in user who is not staff cannot list or raise tickets. Empty (the
 * default) leaves the portal open to any signed-in user, as before.
 */
class CustomerMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
        Gate::define('escalated-admin', fn ($user) => $user->is_admin);
        EscalatedSettings::set('knowledge_base_enabled', '1');
    }

    protected function staffOnly($app): void
    {
        $app['config']->set('escalated.routes.customer_middleware', ['can:escalated-agent']);
    }

    /** @return list<array{string, string}> */
    private function customerRequests(): array
    {
        return [
            ['GET', route('escalated.customer.tickets.index')],
            ['GET', route('escalated.customer.tickets.create')],
            ['POST', route('escalated.customer.tickets.store')],
            ['GET', route('escalated.customer.kb.index')],
            ['GET', route('escalated.customer.kb.show', 'any-slug')],
        ];
    }

    public function test_the_option_defaults_to_no_extra_middleware(): void
    {
        $this->assertSame([], config('escalated.routes.customer_middleware'));

        $this->actingAs($this->createTestUser())
            ->get(route('escalated.customer.tickets.index'))
            ->assertOk();
        $this->actingAs($this->createTestUser(['email' => 'other@example.com']))
            ->get(route('escalated.customer.kb.index'))
            ->assertOk();
    }

    #[DefineEnvironment('staffOnly')]
    public function test_a_signed_in_user_who_fails_the_guard_is_refused(): void
    {
        $this->actingAs($this->createTestUser());

        foreach ($this->customerRequests() as [$method, $url]) {
            $this->call($method, $url, ['subject' => 'Hi', 'description' => 'Hello'])
                ->assertForbidden();
        }
    }

    #[DefineEnvironment('staffOnly')]
    public function test_a_user_who_passes_the_guard_still_gets_in(): void
    {
        $this->actingAs($this->createAgent())
            ->get(route('escalated.customer.tickets.index'))
            ->assertOk();
        $this->actingAs($this->createAgent(['email' => 'agent2@example.com']))
            ->get(route('escalated.customer.kb.index'))
            ->assertOk();
    }
}
