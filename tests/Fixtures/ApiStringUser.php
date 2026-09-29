<?php

namespace Escalated\Laravel\Tests\Fixtures;

class ApiStringUser extends TestUser
{
    protected $table = 'api_string_users';

    protected $keyType = 'string';

    public $incrementing = false;
}
