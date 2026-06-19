<?php

namespace Escalated\Laravel\Models\Newsletter;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterListMember extends Model
{
    use UsesEscalatedConnection;

    protected $table = 'escalated_newsletter_list_members';

    public $timestamps = false;

    protected $fillable = ['list_id', 'contact_id', 'added_at', 'added_by'];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'list_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
