<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A host user model that was never set up for Escalated: it does not
 * implement Ticketable.
 */
class PlainUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'is_agent' => 'boolean',
        'is_admin' => 'boolean',
    ];
}
