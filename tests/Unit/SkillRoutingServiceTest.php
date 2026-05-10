<?php

use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\SkillRoutingService;

it('orders matching agents by skill coverage, proficiency, then load', function () {
    $department = Department::factory()->create(['name' => 'Billing']);
    $tag = Tag::factory()->create(['name' => 'Spanish']);

    $agentHighCoverage = $this->createAgent(['name' => 'Agent High Coverage', 'email' => 'coverage@example.com']);
    $agentLowCoverage = $this->createAgent(['name' => 'Agent Low Coverage', 'email' => 'low@example.com']);
    $agentLowerProficiency = $this->createAgent(['name' => 'Agent Lower Proficiency', 'email' => 'lower@example.com']);

    $spanishSkill = Skill::create([
        'name' => 'Spanish',
        'routing_tag_ids' => [$tag->id],
    ]);
    $billingSkill = Skill::create([
        'name' => 'Billing',
        'routing_department_ids' => [$department->id],
    ]);

    $spanishSkill->agents()->sync([
        $agentHighCoverage->id => ['proficiency' => 5],
        $agentLowCoverage->id => ['proficiency' => 5],
        $agentLowerProficiency->id => ['proficiency' => 3],
    ]);
    $billingSkill->agents()->sync([
        $agentHighCoverage->id => ['proficiency' => 4],
        $agentLowerProficiency->id => ['proficiency' => 2],
    ]);

    Ticket::factory()->count(3)->assigned($agentHighCoverage->id)->open()->create();
    Ticket::factory()->assigned($agentLowCoverage->id)->open()->create();

    $ticket = Ticket::factory()->create(['department_id' => $department->id]);
    $ticket->tags()->attach($tag->id);

    $agents = app(SkillRoutingService::class)->findMatchingAgents($ticket);

    expect($agents->pluck('id')->all())->toBe([
        $agentHighCoverage->id,
        $agentLowerProficiency->id,
        $agentLowCoverage->id,
    ]);
    expect($agents->first()->matched_skill_count)->toBe(2);
    expect($agents->first()->total_skill_proficiency)->toBe(9);
});

it('auto assign prefers skill-matched agents before department load balancing', function () {
    $customer = $this->createTestUser(['email' => 'customer@example.com']);
    $department = Department::factory()->create(['name' => 'Technical']);
    $departmentOnlyAgent = $this->createAgent(['name' => 'Department Only', 'email' => 'dept@example.com']);
    $skilledAgent = $this->createAgent(['name' => 'Skilled Agent', 'email' => 'skilled@example.com']);
    $tag = Tag::factory()->create(['name' => 'French']);

    $department->agents()->sync([$departmentOnlyAgent->id, $skilledAgent->id]);

    $skill = Skill::create([
        'name' => 'French',
        'routing_tag_ids' => [$tag->id],
    ]);
    $skill->agents()->sync([$skilledAgent->id => ['proficiency' => 5]]);

    Ticket::factory()->count(2)->assigned($skilledAgent->id)->open()->create();

    $ticket = Ticket::factory()
        ->forRequester($customer->getMorphClass(), $customer->id)
        ->create(['department_id' => $department->id]);
    $ticket->tags()->attach($tag->id);

    $assigned = app(AssignmentService::class)->autoAssign($ticket);

    expect($assigned)->not->toBeNull();
    expect($assigned?->assigned_to)->toBe($skilledAgent->id);
});
