<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\SkillRoutingService;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;

it('routes via explicit routing tag mapping not skill name equality', function () {
    $tagAlpha = Tag::factory()->create(['name' => 'alpha-priority']);

    // Skill name deliberately does NOT match tag name — routing relies on pivot row only.
    $skill = Skill::create(['name' => 'Rare Skill Name XYZ', 'slug' => 'rare-skill-name-xyz']);

    DB::table(Escalated::table('skill_routing_tags'))->insert([
        'skill_id' => $skill->id,
        'tag_id' => $tagAlpha->id,
    ]);

    $agentEligible = TestUser::create([
        'name' => 'Router Agent',
        'email' => 'router@example.test',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $agentMissingSkill = TestUser::create([
        'name' => 'Other Agent',
        'email' => 'other@example.test',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $skill->agents()->sync([
        $agentEligible->id => ['proficiency' => 5],
    ]);

    $ticket = Ticket::factory()->create();
    $ticket->tags()->sync([$tagAlpha->id]);

    $svc = app(SkillRoutingService::class);
    $matches = $svc->findMatchingAgents($ticket->fresh(['tags']));

    expect($matches->pluck('id')->all())->toContain($agentEligible->id);
    expect($matches->pluck('id')->all())->not->toContain($agentMissingSkill->id);

    // Name-based routing would incorrectly match separate skills called "alpha-priority"
    Skill::create(['name' => 'alpha-priority', 'slug' => 'alpha-priority']);
    expect($svc->findMatchingAgents($ticket->fresh(['tags']))->first()->id)->toBe($agentEligible->id);
});

it('routes via routing department mappings', function () {
    $dept = Department::factory()->create();
    $skill = Skill::create(['name' => 'Dept Skill', 'slug' => 'dept-skill']);

    DB::table(Escalated::table('skill_routing_departments'))->insert([
        'skill_id' => $skill->id,
        'department_id' => $dept->id,
    ]);

    $agent = TestUser::create([
        'name' => 'Dept Agent',
        'email' => 'dept.agent@example.test',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);
    $skill->agents()->sync([$agent->id => ['proficiency' => 3]]);

    $ticket = Ticket::factory()->create([
        'department_id' => $dept->id,
    ]);

    $matches = app(SkillRoutingService::class)->findMatchingAgents($ticket);

    expect($matches)->toHaveCount(1);
    expect($matches->first()->id)->toBe($agent->id);
});
