<?php

use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('lists skills in the admin area', function () {
    $admin = $this->createAdmin();
    Skill::create(['name' => 'Billing']);

    $this->actingAs($admin)
        ->get(route('escalated.admin.skills.index'))
        ->assertOk();
});

it('creates a skill with agent assignments and routing rules', function () {
    $admin = $this->createAdmin();
    $agent = $this->createAgent(['email' => 'skill-agent@example.com']);
    $tag = Tag::factory()->create(['name' => 'Spanish']);
    $department = Department::factory()->create(['name' => 'Billing']);

    $this->actingAs($admin)
        ->post(route('escalated.admin.skills.store'), [
            'name' => 'Spanish',
            'routing_tag_ids' => [$tag->id],
            'routing_department_ids' => [$department->id],
            'agents' => [
                ['user_id' => $agent->id, 'proficiency' => 5],
            ],
        ])
        ->assertRedirect(route('escalated.admin.skills.index'));

    $skill = Skill::query()->where('name', 'Spanish')->firstOrFail();
    expect($skill->routing_tag_ids)->toBe([$tag->id]);
    expect($skill->routing_department_ids)->toBe([$department->id]);
    expect($skill->agents()->first()?->pivot?->proficiency)->toBe(5);
});

it('updates skill routing rules and re-syncs agent proficiencies', function () {
    $admin = $this->createAdmin();
    $agentOne = $this->createAgent(['email' => 'skill-agent-1@example.com']);
    $agentTwo = $this->createAgent(['email' => 'skill-agent-2@example.com']);
    $tag = Tag::factory()->create(['name' => 'Technical']);
    $department = Department::factory()->create(['name' => 'Engineering']);
    $skill = Skill::create(['name' => 'Technical']);
    $skill->agents()->sync([$agentOne->id => ['proficiency' => 2]]);

    $this->actingAs($admin)
        ->put(route('escalated.admin.skills.update', $skill), [
            'name' => 'Technical',
            'routing_tag_ids' => [$tag->id],
            'routing_department_ids' => [$department->id],
            'agents' => [
                ['user_id' => $agentTwo->id, 'proficiency' => 4],
            ],
        ])
        ->assertRedirect(route('escalated.admin.skills.index'));

    $skill->refresh();
    expect($skill->routing_tag_ids)->toBe([$tag->id]);
    expect($skill->routing_department_ids)->toBe([$department->id]);
    expect($skill->agents->pluck('id')->all())->toBe([$agentTwo->id]);
    expect($skill->agents()->first()?->pivot?->proficiency)->toBe(4);
});
