<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'helpful_count' => 'integer',
            'not_helpful_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('articles');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Title or body contains the term, ignoring case on every database
     * (PostgreSQL's LIKE is case-sensitive). `%` and `_` in the term are literal:
     * the pattern escapes them with `!`, which every supported database
     * accepts as an ESCAPE character.
     */
    public function scopeSearch($query, string $term)
    {
        $pattern = self::likePattern($term);
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function ($q) use ($pattern, $grammar) {
            $q->whereRaw('LOWER('.$grammar->wrap('title').") LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw('LOWER('.$grammar->wrap('body').") LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    /** Articles whose title contains the term first. */
    public function scopeOrderByTitleMatch($query, string $term)
    {
        $column = $query->getQuery()->getGrammar()->wrap('title');

        return $query->orderByRaw("CASE WHEN LOWER({$column}) LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [self::likePattern($term)]);
    }

    private static function likePattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function markNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }
}
