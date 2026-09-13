<?php

namespace Escalated\Laravel\Models\Newsletter;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterList extends Model
{
    use UsesEscalatedConnection;

    protected $fillable = ['name', 'description', 'kind', 'filter_json', 'created_by'];

    protected $casts = [
        'filter_json' => 'array',
    ];

    public function getTable(): string
    {
        return Escalated::table('newsletter_lists');
    }

    public function members(): HasMany
    {
        return $this->hasMany(NewsletterListMember::class, 'list_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            Contact::class,
            Escalated::table('newsletter_list_members'),
            'list_id',
            'contact_id',
        )->withPivot(['added_at', 'added_by']);
    }
}
