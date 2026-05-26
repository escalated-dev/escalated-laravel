<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MobileArticleResource extends JsonResource
{
    public function __construct($resource, protected ?Collection $relatedArticles = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => Str::limit(strip_tags((string) $article->body), 160),
            'body' => $article->body,
            'category' => $article->category ? [
                'id' => $article->category->id,
                'name' => $article->category->name,
                'slug' => $article->category->slug,
            ] : null,
            'views' => $article->view_count,
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'is_published' => $article->status === 'published',
            'related_articles' => ($this->relatedArticles ?? collect())
                ->map(fn (Article $related) => [
                    'id' => $related->id,
                    'title' => $related->title,
                    'slug' => $related->slug,
                    'excerpt' => Str::limit(strip_tags((string) $related->body), 160),
                    'created_at' => $related->created_at->toIso8601String(),
                    'updated_at' => $related->updated_at->toIso8601String(),
                    'views' => $related->view_count,
                    'helpful_count' => $related->helpful_count,
                    'not_helpful_count' => $related->not_helpful_count,
                    'is_published' => $related->status === 'published',
                ])
                ->values(),
            'published_at' => $article->published_at?->toIso8601String(),
            'created_at' => $article->created_at->toIso8601String(),
            'updated_at' => $article->updated_at->toIso8601String(),
        ];
    }
}
