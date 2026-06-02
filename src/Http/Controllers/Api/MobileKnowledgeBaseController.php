<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Http\Resources\MobileArticleResource;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MobileKnowledgeBaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureKnowledgeBaseAccessible();

        $query = Article::published()->with('category');

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $articles = $query->latest('published_at')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $articles->getCollection()
                ->map(fn (Article $article) => (new MobileArticleResource($article))->toArray($request))
                ->values(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $this->ensureKnowledgeBaseAccessible();

        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $article->load('category');
        $article->incrementViews();

        $related = Article::published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->limit(5)
            ->get();

        return response()->json([
            'data' => new MobileArticleResource($article->fresh('category'), $related),
        ]);
    }

    public function rate(string $slug, Request $request): JsonResponse
    {
        $this->ensureKnowledgeBaseAccessible();

        if (! EscalatedSettings::knowledgeBaseFeedbackEnabled()) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        $article = Article::published()->where('slug', $slug)->firstOrFail();

        if ($validated['helpful']) {
            $article->markHelpful();
        } else {
            $article->markNotHelpful();
        }

        return response()->json(['message' => 'Thank you for your feedback.']);
    }

    public function categories(): JsonResponse
    {
        $this->ensureKnowledgeBaseAccessible();

        $categories = ArticleCategory::query()
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->ordered()
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $categories->map(fn (ArticleCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'articles_count' => $category->articles_count,
            ]),
        ]);
    }

    protected function ensureKnowledgeBaseAccessible(): void
    {
        if (! EscalatedSettings::knowledgeBaseEnabled()) {
            throw new NotFoundHttpException;
        }
    }
}
