<?php

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

it('returns null on Escalated models when escalated.database_connection is unset', function () {
    config(['escalated.database_connection' => null]);

    expect((new Ticket)->getConnectionName())->toBeNull();
    expect((new Department)->getConnectionName())->toBeNull();
    expect((new Contact)->getConnectionName())->toBeNull();
});

it('routes Escalated models to the configured connection name', function () {
    config(['escalated.database_connection' => 'tenant']);

    expect((new Ticket)->getConnectionName())->toBe('tenant');
    expect((new Department)->getConnectionName())->toBe('tenant');
    expect((new Contact)->getConnectionName())->toBe('tenant');
});

it('honors a per-model $connection override over the config', function () {
    config(['escalated.database_connection' => 'tenant']);

    $pinned = new class extends Model
    {
        use UsesEscalatedConnection;

        protected $connection = 'reporting';
    };

    expect($pinned->getConnectionName())->toBe('reporting');
});
