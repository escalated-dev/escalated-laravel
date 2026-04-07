<?php

use Escalated\Laravel\Models\Plugin;
use Escalated\Laravel\Services\PluginService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->pluginsPath = app_path('Plugins/Escalated');

    // Clean up any leftover plugin dirs
    if (File::exists($this->pluginsPath)) {
        File::deleteDirectory($this->pluginsPath);
    }

    $this->pluginService = app(PluginService::class);
});

afterEach(function () {
    // Clean up test plugin dirs
    if (File::exists($this->pluginsPath)) {
        File::deleteDirectory($this->pluginsPath);
    }
});

// ─── Local Plugin Discovery ───

it('discovers local plugins with plugin.json', function () {
    $pluginDir = $this->pluginsPath.'/test-plugin';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode([
        'name' => 'Test Plugin',
        'version' => '1.2.0',
        'author' => 'Tester',
        'description' => 'A test plugin',
    ]));

    $plugins = $this->pluginService->getAllPlugins();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0]['slug'])->toBe('test-plugin');
    expect($plugins[0]['name'])->toBe('Test Plugin');
    expect($plugins[0]['version'])->toBe('1.2.0');
    expect($plugins[0]['author'])->toBe('Tester');
    expect($plugins[0]['source'])->toBe('local');
    expect($plugins[0]['is_active'])->toBeFalse();
});

it('ignores directories without plugin.json', function () {
    $pluginDir = $this->pluginsPath.'/no-manifest';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/README.md', '# Not a plugin');

    $plugins = $this->pluginService->getAllPlugins();

    expect($plugins)->toBeEmpty();
});

it('returns empty array when no plugins exist', function () {
    $plugins = $this->pluginService->getAllPlugins();

    expect($plugins)->toBeArray();
    expect($plugins)->toBeEmpty();
});

it('uses defaults for missing manifest fields', function () {
    $pluginDir = $this->pluginsPath.'/minimal-plugin';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode([
        'name' => 'Minimal',
    ]));

    $plugins = $this->pluginService->getAllPlugins();

    expect($plugins[0]['version'])->toBe('1.0.0');
    expect($plugins[0]['author'])->toBe('Unknown');
    expect($plugins[0]['description'])->toBe('');
    expect($plugins[0]['main_file'])->toBe('Plugin.php');
});

// ─── Composer Plugin Discovery ───

it('discovers composer plugins with plugin.json in vendor', function () {
    $vendorDir = base_path('vendor/acme/test-escalated-plugin');
    File::makeDirectory($vendorDir, 0755, true);
    File::put($vendorDir.'/plugin.json', json_encode([
        'name' => 'Acme Plugin',
        'version' => '2.0.0',
        'author' => 'Acme Corp',
    ]));

    try {
        $plugins = $this->pluginService->getAllPlugins();
        $composerPlugins = array_filter($plugins, fn ($p) => $p['source'] === 'composer');

        expect($composerPlugins)->not->toBeEmpty();
        $plugin = array_values($composerPlugins)[0];
        expect($plugin['slug'])->toBe('acme--test-escalated-plugin');
        expect($plugin['name'])->toBe('Acme Plugin');
        expect($plugin['source'])->toBe('composer');
    } finally {
        File::deleteDirectory($vendorDir);
    }
});

// ─── Activation / Deactivation ───

it('activates a plugin and creates database record', function () {
    $pluginDir = $this->pluginsPath.'/activatable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Activatable']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    $this->pluginService->activatePlugin('activatable');

    $dbPlugin = Plugin::where('slug', 'activatable')->first();
    expect($dbPlugin)->not->toBeNull();
    expect($dbPlugin->is_active)->toBeTrue();
    expect($dbPlugin->activated_at)->not->toBeNull();
});

