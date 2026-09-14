<?php

use Escalated\Laravel\Http\Middleware\CheckPermission;
use Escalated\Laravel\Http\Requests\CreateTicketRequest;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Models\Newsletter\NewsletterListMember;
use Escalated\Laravel\Models\Newsletter\NewsletterTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/*
 * The suite's base case runs under `helpdesk_` (see PrefixedTablesTestCase), so
 * every migration has already run under that prefix before each test starts. A
 * migration that names an `escalated_` table outright fails there, in setUp.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

/**
 * @return list<string>
 */
function prefixTestTableNames(): array
{
    return array_map(fn (array $table) => $table['name'], Schema::getTables());
}

it('creates every package table under the configured prefix', function () {
    $tables = prefixTestTableNames();

    expect($tables)->toContain(
        'helpdesk_tickets',
        'helpdesk_contacts',
        'helpdesk_newsletter_lists',
        'helpdesk_newsletter_list_members',
        'helpdesk_newsletter_templates',
        'helpdesk_newsletters',
        'helpdesk_newsletter_deliveries',
    );

    expect(array_values(array_filter($tables, fn (string $table) => str_starts_with($table, 'escalated_'))))
        ->toBeEmpty()
        ->and(Schema::hasColumn('helpdesk_contacts', 'marketing_opt_out_at'))->toBeTrue();
});

it('points every foreign key at a table that exists', function () {
    $tables = prefixTestTableNames();

    foreach ($tables as $table) {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            expect(in_array($foreignKey['foreign_table'], $tables, true))
                ->toBeTrue("[{$table}] has a foreign key to [{$foreignKey['foreign_table']}], which does not exist");
        }
    }
});

it('stores newsletter records in the prefixed tables', function () {
    $list = NewsletterList::create(['name' => 'Customers', 'kind' => 'static']);
    $contact = Contact::create(['email' => 'reader@example.com']);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $contact->id]);
    $template = NewsletterTemplate::create(['name' => 'Plain', 'body_markdown' => 'Hello']);
    $newsletter = Newsletter::create([
        'subject' => 'News',
        'from_email' => 'news@example.com',
        'target_list_id' => $list->id,
        'template_id' => $template->id,
        'body_markdown' => 'Hello',
    ]);
    NewsletterDelivery::create([
        'newsletter_id' => $newsletter->id,
        'contact_id' => $contact->id,
        'email_at_send' => 'reader@example.com',
        'tracking_token' => 'tk-prefix',
    ]);

    expect($list->contacts()->pluck('email')->all())->toBe(['reader@example.com'])
        ->and(DB::table('helpdesk_newsletters')->count())->toBe(1)
        ->and(DB::table('helpdesk_newsletter_deliveries')->count())->toBe(1);
});

it('renders the newsletter list pages, which count opted-out members', function () {
    $admin = $this->createAdmin();
    $list = NewsletterList::create(['name' => 'Customers', 'kind' => 'static']);
    $contact = Contact::create(['email' => 'gone@example.com', 'marketing_opt_out_at' => now()]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $contact->id]);

    $this->withoutMiddleware(CheckPermission::class)
        ->actingAs($admin)
        ->get('/admin/newsletters/lists')
        ->assertOk();

    $this->withoutMiddleware(CheckPermission::class)
        ->actingAs($admin)
        ->get("/admin/newsletters/lists/{$list->id}")
        ->assertOk();
});

it('validates newsletter references against the prefixed tables', function () {
    $admin = $this->createAdmin();
    $list = NewsletterList::create(['name' => 'Customers', 'kind' => 'static']);
    $contact = Contact::create(['email' => 'reader@example.com']);

    $this->withoutMiddleware(CheckPermission::class)
        ->actingAs($admin)
        ->post("/admin/newsletters/lists/{$list->id}/members", ['contact_id' => $contact->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/admin/newsletters/lists/{$list->id}");

    expect(NewsletterListMember::count())->toBe(1);

    $this->withoutMiddleware(CheckPermission::class)
        ->actingAs($admin)
        ->post('/admin/newsletters', [
            'subject' => 'News',
            'from_email' => 'news@example.com',
            'target_list_id' => $list->id,
            'body_markdown' => 'Hello',
            'status' => 'draft',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Newsletter::count())->toBe(1);
});

it('accepts a department from the prefixed table on the ticket form', function () {
    $department = Department::factory()->create();

    $validator = Validator::make(
        ['subject' => 'Help', 'description' => 'Details', 'department_id' => $department->id],
        (new CreateTicketRequest)->rules(),
    );

    expect($validator->passes())->toBeTrue();
});
