<?php

use Escalated\Laravel\Models\BusinessSchedule;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $this->admin = $this->createAdmin();
    $this->schedule = BusinessSchedule::create([
        'name' => 'Office', 'timezone' => 'UTC', 'schedule' => ['monday' => ['start' => '09:00', 'end' => '17:00']],
    ]);
});

function policyPayload(array $extra = []): array
{
    return array_merge([
        'name' => 'Standard SLA',
        'first_response_hours' => ['medium' => 8],
        'resolution_hours' => ['medium' => 48],
        'business_hours_only' => true,
        'is_active' => true,
    ], $extra);
}

it('saves the schedule an SLA policy is given', function () {
    $this->actingAs($this->admin)
        ->post(route('escalated.admin.sla-policies.store'), policyPayload(['business_schedule_id' => $this->schedule->id]))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(SlaPolicy::where('name', 'Standard SLA')->value('business_schedule_id'))->toBe($this->schedule->id);
});

it('leaves a policy schedule alone when the form does not send it', function () {
    $policy = SlaPolicy::factory()->create(['business_schedule_id' => $this->schedule->id]);

    $this->actingAs($this->admin)
        ->put(route('escalated.admin.sla-policies.update', $policy), policyPayload())
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($policy->fresh()->business_schedule_id)->toBe($this->schedule->id);
});

it('clears a policy schedule sent empty', function () {
    $policy = SlaPolicy::factory()->create(['business_schedule_id' => $this->schedule->id]);

    $this->actingAs($this->admin)
        ->put(route('escalated.admin.sla-policies.update', $policy), policyPayload(['business_schedule_id' => null]))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($policy->fresh()->business_schedule_id)->toBeNull();
});

it('rejects an unknown schedule on a policy', function () {
    $this->actingAs($this->admin)
        ->post(route('escalated.admin.sla-policies.store'), policyPayload(['business_schedule_id' => 999999]))
        ->assertSessionHasErrors('business_schedule_id');
});

it('saves the schedule a department is given and keeps it when not sent', function () {
    $this->actingAs($this->admin)
        ->post(route('escalated.admin.departments.store'), [
            'name' => 'Engineering', 'is_active' => true, 'business_schedule_id' => $this->schedule->id,
        ])
        ->assertRedirect()->assertSessionHasNoErrors();
    $department = Department::where('name', 'Engineering')->firstOrFail();
    expect($department->business_schedule_id)->toBe($this->schedule->id);

    $this->actingAs($this->admin)
        ->put(route('escalated.admin.departments.update', $department), ['name' => 'Engineering 2', 'is_active' => true])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($department->fresh()->business_schedule_id)->toBe($this->schedule->id);
});

it('rejects an unknown schedule on a department', function () {
    $this->actingAs($this->admin)
        ->post(route('escalated.admin.departments.store'), ['name' => 'Ops', 'business_schedule_id' => 999999])
        ->assertSessionHasErrors('business_schedule_id');
});

it('clears policy and department references when a schedule is deleted', function () {
    $policy = SlaPolicy::factory()->create(['business_schedule_id' => $this->schedule->id]);
    $department = Department::factory()->create(['business_schedule_id' => $this->schedule->id]);

    $this->actingAs($this->admin)
        ->delete(route('escalated.admin.business-hours.destroy', $this->schedule))
        ->assertRedirect();

    expect(BusinessSchedule::find($this->schedule->id))->toBeNull()
        ->and($policy->fresh()->business_schedule_id)->toBeNull()
        ->and($department->fresh()->business_schedule_id)->toBeNull();
});
