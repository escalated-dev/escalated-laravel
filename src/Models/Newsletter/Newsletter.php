<?php

namespace Escalated\Laravel\Models\Newsletter;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Newsletter extends Model
{
    use UsesEscalatedConnection;

    protected $table = 'escalated_newsletters';

    protected $fillable = [
        'subject', 'from_email', 'from_name', 'reply_to',
        'target_list_id', 'template_id', 'theme', 'body_markdown',
        'status', 'scheduled_at', 'sent_at', 'created_by', 'sent_by',
        'summary_total', 'summary_sent', 'summary_opened',
        'summary_clicked', 'summary_bounced', 'summary_complained',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'summary_total' => 'integer',
        'summary_sent' => 'integer',
        'summary_opened' => 'integer',
        'summary_clicked' => 'integer',
        'summary_bounced' => 'integer',
        'summary_complained' => 'integer',
    ];

    public function targetList(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'target_list_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NewsletterTemplate::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }
}
