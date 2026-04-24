<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class WidgetController extends Controller
{
    /**
     * Return widget configuration (branding, KB enabled, etc.)
     */
    public function config(): JsonResponse
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
            'kb_enabled' => config('escalated.knowledge_base.enabled', true),
            'guest_tickets_enabled' => EscalatedSettings::guestTicketsEnabled(),
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
    public function showArticle(string $slug): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            abort(403);
        }

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
            'description' => ['required', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', 'exists:'.config('escalated.table_prefix', 'escalated_').'departments,id'],
        ]);

        // Dedupe repeat submitters by email — one Contact per email
        // across all their tickets (Pattern B).
        $contact = Contact::findOrCreateByEmail($validated['email'], $validated['name']);

        $ticket = Ticket::create([
            'guest_name' => $validated['name'],
            'guest_email' => $validated['email'],
            'guest_token' => Str::random(64),
            'contact_id' => $contact->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::from(config('escalated.default_priority', 'medium')),
            'channel' => 'widget',
            'department_id' => $validated['department_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Ticket created successfully.',
            'reference' => $ticket->reference,
        ], 201);
    }

    /**
     * Look up ticket status by reference + email.
     */
    public function ticketStatus(string $reference, Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('widget_enabled', false)) {
            abort(403);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $ticket = Ticket::where('reference', $reference)
            ->where('guest_email', $request->input('email'))
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

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
