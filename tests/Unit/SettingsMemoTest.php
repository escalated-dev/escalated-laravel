<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

function settingsQueries(callable $callback): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count) {
        if (str_contains($query->sql, Escalated::table('settings'))) {
            $count++;
        }
    });
    $callback();

    return $count;
}

it('reads every setting a request needs with one query', function () {
    EscalatedSettings::set('guest_tickets_enabled', '0');
    EscalatedSettings::set('knowledge_base_public', '1');

    $queries = settingsQueries(function () {
        expect(EscalatedSettings::guestTicketsEnabled())->toBeFalse()
            ->and(EscalatedSettings::getBool('show_powered_by', true))->toBeTrue()
            ->and(EscalatedSettings::knowledgeBaseEnabled())->toBeTrue()
            ->and(EscalatedSettings::knowledgeBasePublic())->toBeTrue()
            ->and(EscalatedSettings::get('missing', 'fallback'))->toBe('fallback');
    });

    expect($queries)->toBe(1);
});

it('sees its own writes straight away', function () {
    expect(EscalatedSettings::getInt('sla_hours', 4))->toBe(4);

    EscalatedSettings::set('sla_hours', '12');
    expect(EscalatedSettings::getInt('sla_hours'))->toBe(12);

    EscalatedSettings::query()->where('key', 'sla_hours')->first()->update(['value' => '24']);
    expect(EscalatedSettings::getInt('sla_hours'))->toBe(24);

    EscalatedSettings::query()->where('key', 'sla_hours')->first()->delete();
    expect(EscalatedSettings::getInt('sla_hours', 4))->toBe(4);
});

it('reads again after each request, so a long-running worker never serves a stale value', function () {
    EscalatedSettings::set('show_powered_by', '1');
    expect(EscalatedSettings::getBool('show_powered_by'))->toBeTrue();

    // Another process changes it behind this one's back.
    DB::connection(EscalatedSettings::query()->getConnection()->getName())
        ->table(Escalated::table('settings'))->where('key', 'show_powered_by')->update(['value' => '0']);
    expect(EscalatedSettings::getBool('show_powered_by'))->toBeTrue();

    event(new RequestHandled(Request::create('/'), new Response));

    expect(EscalatedSettings::getBool('show_powered_by'))->toBeFalse();
});
