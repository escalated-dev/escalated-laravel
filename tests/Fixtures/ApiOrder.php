<?php

namespace Escalated\Laravel\Tests\Fixtures;

class ApiOrder extends ApiShipment
{
    protected $table = 'api_orders';

    protected $connection = 'subject_host';
}
