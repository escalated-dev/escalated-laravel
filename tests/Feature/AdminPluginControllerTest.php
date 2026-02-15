<?php

use Escalated\Laravel\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    $this->pluginsPath = config('escalated.plugins.path', app_path('Plugins/Escalated'));

    if (! File::exists($this->pluginsPath)) {
        File::makeDirectory($this->pluginsPath, 0755, true);
    }
});

afterEach(function () {
    if (File::exists($this->pluginsPath)) {
        File::deleteDirectory($this->pluginsPath);
    }
});

it('shows plugins index page', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.plugins.index'))
        ->assertOk();
});

it('denies non-admin access to plugins page', function () {
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->get(route('escalated.admin.plugins.index'))
        ->assertForbidden();
});

it('lists local plugins with source field', function () {
    $admin = $this->createAdmin();

    $pluginDir = $this->pluginsPath.'/test-plugin';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode([
        'name' => 'Test Plugin',
        'version' => '1.0.0',
    ]));

    $response = $this->actingAs($admin)
        ->get(route('escalated.admin.plugins.index'))
        ->assertOk();

    $plugins = $response->original->getData()['page']['props']['plugins'];
    expect($plugins)->toHaveCount(1);
    expect($plugins[0]['source'])->toBe('local');
    expect($plugins[0]['name'])->toBe('Test Plugin');
});

it('activates a plugin', function () {
    $admin = $this->createAdmin();

    $pluginDir = $this->pluginsPath.'/activatable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Activatable']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    $this->actingAs($admin)
        ->post(route('escalated.admin.plugins.activate', 'activatable'))
        ->assertRedirect();

    expect(Plugin::where('slug', 'activatable')->first()->is_active)->toBeTrue();
});

it('deactivates a plugin', function () {
    $admin = $this->createAdmin();

    $pluginDir = $this->pluginsPath.'/deactivatable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Deactivatable']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    Plugin::create(['slug' => 'deactivatable', 'is_active' => true, 'activated_at' => now()]);

    $this->actingAs($admin)
        ->post(route('escalated.admin.plugins.deactivate', 'deactivatable'))
        ->assertRedirect();

    expect(Plugin::where('slug', 'deactivatable')->first()->is_active)->toBeFalse();
});

it('deletes a local plugin', function () {
    $admin = $this->createAdmin();

    $pluginDir = $this->pluginsPath.'/deletable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Deletable']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    Plugin::create(['slug' => 'deletable', 'is_active' => false]);

    $this->actingAs($admin)
        ->delete(route('escalated.admin.plugins.destroy', 'deletable'))
        ->assertRedirect();

    expect(File::exists($pluginDir))->toBeFalse();
    expect(Plugin::where('slug', 'deletable')->first())->toBeNull();
});

it('rejects deletion of composer plugins via controller', function () {
    $admin = $this->createAdmin();

    $vendorDir = base_path('vendor/acme/protected-plugin');
    File::makeDirectory($vendorDir, 0755, true);
    File::put($vendorDir.'/plugin.json', json_encode([
        'name' => 'Protected Composer Plugin',
        'version' => '1.0.0',
    ]));

    try {
        $this->actingAs($admin)
            ->delete(route('escalated.admin.plugins.destroy', 'acme--protected-plugin'))
            ->assertRedirect()
            ->assertSessionHas('error');
    } finally {
        File::deleteDirectory($vendorDir);
    }
});

it('rejects plugin upload without file', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->post(route('escalated.admin.plugins.upload'), [])
        ->assertSessionHasErrors('plugin');
});
