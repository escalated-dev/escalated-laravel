<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * A host user keyed by ULID.
 *
 * Never saved: the fixture users table has an integer key. It stands in for
 * the authenticated user that a broadcast channel callback receives.
 */
class UlidTestUser extends TestUser
{
    use HasUlids;
}
