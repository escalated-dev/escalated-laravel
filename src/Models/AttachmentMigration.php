<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

/** Internal recovery journal; retained until the original public bytes are removed. */
class AttachmentMigration extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('attachment_migrations');
    }
}
