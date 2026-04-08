<?php

use Escalated\Laravel\Models\SavedView;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('can create a saved view', function () {
    $admin = $this->createAdmin();

    $response = $this->actingAs($admin)
        ->postJson(route('escalated.admin.saved-views.store'), [
            'name' => 'My View',
            'filters' => ['status' => 'open'],
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('escalated_saved_views', [
        'name' => 'My View',
        'user_id' => $admin->id,
    ]);
});

it('can list saved views', function () {
    $admin = $this->createAdmin();

    SavedView::create([
        'name' => 'Admin View',
        'user_id' => $admin->id,
        'filters' => ['status' => 'open'],
        'position' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('escalated.admin.saved-views.index'));

    $response->assertOk();
    $response->assertJsonCount(1);
});

it('can update own saved view', function () {
    $admin = $this->createAdmin();

    $view = SavedView::create([
        'name' => 'Old Name',
        'user_id' => $admin->id,
        'filters' => ['status' => 'open'],
        'position' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->putJson(route('escalated.admin.saved-views.update', $view), [
            'name' => 'New Name',
            'filters' => ['status' => 'closed'],
        ]);

    $response->assertOk();
    expect($view->fresh()->name)->toBe('New Name');
});

it('can delete own saved view', function () {
    $admin = $this->createAdmin();

    $view = SavedView::create([
        'name' => 'To Delete',
        'user_id' => $admin->id,
        'filters' => ['status' => 'open'],
        'position' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->deleteJson(route('escalated.admin.saved-views.destroy', $view));

    $response->assertOk();
    expect(SavedView::find($view->id))->toBeNull();
});

it('scopeForUser returns own views and shared views', function () {
    $admin1 = $this->createAdmin();
    $admin2 = $this->createAdmin(['email' => 'admin2@example.com']);

    SavedView::create([
        'name' => 'Admin1 Private',
        'user_id' => $admin1->id,
        'filters' => ['status' => 'open'],
        'is_shared' => false,
        'position' => 1,
    ]);

    SavedView::create([
        'name' => 'Admin2 Private',
        'user_id' => $admin2->id,
        'filters' => ['status' => 'open'],
        'is_shared' => false,
        'position' => 1,
    ]);

    SavedView::create([
        'name' => 'Shared View',
        'user_id' => $admin2->id,
        'filters' => ['status' => 'open'],
        'is_shared' => true,
        'position' => 2,
    ]);

    $views = SavedView::forUser($admin1->id)->get();

    expect($views)->toHaveCount(2);
    expect($views->pluck('name')->all())->toContain('Admin1 Private', 'Shared View');
});

it('users cannot update another users view', function () {
    $admin1 = $this->createAdmin();
    $admin2 = $this->createAdmin(['email' => 'admin2@example.com']);

    $view = SavedView::create([
        'name' => 'Admin1 View',
        'user_id' => $admin1->id,
        'filters' => ['status' => 'open'],
        'position' => 1,
    ]);

    $response = $this->actingAs($admin2)
        ->putJson(route('escalated.admin.saved-views.update', $view), [
            'name' => 'Hacked',
            'filters' => ['status' => 'open'],
        ]);

    $response->assertForbidden();
});

it('users cannot delete another users view', function () {
    $admin1 = $this->createAdmin();
    $admin2 = $this->createAdmin(['email' => 'admin2@example.com']);

    $view = SavedView::create([
        'name' => 'Admin1 View',
        'user_id' => $admin1->id,
        'filters' => ['status' => 'open'],
        'position' => 1,
    ]);

    $response = $this->actingAs($admin2)
        ->deleteJson(route('escalated.admin.saved-views.destroy', $view));

    $response->assertForbidden();
});

it('filters JSON is properly stored and retrieved', function () {
    $filters = [
        'status' => 'open',
        'priority' => 'high',
        'tags' => [1, 2, 3],
    ];

    $view = SavedView::create([
        'name' => 'Complex Filters',
        'user_id' => 1,
        'filters' => $filters,
        'position' => 1,
    ]);

    $retrieved = SavedView::find($view->id);
    expect($retrieved->filters)->toBe($filters);
});

it('reorder updates positions', function () {
    $admin = $this->createAdmin();

    $view1 = SavedView::create([
        'name' => 'View 1',
        'user_id' => $admin->id,
        'filters' => ['status' => 'open'],
        'position' => 0,
    ]);

    $view2 = SavedView::create([
        'name' => 'View 2',
        'user_id' => $admin->id,
        'filters' => ['status' => 'closed'],
        'position' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('escalated.admin.saved-views.reorder'), [
            'ids' => [$view2->id, $view1->id],
        ]);

    $response->assertOk();

    expect($view1->fresh()->position)->toBe(1);
    expect($view2->fresh()->position)->toBe(0);
});
