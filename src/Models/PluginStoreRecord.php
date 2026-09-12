<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the escalated_plugin_store table.
 *
 * Provides a simple document store for SDK plugins. Each record belongs to a
 * plugin, sits inside a named collection, and optionally carries a string key
 * for keyed access (get/set/delete by key). The data column stores arbitrary
 * JSON.
 *
 * Query operators ($gt, $gte, $lt, $lte, $ne, $in, $nin) are applied by the
 * ContextHandler using raw JSON_EXTRACT expressions.
 */
class PluginStoreRecord extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('plugin_store');
    }
}
