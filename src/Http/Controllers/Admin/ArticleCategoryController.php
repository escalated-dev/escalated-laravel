<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\ArticleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class ArticleCategoryController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/KnowledgeBase/Categories/Index', [
            'categories' => ArticleCategory::withCount('articles')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:'.ArticleCategory::make()->getTable().',id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);

        ArticleCategory::create($validated);

        return redirect()->route('escalated.admin.kb-categories.index')
            ->with('success', 'Category created.');
    }

    public function update(ArticleCategory $kbCategory, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:'.ArticleCategory::make()->getTable().',id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);

        $kbCategory->update($validated);

        return redirect()->route('escalated.admin.kb-categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(ArticleCategory $kbCategory): RedirectResponse
    {
        $kbCategory->delete();

        return redirect()->route('escalated.admin.kb-categories.index')
            ->with('success', 'Category deleted.');
    }
}
