<?php

namespace Database\Seeders;

use App\Models\User;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus as TicketStatusEnum;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\CustomField;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        fake()->unique(reset: true);

        $users = $this->seedUsers();
        $departments = $this->seedDepartments($users['agents']);
        $this->seedSkills($users['agents']);
        $slas = $this->seedSlaPolicies();
        $this->seedExtraStatuses();
        $tags = $this->seedTags();
        $this->seedCustomFields();
        $this->seedMacros($users['admin']);
        $this->seedCannedResponses($users['admin']);
        $this->seedKnowledgeBase($users['admin']);
        $this->seedEscalationRules($departments);
        $tickets = $this->seedTickets($users, $departments, $slas, $tags);
        $this->seedChatSessions($users, $tickets);

        $this->command?->info('Demo data seeded — /demo to log in.');
    }

    protected function seedUsers(): array
    {
        $password = Hash::make('password');

        $admin = User::create([
            'name' => 'Alice Admin', 'email' => 'alice@demo.test',
            'password' => $password, 'is_admin' => true, 'is_agent' => true,
        ]);

        $agents = collect([
            ['Bob Agent',   'bob@demo.test'],
            ['Carol Agent', 'carol@demo.test'],
            ['Dan Agent',   'dan@demo.test'],
        ])->map(fn ($row) => User::create([
            'name' => $row[0], 'email' => $row[1], 'password' => $password,
            'is_admin' => false, 'is_agent' => true,
        ]));

        $lightAgent = User::create([
            'name' => 'Ellie Light', 'email' => 'ellie@demo.test',
            'password' => $password, 'is_admin' => false, 'is_agent' => true,
        ]);

        $customers = collect([
            ['Frank Customer',  'frank@acme.example'],
            ['Grace Customer',  'grace@acme.example'],
            ['Henry Customer',  'henry@globex.example'],
            ['Iris Customer',   'iris@globex.example'],
            ['Jack Customer',   'jack@initech.example'],
        ])->map(fn ($row) => User::create([
            'name' => $row[0], 'email' => $row[1], 'password' => $password,
            'is_admin' => false, 'is_agent' => false,
        ]));

        foreach ($agents as $agent) {
            AgentProfile::create([
                'user_id' => $agent->id, 'agent_type' => 'full', 'max_tickets' => 25,
            ]);
        }
        AgentProfile::create([
            'user_id' => $admin->id, 'agent_type' => 'full', 'max_tickets' => 50,
        ]);
        AgentProfile::create([
            'user_id' => $lightAgent->id, 'agent_type' => 'light', 'max_tickets' => 10,
        ]);

        return [
            'admin' => $admin,
            'agents' => $agents->push($lightAgent),
            'customers' => $customers,
        ];
    }

    protected function seedDepartments($agents): array
    {
        $data = [
            ['Support',     'support',     'General product support and troubleshooting.'],
            ['Billing',     'billing',     'Invoices, refunds, and subscription questions.'],
            ['Engineering', 'engineering', 'Bug reports and technical escalations.'],
        ];

        $departments = [];
        foreach ($data as [$name, $slug, $desc]) {
            $departments[$slug] = Department::create([
                'name' => $name, 'slug' => $slug, 'description' => $desc, 'is_active' => true,
            ]);
        }

        foreach ($agents as $agent) {
            $departments['support']->agents()->syncWithoutDetaching([$agent->id]);
        }

        return $departments;
    }

    protected function seedSkills($agents): void
    {
        $skills = [];
        foreach (['Billing', 'Refunds', 'Technical', 'Integrations', 'Mobile'] as $name) {
            $skills[] = Skill::create([
                'name' => $name, 'slug' => Str::slug($name),
            ]);
        }

        foreach ($agents as $idx => $agent) {
            $skills[$idx % count($skills)]->agents()->syncWithoutDetaching([$agent->id]);
        }
    }

    protected function seedSlaPolicies(): array
    {
        $standard = SlaPolicy::create([
            'name' => 'Standard',
            'description' => 'Default SLA for most tickets.',
            'is_default' => true,
            'first_response_hours' => ['low' => 24, 'medium' => 8, 'high' => 4, 'urgent' => 2, 'critical' => 1],
            'resolution_hours' => ['low' => 72, 'medium' => 48, 'high' => 24, 'urgent' => 8, 'critical' => 4],
            'business_hours_only' => false,
            'is_active' => true,
        ]);

        $priority = SlaPolicy::create([
            'name' => 'Priority',
            'description' => 'Enterprise-tier tickets with tighter response windows.',
            'is_default' => false,
            'first_response_hours' => ['low' => 4, 'medium' => 2, 'high' => 1, 'urgent' => 1, 'critical' => 1],
            'resolution_hours' => ['low' => 24, 'medium' => 12, 'high' => 4, 'urgent' => 2, 'critical' => 1],
            'business_hours_only' => false,
            'is_active' => true,
        ]);

        return ['standard' => $standard, 'priority' => $priority];
    }

    protected function seedExtraStatuses(): void
    {
        if (TicketStatus::count() === 0) {
            foreach (['New' => 'new', 'Open' => 'open', 'Pending' => 'pending', 'On Hold' => 'on_hold', 'Solved' => 'solved'] as $label => $slug) {
                TicketStatus::create([
                    'label' => $label, 'slug' => $slug, 'category' => $slug,
                    'color' => '#6b7280', 'is_default' => $slug === 'new',
                ]);
            }
        }

        TicketStatus::firstOrCreate(['slug' => 'waiting_on_customer'], [
            'label' => 'Waiting on Customer', 'category' => 'pending',
            'color' => '#f59e0b', 'position' => 50,
        ]);

        TicketStatus::firstOrCreate(['slug' => 'needs_triage'], [
            'label' => 'Needs Triage', 'category' => 'new',
            'color' => '#8b5cf6', 'position' => 51,
        ]);
    }

    protected function seedTags(): array
    {
        $palette = [
            'bug' => '#ef4444', 'feature-request' => '#3b82f6', 'billing' => '#f59e0b',
            'refund' => '#10b981', 'urgent' => '#dc2626', 'integration' => '#8b5cf6',
            'mobile' => '#06b6d4', 'how-to' => '#6366f1',
        ];

        $tags = [];
        foreach ($palette as $slug => $color) {
            $tags[$slug] = Tag::create([
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'slug' => $slug, 'color' => $color,
            ]);
        }

        return $tags;
    }

    protected function seedCustomFields(): void
    {
        CustomField::create([
            'name' => 'Order ID', 'slug' => 'order_id', 'type' => 'text',
            'context' => 'ticket', 'placeholder' => 'e.g. ACM-1234', 'position' => 1, 'active' => true,
        ]);
        CustomField::create([
            'name' => 'Severity', 'slug' => 'severity', 'type' => 'select',
            'context' => 'ticket', 'options' => ['S1', 'S2', 'S3', 'S4'], 'position' => 2, 'active' => true,
        ]);
        CustomField::create([
            'name' => 'Customer Tier', 'slug' => 'customer_tier', 'type' => 'select',
            'context' => 'ticket', 'options' => ['Free', 'Pro', 'Enterprise'], 'position' => 3, 'active' => true,
        ]);
    }

    protected function seedMacros(User $admin): void
    {
        $macros = [
            ['Close as duplicate',    [['type' => 'status', 'value' => 'solved'], ['type' => 'reply', 'body' => 'Closing as duplicate of another ticket.']]],
            ['Escalate to manager',   [['type' => 'priority', 'value' => 'urgent'], ['type' => 'note', 'body' => 'Escalating per customer request.']]],
            ['Approve refund',        [['type' => 'tag_add', 'value' => 'refund'], ['type' => 'reply', 'body' => 'Your refund has been approved and will reach you within 5 business days.']]],
            ['Ask for more info',     [['type' => 'status', 'value' => 'pending'], ['type' => 'reply', 'body' => 'Could you share the order number and a screenshot of the issue?']]],
        ];
        foreach ($macros as [$name, $actions]) {
            Macro::create([
                'name' => $name, 'actions' => $actions,
                'created_by' => $admin->id, 'is_shared' => true,
            ]);
        }
    }

    protected function seedCannedResponses(User $admin): void
    {
        $data = [
            ['Greeting',       'Hi {{ticket.requester.name}}, thanks for reaching out — we\'re on it.'],
            ['Password reset', 'We\'ve triggered a password-reset email. Please check your inbox (and spam folder).'],
            ['Closing note',   'Marking this as resolved. Reply any time to reopen.'],
        ];
        foreach ($data as [$title, $body]) {
            CannedResponse::create([
                'title' => $title, 'body' => $body, 'created_by' => $admin->id,
            ]);
        }
    }

    protected function seedKnowledgeBase(User $admin): void
    {
        $getting = ArticleCategory::create(['name' => 'Getting Started', 'slug' => 'getting-started', 'position' => 1]);
        $billing = ArticleCategory::create(['name' => 'Billing', 'slug' => 'billing-kb', 'position' => 2]);

        $articles = [
            [$getting, 'Welcome to Acme Support', 'Here\'s how to file a ticket, track status, and get help from our team.', 'published'],
            [$getting, 'Installing the mobile app', 'Our mobile apps are available on iOS and Android. Search for "Acme" in the app store.', 'published'],
            [$getting, 'Setting up SSO',           'SSO is available on the Enterprise tier. Contact sales to enable it.',                      'draft'],
            [$billing, 'How billing cycles work',  'We bill on the 1st of each month. Prorated charges apply for mid-cycle upgrades.',         'published'],
            [$billing, 'Requesting a refund',      'Submit a ticket tagged `refund` within 30 days of purchase.',                              'published'],
            [$billing, 'Updating payment method',  'Go to Settings → Billing → Payment Methods to add or remove cards.',                       'published'],
        ];

        foreach ($articles as [$cat, $title, $body, $status]) {
            Article::create([
                'category_id' => $cat->id,
                'title' => $title, 'slug' => Str::slug($title),
                'body' => "<p>{$body}</p>", 'status' => $status,
                'author_id' => $admin->id,
                'published_at' => $status === 'published' ? now()->subDays(rand(1, 60)) : null,
            ]);
        }
    }

    protected function seedEscalationRules(array $departments): void
    {
        EscalationRule::create([
            'name' => 'Urgent unanswered > 1h',
            'description' => 'Escalate urgent tickets that have gone 1 hour without a reply.',
            'trigger_type' => 'time_based',
            'conditions' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'urgent'],
                ['field' => 'time_since_last_reply_minutes', 'operator' => 'greater_than', 'value' => 60],
            ],
            'actions' => [
                ['type' => 'priority', 'value' => 'critical'],
                ['type' => 'note', 'body' => 'Auto-escalated by rule: urgent ticket unanswered >1h.'],
            ],
            'is_active' => true,
        ]);
    }

    protected function seedTickets(array $users, array $departments, array $slas, array $tags): array
    {
        $subjects = [
            'Unable to log in — password reset email not arriving',
            'Feature request: bulk-export tickets as CSV',
            'Mobile app crashes when opening attachments',
            'Refund for duplicate charge on invoice #A-2847',
            'Integration with Slack stopped posting after last update',
            'Workflow stuck in "pending" status forever',
            'Getting 502 from API endpoint /v2/contacts',
            'SSO configuration questions',
            'Cannot upload files larger than 10MB',
            'Team seats: how do we transfer a seat to a new employee?',
            'Emails from support aren\'t threading correctly in Gmail',
            'Webhook retries seem to hammer our endpoint',
            'Report export returns empty CSV',
            'Two-factor auth keeps logging me out',
            'Billing: can we switch from monthly to annual mid-cycle?',
        ];

        $statuses = [TicketStatusEnum::Open, TicketStatusEnum::InProgress, TicketStatusEnum::WaitingOnCustomer, TicketStatusEnum::Resolved, TicketStatusEnum::Closed];
        $priorities = [TicketPriority::Low, TicketPriority::Medium, TicketPriority::High, TicketPriority::Urgent];
        $createdTickets = [];

        $customers = $users['customers'];
        $agents = $users['agents'];

        for ($i = 0; $i < 55; $i++) {
            $customer = $customers[$i % $customers->count()];
            $agent = fake()->boolean(70) ? $agents[$i % $agents->count()] : null;
            $status = $statuses[array_rand($statuses)];
            $priority = $priorities[array_rand($priorities)];
            $createdAt = now()->subMinutes(rand(5, 60 * 24 * 30));

            $ticket = Ticket::factory()
                ->forRequester(User::class, $customer->id)
                ->state([
                    'subject' => $subjects[$i % count($subjects)],
                    'description' => fake()->paragraphs(rand(1, 3), true),
                    'status' => $status,
                    'priority' => $priority,
                    'assigned_to' => $agent?->id,
                    'department_id' => array_values($departments)[$i % 3]->id,
                    'sla_policy_id' => $i % 7 === 0 ? $slas['priority']->id : $slas['standard']->id,
                    'created_at' => $createdAt, 'updated_at' => $createdAt,
                    'resolved_at' => in_array($status, [TicketStatusEnum::Resolved, TicketStatusEnum::Closed]) ? $createdAt->copy()->addHours(rand(2, 48)) : null,
                    'closed_at' => $status === TicketStatusEnum::Closed ? $createdAt->copy()->addHours(rand(48, 96)) : null,
                    'sla_first_response_breached' => $i % 11 === 0,
                ])
                ->create();

            $ticketTags = collect($tags)->random(rand(0, 3))->pluck('id')->all();
            if ($ticketTags) {
                $ticket->tags()->sync($ticketTags);
            }

            $replyCount = rand(0, 5);
            for ($r = 0; $r < $replyCount; $r++) {
                $author = $r === 0 ? $customer : ($agent ?? $agents->first());
                $isInternal = $agent && $r > 0 && fake()->boolean(25);
                Reply::factory()->state([
                    'ticket_id' => $ticket->id,
                    'author_type' => User::class, 'author_id' => $author->id,
                    'body' => fake()->paragraph(),
                    'is_internal_note' => $isInternal,
                    'type' => $isInternal ? 'note' : 'reply',
                    'created_at' => $createdAt->copy()->addMinutes(15 * ($r + 1)),
                    'updated_at' => $createdAt->copy()->addMinutes(15 * ($r + 1)),
                ])->create();
            }

            $createdTickets[] = $ticket;
        }

        return $createdTickets;
    }

    protected function seedChatSessions(array $users, array $tickets): void
    {
        $pairs = [
            [TicketStatusEnum::Open,     'active', null],
            [TicketStatusEnum::Resolved, 'ended',  Carbon::now()->subDays(2)],
            [TicketStatusEnum::Closed,   'ended',  Carbon::now()->subDays(7)],
        ];

        foreach ($pairs as $idx => [$ticketStatus, $chatStatus, $endedAt]) {
            $ticket = $tickets[$idx] ?? null;
            if (! $ticket) {
                continue;
            }

            ChatSession::create([
                'ticket_id' => $ticket->id,
                'customer_session_id' => Str::random(40),
                'agent_id' => $users['agents']->first()->id,
                'status' => $chatStatus,
                'started_at' => Carbon::now()->subDays($idx + 1),
                'ended_at' => $endedAt,
            ]);
        }
    }
}
