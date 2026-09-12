<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('webhook_deliveries');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_code' => 'integer',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class, 'webhook_id');
    }

    public function isSuccess(): bool
    {
        return $this->response_code >= 200 && $this->response_code < 300;
    }
}
