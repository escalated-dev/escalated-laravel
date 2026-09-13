<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verifies that the package types its host-user foreign keys to match the
// configured user model key type (auto-detected), so UUID/string-keyed apps
// migrate cleanly. Column types are probed through the real schema builder.

afterEach(function () {
    Schema::dropIfExists('escalated_ukt_probe');

    // Testbench rolls the package migrations back after some tests, and their
    // down() methods retype user columns from this setting. Left at uuid,
    // PostgreSQL refuses to cast the bigint requester_id and the rollback
    // fails, so which test fails depends only on how many tests ran before.
    config()->set('escalated.user_key_type', 'auto');
});

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

// Each driver names the same column differently: SQLite says "integer",
// PostgreSQL "int8", MySQL "bigint". What matters is that the column holds
// integers, not what the driver calls them.
function isIntegerColumn(string $type): bool
{
    return in_array($type, ['integer', 'int8', 'bigint', 'bigserial'], true);
}

it('creates an integer column for bigint keys', function () {
    expect(isIntegerColumn(probeUserColumnType('bigint')))->toBeTrue();
});

it('creates a string-compatible column for uuid and string keys', function () {
    expect(isIntegerColumn(probeUserColumnType('uuid')))->toBeFalse()
        ->and(isIntegerColumn(probeUserColumnType('string')))->toBeFalse();
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
        ->and(isIntegerColumn(Schema::getColumnType('escalated_ukt_probe', 'requester_id')))->toBeFalse();
});
