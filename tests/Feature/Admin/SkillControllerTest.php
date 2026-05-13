<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('creates updates and deletes a skill with routing mappings and proficiency', function () {
    $admin = $this->createAdmin();

    $tag = Tag::create(['name' => 'priority-bug', 'slug' => 'priority-bug', 'color' => '#111111']);
    $dept = Department::factory()->create(['name' => 'Premium Support']);

    $agentA = $this->createAgent(['name' => 'Agent A', 'email' => 'a@example.test']);
    $agentB = $this->createAgent(['name' => 'Agent B', 'email' => 'b@example.test']);

    $this->actingAs($admin)->post(route('escalated.admin.skills.store'), [
        'name' => 'Network Specialist',
        'routing_tag_ids' => [$tag->id],
        'routing_department_ids' => [$dept->id],
        'agents' => [
            ['user_id' => $agentA->id, 'proficiency' => 5],
            ['user_id' => $agentB->id, 'proficiency' => 2],
        ],
    ])->assertRedirect(route('escalated.admin.skills.index'));

    $skill = Skill::firstWhere('name', 'Network Specialist');
    expect($skill)->not->toBeNull();

    $pivotTable = Escalated::table('agent_skill');

    $this->assertDatabaseHas(Escalated::table('skill_routing_tags'), [
        'skill_id' => $skill->id,
        'tag_id' => $tag->id,
    ]);
    $this->assertDatabaseHas(Escalated::table('skill_routing_departments'), [
        'skill_id' => $skill->id,
        'department_id' => $dept->id,
    ]);

    foreach ([[$agentA->id, 5], [$agentB->id, 2]] as [$uid, $prof]) {
        $this->assertDatabaseHas($pivotTable, [
            'skill_id' => $skill->id,
            'user_id' => $uid,
            'proficiency' => $prof,
        ]);
    }

    $tagAlt = Tag::create(['name' => 'infra', 'slug' => 'infra', 'color' => '#222222']);

    $this->actingAs($admin)->from(route('escalated.admin.skills.edit', $skill))
        ->patch(route('escalated.admin.skills.update', $skill), [
            'name' => 'Network SME',
            'routing_tag_ids' => [$tagAlt->id],
            'routing_department_ids' => [],
            'agents' => [
                ['user_id' => $agentB->id, 'proficiency' => 4],
            ],
        ])->assertRedirect(route('escalated.admin.skills.index'));

    $skill->refresh();

    $this->assertDatabaseMissing(Escalated::table('skill_routing_tags'), [
        'skill_id' => $skill->id,
        'tag_id' => $tag->id,
    ]);
    $this->assertDatabaseHas(Escalated::table('skill_routing_tags'), [
        'skill_id' => $skill->id,
        'tag_id' => $tagAlt->id,
    ]);
    $this->assertDatabaseMissing(Escalated::table('skill_routing_departments'), [
        'skill_id' => $skill->id,
        'department_id' => $dept->id,
    ]);

    $this->assertDatabaseMissing($pivotTable, [
        'skill_id' => $skill->id,
        'user_id' => $agentA->id,
    ]);
    $this->assertDatabaseHas($pivotTable, [
        'skill_id' => $skill->id,
        'user_id' => $agentB->id,
        'proficiency' => 4,
    ]);

    expect($skill->name)->toBe('Network SME');

    $this->actingAs($admin)
        ->delete(route('escalated.admin.skills.destroy', $skill))
        ->assertRedirect(route('escalated.admin.skills.index'));

    $this->assertDatabaseMissing(Escalated::table('skills'), ['id' => $skill->id]);
    expect(DB::table($pivotTable)->where('skill_id', $skill->id)->exists())->toBeFalse();
});
