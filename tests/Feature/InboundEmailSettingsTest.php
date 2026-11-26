<?php

use Escalated\Laravel\Mail\Adapters\SesAdapter;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Support\RecurringCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/*
 * Issue #322: escalated.inbound_email.enabled is the master switch; with it
 * on, the Settings page's switch, provider and address take effect.
 */

function pollsImap(): bool
{
    return collect(RecurringCommands::all())->contains('command', 'escalated:poll-imap');
}

function adminSettingsPage()
{
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    return test()->actingAs(test()->createAdmin())->get(route('escalated.admin.settings'))->assertOk();
}

it('polls IMAP when IMAP is chosen in Settings', function () {
    config(['escalated.inbound_email.enabled' => true, 'escalated.inbound_email.adapter' => 'mailgun']);
    expect(pollsImap())->toBeFalse();

    EscalatedSettings::set('inbound_email_adapter', 'imap');

    expect(pollsImap())->toBeTrue();
});

it('stops polling IMAP when Settings switches inbound email off or picks a webhook provider', function () {
    config(['escalated.inbound_email.enabled' => true, 'escalated.inbound_email.adapter' => 'imap']);
    expect(pollsImap())->toBeTrue();

    EscalatedSettings::set('inbound_email_adapter', 'postmark');
    expect(pollsImap())->toBeFalse();

    EscalatedSettings::set('inbound_email_adapter', 'imap');
    EscalatedSettings::set('inbound_email_enabled', '0');
    expect(pollsImap())->toBeFalse();
});

it('never polls IMAP while the config switch is off', function () {
    config(['escalated.inbound_email.enabled' => false]);
    EscalatedSettings::set('inbound_email_enabled', '1');
    EscalatedSettings::set('inbound_email_adapter', 'imap');

    expect(pollsImap())->toBeFalse();
});

it('shows a note instead of the inbound controls while the config switch is off', function () {
    config(['escalated.inbound_email.enabled' => false]);

    adminSettingsPage()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('inbound_email_notice', fn ($notice) => str_contains((string) $notice, 'ESCALATED_INBOUND_EMAIL'))
        ->where('supported_settings', fn ($keys) => collect($keys)->contains('guest_tickets_enabled')
            && ! collect($keys)->contains('inbound_email_enabled')
            && ! collect($keys)->contains('inbound_email_adapter')
            && ! collect($keys)->contains('imap_host')));
});

it('offers the inbound controls, without the unused SES region, while the config switch is on', function () {
    config(['escalated.inbound_email.enabled' => true]);

    adminSettingsPage()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('inbound_email_notice', null)
        ->where('supported_settings', fn ($keys) => collect($keys)->contains('inbound_email_enabled')
            && collect($keys)->contains('inbound_email_address')
            && ! collect($keys)->contains('ses_region'))
        ->missing('settings.ses_region'));
});

it('uses the support address from Settings', function () {
    config(['escalated.inbound_email.address' => 'config@example.com']);
    EscalatedSettings::set('inbound_email_address', 'help@example.com');
    Http::fake();

    $request = Request::create('/inbound/ses', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'Type' => 'SubscriptionConfirmation',
        'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/confirm?token=abc',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789:escalated-inbound',
    ]));

    expect((new SesAdapter)->parseRequest($request)->toEmail)->toBe('help@example.com');
});
