<?php

namespace Escalated\Laravel\Models\Newsletter;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Illuminate\Database\Eloquent\Model;

class NewsletterTemplate extends Model
{
    use UsesEscalatedConnection;

    protected $table = 'escalated_newsletter_templates';

    protected $fillable = ['name', 'theme', 'subject_template', 'body_markdown', 'merge_fields_schema', 'created_by'];

    protected $casts = [
        'merge_fields_schema' => 'array',
    ];
}