it('deactivates an active plugin', function () {
    $pluginDir = $this->pluginsPath.'/deactivatable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Deactivatable']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    $this->pluginService->activatePlugin('deactivatable');
    $this->pluginService->deactivatePlugin('deactivatable');

    $dbPlugin = Plugin::where('slug', 'deactivatable')->first();
    expect($dbPlugin->is_active)->toBeFalse();
    expect($dbPlugin->deactivated_at)->not->toBeNull();
});

it('reflects activation status in getAllPlugins', function () {
    $pluginDir = $this->pluginsPath.'/status-check';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Status Check']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    $plugins = $this->pluginService->getAllPlugins();
    expect($plugins[0]['is_active'])->toBeFalse();

    $this->pluginService->activatePlugin('status-check');

    $plugins = $this->pluginService->getAllPlugins();
    expect($plugins[0]['is_active'])->toBeTrue();
});

it('returns activated plugin slugs', function () {
    Plugin::create(['slug' => 'active-one', 'is_active' => true, 'activated_at' => now()]);
    Plugin::create(['slug' => 'inactive-one', 'is_active' => false]);

    $activated = $this->pluginService->getActivatedPlugins();

    expect($activated)->toContain('active-one');
    expect($activated)->not->toContain('inactive-one');
});

// ─── Delete ───

it('deletes a local plugin and removes database record', function () {
    $pluginDir = $this->pluginsPath.'/deletable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode(['name' => 'Deletable']));
    File::put($pluginDir.'/Plugin.php', '<?php // noop');

    $this->pluginService->activatePlugin('deletable');
    $this->pluginService->deletePlugin('deletable');

    expect(File::exists($pluginDir))->toBeFalse();
    expect(Plugin::where('slug', 'deletable')->first())->toBeNull();
});

it('rejects deletion of composer plugins', function () {
    $vendorDir = base_path('vendor/acme/undeletable-plugin');
    File::makeDirectory($vendorDir, 0755, true);
    File::put($vendorDir.'/plugin.json', json_encode([
        'name' => 'Undeletable',
        'version' => '1.0.0',
    ]));

    try {
        $this->pluginService->deletePlugin('acme--undeletable-plugin');
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('Composer plugins cannot be deleted');
    } finally {
        File::deleteDirectory($vendorDir);
    }
});

it('returns false when deleting non-existent plugin', function () {
    $result = $this->pluginService->deletePlugin('non-existent');

    expect($result)->toBeFalse();
});

// ─── Load Plugin ───

it('loads a local plugin file', function () {
    $pluginDir = $this->pluginsPath.'/loadable';
    File::makeDirectory($pluginDir, 0755, true);
    File::put($pluginDir.'/plugin.json', json_encode([
        'name' => 'Loadable',
        'main_file' => 'Plugin.php',
    ]));
    File::put($pluginDir.'/Plugin.php', '<?php define("ESCALATED_TEST_PLUGIN_LOADED", true);');

    $this->pluginService->loadPlugin('loadable');

    expect(defined('ESCALATED_TEST_PLUGIN_LOADED'))->toBeTrue();
});

it('resolves composer plugin paths for loading', function () {
    $vendorDir = base_path('vendor/acme/loadable-plugin');
    File::makeDirectory($vendorDir, 0755, true);
    File::put($vendorDir.'/plugin.json', json_encode([
        'name' => 'Loadable Composer',
        'main_file' => 'Plugin.php',
    ]));
    File::put($vendorDir.'/Plugin.php', '<?php define("ESCALATED_TEST_COMPOSER_LOADED", true);');

    try {
        $this->pluginService->loadPlugin('acme--loadable-plugin');
        expect(defined('ESCALATED_TEST_COMPOSER_LOADED'))->toBeTrue();
    } finally {
        File::deleteDirectory($vendorDir);
    }
});

it('silently skips loading when plugin path does not exist', function () {
    // Should not throw
    $this->pluginService->loadPlugin('nonexistent-slug');
    expect(true)->toBeTrue();
});
