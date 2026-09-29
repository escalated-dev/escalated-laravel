<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Services\GuestTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class WidgetController extends Controller
{
    /**
     * Return widget configuration (branding, KB enabled, etc.)
     */
    public function config(Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            return response()->json(['enabled' => false], 403);
        }

        $departmentIds = array_filter(
            explode(',', EscalatedSettings::get('widget_departments', ''))
        );

        $departments = Department::active()
            ->when(! empty($departmentIds), fn ($q) => $q->whereIn('id', $departmentIds))
            ->get(['id', 'name']);

        return response()->json([
            'enabled' => true,
            'color' => EscalatedSettings::get('widget_color', '#4F46E5'),
            'position' => EscalatedSettings::get('widget_position', 'bottom-right'),
            'greeting' => EscalatedSettings::get('widget_greeting', 'Hi there! How can we help?'),
            'departments' => $departments,
            'kb_enabled' => $this->knowledgeBaseAvailable($request),
            'guest_tickets_enabled' => EscalatedSettings::guestTicketsEnabled(),
            'guest_verification_required' => true,
        ]);
    }

    /**
     * Search published KB articles.
     */
    public function searchArticles(Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            abort(403);
        }

        $this->ensureKnowledgeBaseAvailable($request);

        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $articles = Article::published()
            ->search($request->input('q'))
            ->limit(10)
            ->get(['title', 'slug', 'body'])
            ->map(fn ($article) => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => Str::limit(strip_tags($article->body), 150),
            ]);

        return response()->json(['articles' => $articles]);
    }

    /**
     * Get full article content by slug.
     */
    public function showArticle(Request $request, string $slug): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            abort(403);
        }

        $this->ensureKnowledgeBaseAvailable($request);

        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $article->incrementViews();

        return response()->json([
            'title' => $article->title,
            'slug' => $article->slug,
            'body' => $article->body,
            'category' => $article->category?->name,
        ]);
    }

    /**
     * Whether the widget may offer knowledge-base articles to this visitor.
     * It follows the customer knowledge base's two admin settings: the
     * knowledge base is on, and it is public or the visitor is signed in.
     */
    protected function knowledgeBaseAvailable(Request $request): bool
    {
        return EscalatedSettings::knowledgeBaseEnabled()
            && (EscalatedSettings::knowledgeBasePublic() || $request->user() !== null);
    }

    /**
     * Stops an article request the same way the customer knowledge base does:
     * 404 when it is off, 403 when it is not public and the visitor is not
     * signed in.
     */
    protected function ensureKnowledgeBaseAvailable(Request $request): void
    {
        abort_unless(EscalatedSettings::knowledgeBaseEnabled(), 404);
        abort_if(! EscalatedSettings::knowledgeBasePublic() && $request->user() === null, 403);
    }

    /**
     * Create a guest ticket.
     */
    public function createTicket(Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            abort(403);
        }

        if (! EscalatedSettings::guestTicketsEnabled()) {
            return response()->json(['message' => 'Guest tickets are disabled.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'verification_id' => ['required', 'uuid'],
            'verification_code' => ['required', 'string', 'max:16'],
            'description' => ['required', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', 'exists:'.Department::class.',id'],
        ]);

        $result = app(GuestTicketService::class)->create($validated, 'widget');
        $ticket = $result['ticket'];

        return response()->json([
            'message' => 'Ticket created successfully.',
            'reference' => $ticket->reference,
            'guest_access_token' => $result['token'],
            'expires_at' => $ticket->guest_access_expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Look up ticket status with its verified, expiring guest bearer grant.
     */
    public function ticketStatus(string $reference, Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            abort(403);
        }

        $token = $request->bearerToken();
        abort_unless(is_string($token) && $token !== '', 404);
        $ticket = app(GuestAccess::class)->resolve($token);
        abort_unless(hash_equals($ticket->reference, $reference), 404);

        $replies = $ticket->replies()
            ->where('is_internal_note', false)
            ->with('author')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($reply) => [
                'body' => $reply->body,
                'author' => $reply->author?->name ?? 'Support',
                'is_agent' => $reply->author_type !== null,
                'created_at' => $reply->created_at->toIso8601String(),
            ]);

        return response()->json([
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'status_color' => $ticket->status->color(),
            'created_at' => $ticket->created_at->toIso8601String(),
            'department' => $ticket->department?->name,
            'replies' => $replies,
        ]);
    }
}
