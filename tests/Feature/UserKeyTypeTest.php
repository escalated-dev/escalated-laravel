<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verifies that the package types its host-user foreign keys to match the
// configured user model key type (auto-detected), so UUID/string-keyed apps
// migrate cleanly. Column types are probed through the real schema builder.

afterEach(fn () => Schema::dropIfExists('escalated_ukt_probe'));

function probeUserColumnType(string $keyType): string
{
    config()->set('escalated.user_key_type', $keyType);
    Schema::dropIfExists('escalated_ukt_probe');
    Schema::create('escalated_ukt_probe', function (Blueprint $table) {
        $table->id();
        Escalated::userForeignColumn($table, 'user_id');
    });

    return Schema::getColumnType('escalated_ukt_probe', 'user_id');
}

it('auto-resolves to bigint for the default integer-keyed user model', function () {
    config()->set('escalated.user_key_type', 'auto');

    expect(Escalated::userKeyType())->toBe('bigint');
});

it('honors an explicit user_key_type override', function () {
    config()->set('escalated.user_key_type', 'uuid');
    expect(Escalated::userKeyType())->toBe('uuid');

    config()->set('escalated.user_key_type', 'string');
    expect(Escalated::userKeyType())->toBe('string');
});

it('creates an integer column for bigint keys', function () {
    expect(probeUserColumnType('bigint'))->toBe('integer');
});

it('creates a string-compatible column for uuid and string keys', function () {
    expect(probeUserColumnType('uuid'))->not->toBe('integer')
        ->and(probeUserColumnType('string'))->not->toBe('integer');
});

it('builds user morph columns sized to the key type', function () {
    config()->set('escalated.user_key_type', 'uuid');
    Schema::dropIfExists('escalated_ukt_probe');
    Schema::create('escalated_ukt_probe', function (Blueprint $table) {
        $table->id();
        Escalated::userMorphs($table, 'requester');
    });

    expect(Schema::hasColumn('escalated_ukt_probe', 'requester_type'))->toBeTrue()
        ->and(Schema::hasColumn('escalated_ukt_probe', 'requester_id'))->toBeTrue()
        ->and(Schema::getColumnType('escalated_ukt_probe', 'requester_id'))->not->toBe('integer');
});
