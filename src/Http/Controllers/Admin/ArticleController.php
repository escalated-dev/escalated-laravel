<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        $query = Article::with('category', 'author');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        return $this->renderer->render('Escalated/Admin/KnowledgeBase/Articles/Index', [
            'articles' => $query->latest()->paginate(20)->withQueryString(),
            'categories' => ArticleCategory::ordered()->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/KnowledgeBase/Articles/Form', [
            'categories' => ArticleCategory::ordered()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,published'],
            'category_id' => ['nullable', 'integer', 'exists:'.ArticleCategory::make()->getTable().',id'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['title']);
        $validated['author_id'] = $request->user()->id;

        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        Article::create($validated);

        return redirect()->route('escalated.admin.kb-articles.index')
            ->with('success', 'Article created.');
    }

    public function edit(Article $kbArticle): mixed
    {
        return $this->renderer->render('Escalated/Admin/KnowledgeBase/Articles/Form', [
            'article' => $kbArticle,
            'categories' => ArticleCategory::ordered()->get(['id', 'name']),
        ]);
    }

    public function update(Article $kbArticle, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,published'],
            'category_id' => ['nullable', 'integer', 'exists:'.ArticleCategory::make()->getTable().',id'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['title']);

        if ($validated['status'] === 'published' && ! $kbArticle->published_at) {
            $validated['published_at'] = now();
        }

        $kbArticle->update($validated);

        return redirect()->route('escalated.admin.kb-articles.index')
            ->with('success', 'Article updated.');
    }

    public function destroy(Article $kbArticle): RedirectResponse
    {
        $kbArticle->delete();

        return redirect()->route('escalated.admin.kb-articles.index')
            ->with('success', 'Article deleted.');
    }
}
