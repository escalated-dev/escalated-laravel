<?php

namespace Escalated\Laravel\Models\Newsletter;

use Escalated\Laravel\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterDelivery extends Model
{
    protected $table = 'escalated_newsletter_deliveries';

    const UPDATED_AT = null;

    protected $fillable = [
        'newsletter_id', 'contact_id', 'email_at_send', 'status',
        'tracking_token', 'sent_at', 'opened_at', 'last_clicked_at',
        'clicks_count', 'bounce_reason', 'failure_reason',
        'attempt_count', 'claimed_at', 'is_test',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'claimed_at' => 'datetime',
        'is_test' => 'boolean',
        'clicks_count' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
