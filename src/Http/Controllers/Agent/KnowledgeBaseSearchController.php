<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Knowledge-base search for the agent ticket workspace (ADR
 * 2026-11-05-agent-panel-data, section 5). Published articles only: this
 * package's knowledge base has no agent-only articles.
 */
class KnowledgeBaseSearchController extends Controller
{
    public const DEFAULT_LIMIT = 10;

    public const MAX_LIMIT = 20;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(EscalatedSettings::knowledgeBaseEnabled(), 404);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $articles = Article::published()
            ->with('category:id,name')
            ->search($validated['q'])
            ->orderByTitleMatch($validated['q'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit((int) ($validated['limit'] ?? self::DEFAULT_LIMIT))
            ->get();

        $linkable = Route::has('escalated.customer.kb.show');

        return response()->json([
            'data' => $articles->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $article->body))), 160),
                'url' => $linkable ? route('escalated.customer.kb.show', $article->slug) : null,
                'category' => $article->category ? ['id' => $article->category->id, 'name' => $article->category->name] : null,
                'updated_at' => $article->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
