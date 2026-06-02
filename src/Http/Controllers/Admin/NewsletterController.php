<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Models\Newsletter\NewsletterTemplate;
use Escalated\Laravel\Services\Newsletter\NewsletterPlannerService;
use Escalated\Laravel\Services\Newsletter\NewsletterRendererService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function __construct(protected EscalatedUiRenderer $ui) {}

    public function index(Request $request): mixed
    {
        $tab = $request->query('tab', 'drafts');
        $statuses = match ($tab) {
            'scheduled' => ['scheduled', 'sending', 'paused'],
            'sent' => ['sent', 'failed'],
            default => ['draft'],
        };
        $newsletters = Newsletter::with('targetList')->whereIn('status', $statuses)->latest()->paginate(50);

        return $this->ui->render('Escalated/Admin/Newsletters/Index', compact('newsletters', 'tab'));
    }

    public function create(): mixed
    {
        return $this->ui->render('Escalated/Admin/Newsletters/Compose', $this->composeProps());
    }

    public function store(Request $request, NewsletterPlannerService $planner): mixed
    {
        $data = $this->validateForm($request);
        $isSend = in_array($data['status'] ?? 'draft', ['scheduled', 'sending'], true);
        if ($isSend && ! $this->mailConfigured()) {
            return back()->withErrors(['from_email' => 'Outbound mail is not configured.']);
        }
        $n = Newsletter::create($data + ['created_by' => Auth::id()]);
        if (($data['status'] ?? null) === 'sending') {
            $planner->plan($n);
        }

        return redirect("/admin/newsletters/{$n->id}");
    }

    public function show(Newsletter $newsletter, Request $request): mixed
    {
        $tab = $request->query('tab', 'overview');
        $statusFilter = $request->query('status');
        $deliveries = NewsletterDelivery::where('newsletter_id', $newsletter->id)
            ->where('is_test', false)
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->with('contact:id,name,email')
            ->latest('id')->paginate(100);
        $topClicks = [];

        return $this->ui->render('Escalated/Admin/Newsletters/Show', [
            'newsletter' => $newsletter->load('targetList'),
            'deliveries' => $deliveries,
            'topClicks' => $topClicks,
            'tab' => $tab,
        ]);
    }

    public function edit(Newsletter $newsletter): mixed
    {
        abort_unless(in_array($newsletter->status, ['draft', 'scheduled'], true), 422, 'Only drafts and scheduled newsletters can be edited');

        return $this->ui->render('Escalated/Admin/Newsletters/Edit', $this->composeProps() + ['newsletter' => $newsletter]);
    }

    public function update(Newsletter $newsletter, Request $request, NewsletterPlannerService $planner): mixed
    {
        $data = $this->validateForm($request);
        $newsletter->update($data);
        if (($data['status'] ?? null) === 'sending') {
            $planner->plan($newsletter);
        }

        return redirect("/admin/newsletters/{$newsletter->id}");
    }

    public function destroy(Newsletter $newsletter): mixed
    {
        abort_unless($newsletter->status === 'draft', 422, 'Only drafts can be deleted');
        $newsletter->delete();

        return redirect('/admin/newsletters');
    }

    public function preview(Request $request, NewsletterRendererService $renderer): mixed
    {
        $data = $request->validate([
            'subject' => 'nullable|string|max:998',
            'body_markdown' => 'nullable|string',
            'theme' => 'nullable|string',
            'target_list_id' => 'nullable|integer',
            'from_email' => 'nullable|email',
        ]);
        $n = new Newsletter($data + ['theme' => $data['theme'] ?? 'default', 'from_email' => $data['from_email'] ?? 'preview@example.test']);
        $n->id = 0;
        $contact = new Contact(['email' => 'preview@example.test', 'name' => 'Preview User']);
        $contact->id = 0;
        $delivery = new NewsletterDelivery([
            'newsletter_id' => 0, 'contact_id' => 0,
            'email_at_send' => $contact->email, 'tracking_token' => 'preview',
        ]);
        $delivery->setRelation('newsletter', $n);
        $delivery->setRelation('contact', $contact);

        return response()->json(['html' => $renderer->render($delivery)]);
    }

    public function testSend(Request $request, NewsletterRendererService $renderer): mixed
    {
        $data = $this->validateForm($request);
        $n = new Newsletter($data);
        $n->id = 0;
        $contact = new Contact(['email' => $request->user()->email, 'name' => $request->user()->name ?? 'Tester']);
        $contact->id = $request->user()->getKey();
        $delivery = new NewsletterDelivery([
            'newsletter_id' => 0,
            'contact_id' => $contact->id,
            'email_at_send' => $contact->email,
            'tracking_token' => Str::random(40),
            'is_test' => true,
        ]);
        $delivery->setRelation('newsletter', $n);
        $delivery->setRelation('contact', $contact);
        $html = $renderer->render($delivery);
        Mail::html($html, function ($message) use ($request, $data) {
            $message->to($request->user()->email)
                ->from($data['from_email'], $data['from_name'] ?? null)
                ->subject('[TEST] '.$data['subject']);
        });

        return response()->json(['ok' => true]);
    }

    private function composeProps(): array
    {
        return [
            'lists' => NewsletterList::query()->select('id', 'name')->withCount('members as member_count')->get(),
            'templates' => NewsletterTemplate::query()->select('id', 'name')->get(),
            'themes' => $this->discoverThemes(),
            'mailConfigured' => $this->mailConfigured(),
            'canSend' => true,
            'defaultFromEmail' => config('escalated.newsletters.default_from'),
            'defaultReplyTo' => config('escalated.newsletters.default_reply_to'),
            'defaultTheme' => config('escalated.newsletters.default_theme', 'default'),
        ];
    }

    private function discoverThemes(): array
    {
        $candidates = [
            base_path('vendor/escalated/laravel/resources/views/newsletters/themes'),
            __DIR__.'/../../../../resources/views/newsletters/themes',
            resource_path('views/vendor/escalated/newsletters/themes'),
        ];
        $themes = [];
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                foreach (glob("{$dir}/*.blade.php") ?: [] as $path) {
                    $themes[] = basename($path, '.blade.php');
                }
            }
        }
        $themes = array_values(array_unique($themes));

        return $themes ?: ['default', 'branded'];
    }

    private function validateForm(Request $request): array
    {
        return $request->validate([
            'subject' => 'required|string|max:998',
            'from_email' => 'required|email|max:320',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|email|max:320',
            'target_list_id' => 'required|integer|exists:escalated_newsletter_lists,id',
            'template_id' => 'nullable|integer|exists:escalated_newsletter_templates,id',
            'theme' => 'nullable|string|max:64',
            'body_markdown' => 'nullable|string',
            'status' => 'in:draft,scheduled,sending',
            'scheduled_at' => 'nullable|date|after:now',
        ]);
    }

    private function mailConfigured(): bool
    {
        return ! in_array(config('mail.default'), [null, 'array'], true);
    }
}
