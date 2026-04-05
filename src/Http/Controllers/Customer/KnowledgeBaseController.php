<?php

namespace Escalated\Laravel\Http\Controllers\Customer;

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class KnowledgeBaseController extends Controller
{
    public function __construct(protected EscalatedUiRenderer $renderer) {}

    public function index(Request $request): mixed
    {
        $categories = ArticleCategory::withCount(['articles' => function ($q) {
            $q->published();
        }])->roots()->ordered()->get();

        $query = Article::published()->with('category');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $articles = $query->latest('published_at')->paginate(15)->withQueryString();

        return $this->renderer->render('Escalated/Customer/KnowledgeBase/Index', [
            'categories' => $categories,
            'articles' => $articles,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function show(string $slug): mixed
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $article->load('category');
        $article->incrementViews();

        $related = Article::published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->limit(5)
            ->get(['id', 'title', 'slug']);

        return $this->renderer->render('Escalated/Customer/KnowledgeBase/Article', [
            'article' => $article,
            'related' => $related,
        ]);
    }

    public function feedback(string $slug, Request $request): RedirectResponse
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        if ($validated['helpful']) {
            $article->markHelpful();
        } else {
            $article->markNotHelpful();
        }

        return back()->with('success', 'Thank you for your feedback!');
    }
}
