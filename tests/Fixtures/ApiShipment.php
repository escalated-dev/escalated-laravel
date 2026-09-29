<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Concerns\PresentsAsTicketSubject;
use Escalated\Laravel\Contracts\TicketSubject;
use Illuminate\Database\Eloquent\Model;

class ApiShipment extends Model implements TicketSubject
{
    use PresentsAsTicketSubject;

    protected $table = 'api_shipments';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
