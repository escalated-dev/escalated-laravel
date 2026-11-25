<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/*
 * The public routes of optional features are registered only while the
 * feature is on in config, so an install that never uses them carries no
 * unauthenticated routes for them. Routes are registered at boot, so each
 * case sets the config before the application boots.
 */
class OptionalPublicRoutesTest extends TestCase
{
    /** @return list<string> */
    private function routeNames(string $prefix): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => is_string($name) && Str::startsWith($name, $prefix))
            ->values()
            ->all();
    }

    protected function slackOn($app): void
    {
        $app['config']->set('escalated.slack.enabled', true);
    }

    protected function widgetOff($app): void
    {
        $app['config']->set('escalated.widget.enabled', false);
    }

    protected function guestRoutesOff($app): void
    {
        $app['config']->set('escalated.guest.routes_enabled', false);
    }

    protected function mobileApiOff($app): void
    {
        $app['config']->set('escalated.mobile_api.enabled', false);
    }

    protected function cloudSecretSet($app): void
    {
        $app['config']->set('escalated.hosted.signing_secret', 'whsec_site');
    }

    public function test_defaults_register_widget_and_guest_but_not_slack_or_cloud(): void
    {
        $this->assertTrue(config('escalated.widget.enabled'));
        $this->assertTrue(config('escalated.guest.routes_enabled'));
        $this->assertFalse(config('escalated.slack.enabled'));

        $this->assertNotEmpty($this->routeNames('escalated.widget.'));
        $this->assertNotEmpty($this->routeNames('escalated.guest.'));
        $this->assertSame([], $this->routeNames('escalated.slack.'));
        $this->assertSame([], $this->routeNames('escalated.cloud.'));

        $this->postJson('/support/inbound/slack/default')->assertNotFound();
    }

    public function test_the_mobile_api_is_registered_by_default_with_the_rest_api(): void
    {
        $this->assertTrue(config('escalated.api.enabled'));
        $this->assertTrue(config('escalated.mobile_api.enabled'));

        $this->assertContains('escalated.api.mobile.auth.register', $this->routeNames('escalated.api.mobile.'));
        $this->assertContains('escalated.api.mobile.kb.index', $this->routeNames('escalated.api.mobile.'));
    }

    #[DefineEnvironment('mobileApiOff')]
    public function test_no_mobile_api_route_is_registered_when_the_mobile_api_is_switched_off(): void
    {
        $this->assertSame([], $this->routeNames('escalated.api.mobile.'));
        $this->assertNotEmpty($this->routeNames('escalated.api.tickets.'));

        $this->postJson('/support/api/v1/mobile/auth/register', [])->assertNotFound();
        $this->getJson('/support/api/v1/mobile/kb/articles')->assertNotFound();
    }

    #[DefineEnvironment('slackOn')]
    public function test_slack_inbound_is_registered_when_slack_is_enabled(): void
    {
        $this->assertSame(['escalated.slack.inbound'], $this->routeNames('escalated.slack.'));
    }

    #[DefineEnvironment('cloudSecretSet')]
    public function test_cloud_webhook_is_registered_when_its_signing_secret_is_set(): void
    {
        $this->assertSame(['escalated.cloud.webhook'], $this->routeNames('escalated.cloud.'));
    }

    #[DefineEnvironment('widgetOff')]
    public function test_no_widget_route_is_registered_when_the_widget_is_switched_off(): void
    {
        $this->assertSame([], $this->routeNames('escalated.widget.'));
        $this->assertNotEmpty($this->routeNames('escalated.guest.'));

        $this->getJson('/support/widget/config')->assertNotFound();
        $this->get('/support/widget/embed.js')->assertNotFound();
        $this->postJson('/support/widget/tickets', [])->assertNotFound();
    }

    #[DefineEnvironment('guestRoutesOff')]
    public function test_no_guest_route_is_registered_when_guest_routes_are_switched_off(): void
    {
        $this->assertSame([], $this->routeNames('escalated.guest.'));
        $this->assertNotEmpty($this->routeNames('escalated.widget.'));

        $this->get('/support/guest/create')->assertNotFound();
        $this->postJson('/support/guest', [])->assertNotFound();
    }
}
