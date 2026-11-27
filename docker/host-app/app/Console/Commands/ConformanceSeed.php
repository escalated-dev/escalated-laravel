<?php

namespace App\Console\Commands;

use App\Models\User;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\ApiTokenCode;
use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a throwaway install for the staff API conformance suite and prints
 * KEY=value lines (tokens and the connect fixture) for the CI workflow to
 * export. Host app only; never part of the package.
 */
class ConformanceSeed extends Command
{
    protected $signature = 'escalated:conformance-seed {--force : Run outside the demo and testing environments}';

    protected $description = 'Seed data and tokens for the staff API conformance suite (CI only, throwaway database)';

    public function handle(): int
    {
        if (! app()->environment(['demo', 'testing']) && ! $this->option('force')) {
            $this->error('Refusing to seed: APP_ENV must be demo or testing (or pass --force on a throwaway database).');

            return self::FAILURE;
        }

        $password = Hash::make(Str::random(40));

        $agent = User::create([
            'name' => 'Conformance Agent', 'email' => 'agent@conformance.test',
            'password' => $password, 'is_admin' => false, 'is_agent' => true,
        ]);
        $other = User::create([
            'name' => 'Conformance Other Agent', 'email' => 'other@conformance.test',
            'password' => $password, 'is_admin' => false, 'is_agent' => true,
        ]);
        $admin = User::create([
            'name' => 'Conformance Admin', 'email' => 'admin@conformance.test',
            'password' => $password, 'is_admin' => true, 'is_agent' => true,
        ]);
        $customer = User::create([
            'name' => 'Conformance Customer', 'email' => 'customer@conformance.test',
            'password' => $password, 'is_admin' => false, 'is_agent' => false,
        ]);

        foreach ([$agent, $other, $admin] as $staff) {
            AgentProfile::create(['user_id' => $staff->id, 'agent_type' => 'full', 'max_tickets' => 50]);
        }

        $department = Department::create([
            'name' => 'Conformance', 'slug' => 'conformance',
            'description' => 'Department for the conformance suite.', 'is_active' => true,
        ]);
        $department->agents()->syncWithoutDetaching([$agent->id, $other->id]);

        // Two unassigned and one assigned open ticket (the API cannot unassign).
        foreach ([
            ['Conformance ticket A (unassigned)', null],
            ['Conformance ticket B (unassigned)', null],
            ['Conformance ticket C (assigned)', $other->id],
        ] as [$subject, $assignee]) {
            Ticket::create([
                'requester_type' => $customer->getMorphClass(),
                'requester_id' => $customer->id,
                'subject' => $subject,
                'description' => 'Seeded for the staff API conformance suite.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::Medium,
                'channel' => 'web',
                'department_id' => $department->id,
                'assigned_to' => $assignee,
            ]);
        }

        CannedResponse::create([
            'title' => 'Conformance greeting', 'body' => 'Thanks for reaching out.',
            'created_by' => $admin->id, 'is_shared' => true,
        ]);

        // Actions are only priority or note, which the suite can undo or ignore.
        Macro::create([
            'name' => 'Conformance macro',
            'actions' => [
                ['type' => 'priority', 'value' => 'high'],
                ['type' => 'note', 'body' => 'Applied by the conformance macro.'],
            ],
            'created_by' => $admin->id, 'is_shared' => true,
        ]);

        $agentToken = ApiToken::createToken($agent, 'conformance agent', ['agent']);
        $adminToken = ApiToken::createToken($admin, 'conformance admin', ['agent', 'admin']);
        $revocable = ApiToken::createToken($agent, 'conformance revocable', ['agent']);

        $verifier = Str::random(64); // [A-Za-z0-9], inside the PKCE alphabet
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $extensionId = '';
        for ($i = 0; $i < 32; $i++) {
            $extensionId .= chr(ord('a') + random_int(0, 15)); // a-p
        }
        $redirect = "https://{$extensionId}.chromiumapp.org/";

        $codes = [];
        for ($i = 0; $i < 2; $i++) {
            $codes[] = ApiTokenCode::issue($agent, $challenge, $redirect, 'Conformance', $extensionId, 3600);
        }

        $this->line('AGENT_TOKEN='.$agentToken['plainTextToken']);
        $this->line('ADMIN_TOKEN='.$adminToken['plainTextToken']);
        $this->line('REVOCABLE_TOKEN='.$revocable['plainTextToken']);
        $this->line('CONNECT_CODE='.$codes[0]);
        $this->line('CONNECT_CODE_2='.$codes[1]);
        $this->line('CONNECT_VERIFIER='.$verifier);
        $this->line('CONNECT_REDIRECT_URI='.$redirect);

        return self::SUCCESS;
    }
}
