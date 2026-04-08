<?php

namespace Escalated\Laravel\Http\Controllers\Customer;

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KnowledgeBaseController extends Controller
{
    public function __construct(protected EscalatedUiRenderer $renderer) {}

    public function index(Request $request): mixed
    {
        $this->ensureKnowledgeBaseAccessible($request);

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
            'feedbackEnabled' => EscalatedSettings::knowledgeBaseFeedbackEnabled(),
        ]);
    }

    public function show(string $slug, Request $request): mixed
    {
        $this->ensureKnowledgeBaseAccessible($request);

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
            'feedbackEnabled' => EscalatedSettings::knowledgeBaseFeedbackEnabled(),
        ]);
    }

    public function feedback(string $slug, Request $request): RedirectResponse
    {
        $this->ensureKnowledgeBaseAccessible($request);

        if (! EscalatedSettings::knowledgeBaseFeedbackEnabled()) {
            abort(404);
        }

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

    /**
     * Ensure the knowledge base is enabled and accessible to the current user.
     */
    protected function ensureKnowledgeBaseAccessible(Request $request): void
    {
        if (! EscalatedSettings::knowledgeBaseEnabled()) {
            throw new NotFoundHttpException();
        }

        if (! EscalatedSettings::knowledgeBasePublic() && ! $request->user()) {
            abort(403, 'Authentication required to access the knowledge base.');
        }
    }
}
