<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Bridge\SdkPluginActivation;
use Escalated\Laravel\Exceptions\UnknownPluginException;
use Escalated\Laravel\Facades\Hook;
use Escalated\Laravel\Models\Plugin;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PluginService
{
    protected string $pluginsPath;

    public function __construct()
    {
        $this->pluginsPath = config('escalated.plugins.path', app_path('Plugins/Escalated'));

        // Ensure plugins directory exists
        if (! File::exists($this->pluginsPath)) {
            File::makeDirectory($this->pluginsPath, 0755, true);
        }
    }

    /**
     * Get all installed plugins with their metadata: native plugins (local
     * and Composer) and the SDK plugins the runtime loaded.
     */
    public function getAllPlugins(): array
    {
        $native = array_merge($this->getLocalPlugins(), $this->getComposerPlugins());
        $taken = array_column($native, 'slug');
        $sdk = [];

        foreach ($this->getSdkPlugins() as $plugin) {
            if (in_array($plugin['slug'], $taken, true)) {
                Log::error("Escalated: SDK plugin '{$plugin['name']}' and a native plugin share the slug '{$plugin['slug']}'; the SDK plugin is not listed.");

                continue;
            }
            $sdk[] = $plugin;
        }

        return array_merge($native, $sdk);
    }

    /**
     * Get plugins installed in the local plugins directory.
     */
    protected function getLocalPlugins(): array
    {
        $plugins = [];
        $directories = File::directories($this->pluginsPath);

        foreach ($directories as $directory) {
            $pluginSlug = basename($directory);
            $manifestPath = $directory.'/plugin.json';

            if (File::exists($manifestPath)) {
                $manifest = json_decode(File::get($manifestPath), true);

                // Get activation status from database
                $dbPlugin = Plugin::where('slug', $pluginSlug)->first();

                $plugins[] = [
                    'slug' => $pluginSlug,
                    'name' => $manifest['name'] ?? $pluginSlug,
                    'description' => $manifest['description'] ?? '',
                    'version' => $manifest['version'] ?? '1.0.0',
                    'author' => $manifest['author'] ?? 'Unknown',
                    'author_url' => $manifest['author_url'] ?? '',
                    'requires' => $manifest['requires'] ?? '1.0.0',
                    'main_file' => $manifest['main_file'] ?? 'Plugin.php',
                    'is_active' => $dbPlugin ? $dbPlugin->is_active : false,
                    'activated_at' => $dbPlugin?->activated_at,
                    'path' => $directory,
                    'source' => 'local',
                ];
            }
        }

        return $plugins;
    }

    /**
     * Discover plugins installed via Composer (vendor packages with plugin.json).
     */
    protected function getComposerPlugins(): array
    {
        $plugins = [];
        $pattern = base_path('vendor/*/*/plugin.json');
        $manifests = glob($pattern);

        if (! $manifests) {
            return [];
        }

        foreach ($manifests as $manifestPath) {
            $directory = dirname($manifestPath);
            $manifest = json_decode(File::get($manifestPath), true);

            if (! $manifest) {
                continue;
            }

            // Derive slug from vendor/package path (e.g. "acme/escalated-billing" → "acme--escalated-billing")
            $parts = array_slice(explode('/', str_replace('\\', '/', $directory)), -2);
            $pluginSlug = implode('--', $parts);

            $dbPlugin = Plugin::where('slug', $pluginSlug)->first();

            $plugins[] = [
                'slug' => $pluginSlug,
                'name' => $manifest['name'] ?? $pluginSlug,
                'description' => $manifest['description'] ?? '',
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? 'Unknown',
                'author_url' => $manifest['author_url'] ?? '',
                'requires' => $manifest['requires'] ?? '1.0.0',
                'main_file' => $manifest['main_file'] ?? 'Plugin.php',
                'is_active' => $dbPlugin ? $dbPlugin->is_active : false,
                'activated_at' => $dbPlugin?->activated_at,
                'path' => $directory,
                'source' => 'composer',
            ];
        }

        return $plugins;
    }

    /**
     * Get list of activated plugin slugs.
     */
    public function getActivatedPlugins(): array
    {
        try {
            return Plugin::active()->pluck('slug')->toArray();
        } catch (\Exception $e) {
            Log::debug('Escalated: Could not retrieve activated plugins - table may not exist yet', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Activate a plugin (domain-model/plugin-contract.md, Lifecycle): record
     * it active, let it set itself up (native: load it and fire
     * `escalated_plugin_activated`; SDK: the runtime's `activate`), then fire
     * `plugin.activated`. Activating an active plugin does nothing.
     *
     * @throws UnknownPluginException
     */
    public function activatePlugin(string $slug): bool
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();
        $sdkName = $this->sdkPluginName($slug);

        if ($sdkName === null && $this->resolvePluginPath($slug) === null) {
            throw new UnknownPluginException($slug);
        }

        $plugin = Plugin::where('slug', $slug)->first();

        if ($plugin?->is_active) {
            return true;
        }

        $plugin ??= new Plugin(['slug' => $slug]);
        $plugin->fill([
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
        ])->save();

        if ($sdkName !== null) {
            $this->sdkBridge()?->activate($sdkName);
        } else {
            // Load the plugin first so its hooks are registered
            $this->loadPlugin($slug);

            // Fire activation action hooks
            Hook::doAction('escalated_plugin_activated', $slug);
            Hook::doAction("escalated_plugin_activated_{$slug}");
        }

        $this->fireLifecycle('plugin.activated', $slug, $sdkName);

        return true;
    }

    /**
     * Deactivate a plugin: fire `plugin.deactivated` and let it clean up
     * while it is still active, then record it inactive. Deactivating an
     * inactive plugin does nothing.
     *
     * @throws UnknownPluginException
     */
    public function deactivatePlugin(string $slug): bool
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();
        $sdkName = $this->sdkPluginName($slug);
        $plugin = Plugin::where('slug', $slug)->first();

        if ($plugin === null && $sdkName === null && $this->resolvePluginPath($slug) === null) {
            throw new UnknownPluginException($slug);
        }

        if ($plugin && $plugin->is_active) {
            $this->fireLifecycle('plugin.deactivated', $slug, $sdkName);

            if ($sdkName !== null) {
                $this->sdkBridge()?->deactivate($sdkName);
            } else {
                // Fire deactivation action hooks BEFORE deactivating
                Hook::doAction('escalated_plugin_deactivated', $slug);
                Hook::doAction("escalated_plugin_deactivated_{$slug}");
            }

            $plugin->update([
                'is_active' => false,
                'deactivated_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Delete a plugin. Only local plugins can be deleted.
     */
    public function deletePlugin(string $slug): bool
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();
        // Check if this is a composer plugin — cannot delete those
        $allPlugins = $this->getAllPlugins();
        $pluginData = collect($allPlugins)->firstWhere('slug', $slug);

        if ($pluginData && $pluginData['source'] === 'composer') {
            throw new \Exception(($pluginData['sdk'] ?? false)
                ? 'SDK plugins are installed with npm. Remove the package with npm instead.'
                : 'Composer plugins cannot be deleted. Remove the package via Composer instead.');
        }

        $pluginPath = $this->pluginsPath.'/'.$slug;

        if (! File::exists($pluginPath)) {
            return false;
        }

        $plugin = Plugin::where('slug', $slug)->first();

        // Load plugin so its uninstall hooks can run
        if ($plugin && $plugin->is_active) {
            $this->loadPlugin($slug);
        }

        // Fire uninstall action hooks
        $this->fireLifecycle('plugin.uninstalling', $slug, null);
        Hook::doAction('escalated_plugin_uninstalling', $slug);
        Hook::doAction("escalated_plugin_uninstalling_{$slug}");

        // Deactivate first if active
        $this->deactivatePlugin($slug);

        // Delete database record
        if ($plugin) {
            $plugin->delete();
        }

        // Delete the plugin directory
        File::deleteDirectory($pluginPath);

        return true;
    }

    /**
     * Fire a lifecycle action (`plugin.activated` and so on) with
     * `{plugin, slug}` to active native and SDK plugins. A plugin that throws
     * is logged and never undoes the transition.
     */
    protected function fireLifecycle(string $hook, string $slug, ?string $sdkName): void
    {
        try {
            escalated_do_action($hook, ['plugin' => $sdkName ?? $this->nativePluginName($slug), 'slug' => $slug]);
        } catch (\Throwable $e) {
            Log::error("Escalated: plugin action '{$hook}' failed", ['plugin' => $slug, 'error' => $e->getMessage()]);
        }
    }

    protected function nativePluginName(string $slug): string
    {
        $path = $this->resolvePluginPath($slug);
        $manifest = $path ? json_decode((string) File::get($path.'/plugin.json'), true) : null;

        return is_array($manifest) && is_string($manifest['name'] ?? null) ? $manifest['name'] : $slug;
    }

    /**
     * SDK plugins the runtime loaded, listed with the native ones. They are
     * installed with npm, so their source is `composer` (managed by a package
     * manager) and the admin cannot delete them.
     */
    protected function getSdkPlugins(): array
    {
        $manifests = $this->sdkManifests();

        if ($manifests === []) {
            return [];
        }

        $slugs = array_map(fn ($name) => SdkPluginActivation::slug((string) $name), array_keys($manifests));
        $rows = Plugin::whereIn('slug', $slugs)->get()->keyBy('slug');
        $plugins = [];

        foreach ($manifests as $name => $manifest) {
            $slug = SdkPluginActivation::slug((string) $name);
            $row = $rows->get($slug);
            $pages = (array) ($manifest['pages'] ?? []);

            $plugins[] = [
                'slug' => $slug,
                'name' => (string) $name,
                'description' => $manifest['description'] ?? null,
                'version' => (string) ($manifest['version'] ?? '0.0.0'),
                'author' => $manifest['author'] ?? null,
                'author_url' => $manifest['homepage'] ?? null,
                'homepage' => $manifest['homepage'] ?? null,
                'is_active' => (bool) $row?->is_active,
                'activated_at' => $row?->is_active ? $row->activated_at : null,
                'installed_at' => $row?->created_at,
                'source' => 'composer',
                'sdk' => true,
                'provides' => [
                    'menu_items' => count(array_filter($pages, fn ($page) => is_array($page['menu'] ?? null))),
                    'widgets' => count((array) ($manifest['widgets'] ?? [])),
                    'page_components' => count((array) ($manifest['components'] ?? [])),
                ],
            ];
        }

        return $plugins;
    }

    /**
     * The SDK plugin name behind a slug, or null.
     */
    protected function sdkPluginName(string $slug): ?string
    {
        foreach (array_keys($this->sdkManifests()) as $name) {
            if (SdkPluginActivation::slug((string) $name) === $slug) {
                return (string) $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, array>
     */
    protected function sdkManifests(): array
    {
        return $this->sdkBridge()?->getManifests() ?? [];
    }

    protected function sdkBridge(): ?PluginBridge
    {
        try {
            if (! config('escalated.plugins.sdk_enabled', true) || ! app()->bound(PluginBridge::class)) {
                return null;
            }
            $bridge = app(PluginBridge::class);

            return $bridge->isBooted() ? $bridge : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Upload and extract a plugin ZIP file.
     */
    public function uploadPlugin($file): array
    {
        app(TenantContext::class)->assertPlatformAdmin(request()->user());
        $zip = new ZipArchive;
        $tempPath = storage_path('app/temp/'.$file->getClientOriginalName());

        // Save uploaded file temporarily
        $file->storeAs('temp', $file->getClientOriginalName());

        if ($zip->open($tempPath) !== true) {
            throw new \Exception('Failed to open ZIP file');
        }

        // Get the root folder name from ZIP
        $rootFolder = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            if (strpos($name, '/') !== false) {
                $rootFolder = substr($name, 0, strpos($name, '/'));
                break;
            }
        }

        if (empty($rootFolder)) {
            $zip->close();
            throw new \Exception('Invalid plugin structure');
        }

        // Validate ZIP entries for path traversal (zip slip)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/')) {
                $zip->close();
                throw new \RuntimeException('Plugin ZIP contains unsafe path: '.$entryName);
            }
        }

        // Extract to plugins directory
        $extractPath = $this->pluginsPath.'/'.$rootFolder;

        if (File::exists($extractPath)) {
            $zip->close();
            throw new \Exception('Plugin already exists');
        }

        $zip->extractTo($this->pluginsPath);
        $zip->close();

        // Clean up temp file
        Storage::delete('temp/'.$file->getClientOriginalName());

        // Validate plugin.json exists
        $manifestPath = $extractPath.'/plugin.json';
        if (! File::exists($manifestPath)) {
            File::deleteDirectory($extractPath);
            throw new \Exception('Invalid plugin: missing plugin.json');
        }

        // Validate main_file from manifest doesn't contain path traversal
        $manifest = json_decode(File::get($manifestPath), true);
        $mainFile = $manifest['main_file'] ?? 'Plugin.php';
        if (str_contains($mainFile, '..') || str_starts_with($mainFile, '/')) {
            File::deleteDirectory($extractPath);
            throw new \RuntimeException('Plugin manifest contains unsafe main_file path: '.$mainFile);
        }

        return [
            'slug' => $rootFolder,
            'path' => $extractPath,
        ];
    }

    /**
     * Load all active plugins.
     */
    public function loadActivePlugins(): void
    {
        if (app(TenantContext::class)->enabled()) {
            return;
        }
        $activatedPlugins = $this->getActivatedPlugins();

        foreach ($activatedPlugins as $slug) {
            $this->loadPlugin($slug);
        }
    }

    /**
     * Load a specific plugin by slug. Resolves path from both local and composer sources.
     */
    public function loadPlugin(string $slug): void
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();
        $pluginPath = $this->resolvePluginPath($slug);

        if (! $pluginPath) {
            return;
        }

        $manifestPath = $pluginPath.'/plugin.json';

        if (! File::exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $mainFile = $manifest['main_file'] ?? 'Plugin.php';
        $pluginFile = $pluginPath.'/'.$mainFile;

        if (File::exists($pluginFile)) {
            // Load the plugin file - it will have access to all helper functions
            require_once $pluginFile;

            // Run the plugin's loaded action if it exists
            Hook::doAction('escalated_plugin_loaded', $slug, $manifest);
        }
    }

    /**
     * Resolve the filesystem path for a plugin slug (local or composer).
     */
    protected function resolvePluginPath(string $slug): ?string
    {
        // Check local plugins first
        $localPath = $this->pluginsPath.'/'.$slug;
        if (File::exists($localPath.'/plugin.json')) {
            return $localPath;
        }

        // Check composer plugins — slug uses "vendor--package" format
        if (str_contains($slug, '--')) {
            $vendorPath = base_path('vendor/'.str_replace('--', '/', $slug));
            if (File::exists($vendorPath.'/plugin.json')) {
                return $vendorPath;
            }
        }

        return null;
    }
}
