<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Models\Newsletter\NewsletterListMember;
use Escalated\Laravel\Services\Newsletter\ContactSegmentResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NewsletterListController extends Controller
{
    public function __construct(protected EscalatedUiRenderer $ui) {}

    public function index(): mixed
    {
        $lists = NewsletterList::query()
            ->withCount('members as member_count')
            ->get()
            ->map(function ($l) {
                $l->opted_out_count = DB::table('escalated_newsletter_list_members')
                    ->join('escalated_contacts', 'escalated_contacts.id', '=', 'escalated_newsletter_list_members.contact_id')
                    ->where('list_id', $l->id)
                    ->whereNotNull('marketing_opt_out_at')
                    ->count();

                return $l;
            });

        return $this->ui->render('Escalated/Admin/Newsletters/Lists/Index', compact('lists'));
    }

    public function create(): mixed
    {
        return $this->ui->render('Escalated/Admin/Newsletters/Lists/Create', []);
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'kind' => 'required|in:static,dynamic',
            'filter_json' => 'nullable|array',
        ]);
        $list = NewsletterList::create($data + ['created_by' => Auth::id()]);

        return redirect("/admin/newsletters/lists/{$list->id}");
    }

    public function show(NewsletterList $list, ContactSegmentResolver $segments): mixed
    {
        $members = $list->members()->with('contact:id,name,email')->paginate(100);
        $matchCount = $list->kind === 'dynamic' ? $segments->countMatches($list->filter_json ?? ['rules' => []]) : 0;
        $list->member_count = $list->members()->count();
        $list->opted_out_count = DB::table('escalated_newsletter_list_members')
            ->join('escalated_contacts', 'escalated_contacts.id', '=', 'escalated_newsletter_list_members.contact_id')
            ->where('list_id', $list->id)
            ->whereNotNull('marketing_opt_out_at')
            ->count();

        return $this->ui->render('Escalated/Admin/Newsletters/Lists/Show', compact('list', 'members', 'matchCount'));
    }

    public function update(NewsletterList $list, Request $request): mixed
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'filter_json' => 'nullable|array',
        ]);
        $list->update($data);

        return redirect("/admin/newsletters/lists/{$list->id}");
    }

    public function destroy(NewsletterList $list): mixed
    {
        $list->delete();

        return redirect('/admin/newsletters/lists');
    }

    public function addMember(NewsletterList $list, Request $request): mixed
    {
        abort_unless($list->kind === 'static', 422, 'Dynamic lists are filter-driven');
        $data = $request->validate(['contact_id' => 'required|integer|exists:escalated_contacts,id']);
        NewsletterListMember::firstOrCreate(
            ['list_id' => $list->id, 'contact_id' => $data['contact_id']],
            ['added_by' => Auth::id()],
        );

        return redirect("/admin/newsletters/lists/{$list->id}");
    }

    public function removeMember(NewsletterList $list, int $contactId): mixed
    {
        abort_unless($list->kind === 'static', 422, 'Dynamic lists are filter-driven');
        NewsletterListMember::where(['list_id' => $list->id, 'contact_id' => $contactId])->delete();

        return redirect("/admin/newsletters/lists/{$list->id}");
    }

    public function importCsv(NewsletterList $list, Request $request): mixed
    {
        abort_unless($list->kind === 'static', 422);
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $email = filter_var(trim($row[0] ?? ''), FILTER_VALIDATE_EMAIL);
            if (! $email) {
                continue;
            }
            $contact = Contact::firstOrCreate(['email' => $email]);
            NewsletterListMember::firstOrCreate(
                ['list_id' => $list->id, 'contact_id' => $contact->id],
                ['added_by' => Auth::id()],
            );
            $imported++;
        }
        fclose($handle);

        return redirect("/admin/newsletters/lists/{$list->id}")->with('status', "Imported {$imported} contacts");
    }
}
