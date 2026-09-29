<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Illuminate\Database\Eloquent\Model;

/** Association validation for explicitly named package tables in raw queries. */
class TenantRow extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = [];
}
