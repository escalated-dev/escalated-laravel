<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Services\PluginService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class PluginCommand extends Command
{
    protected $signature = 'escalated:plugin
        {action : Action to perform (search, install, remove, list, activate, deactivate)}
        {query? : Plugin slug or search term}
        {--force : Overwrite existing plugin on install}';

    protected $description = 'Manage Escalated plugins from the marketplace';

    protected PluginService $pluginService;

    public function handle(PluginService $pluginService): int
    {
        $this->pluginService = $pluginService;

        return match ($this->argument('action')) {
            'search' => $this->handleSearch(),
            'install' => $this->handleInstall(),
            'remove' => $this->handleRemove(),
            'list' => $this->handleList(),
            'activate' => $this->handleActivate(),
            'deactivate' => $this->handleDeactivate(),
            default => $this->handleUnknown(),
        };
    }

    protected function handleSearch(): int
    {
        $query = $this->argument('query');

        if (! $query) {
            $this->error('Please provide a search term.');

            return self::FAILURE;
        }

        $this->info("Searching marketplace for \"{$query}\"...");
        $this->newLine();

        $response = $this->marketplaceRequest('GET', '/plugins', ['q' => $query]);

        if (! $response) {
            return self::FAILURE;
        }

        $plugins = $response['data'] ?? [];

        if (empty($plugins)) {
            $this->warn('No plugins found matching your search.');

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Name', 'Version', 'Author', 'Downloads'],
            collect($plugins)->map(fn ($p) => [
                $p['slug'],
                $p['name'],
                $p['version'] ?? '1.0.0',
                $p['author'] ?? 'Unknown',
                $p['downloads'] ?? 0,
            ])->toArray()
        );

        return self::SUCCESS;
    }

    protected function handleInstall(): int
    {
        $slug = $this->argument('query');

        if (! $slug) {
            $this->error('Please provide a plugin slug to install.');
            $this->line('  Usage: php artisan escalated:plugin install <slug>');

            return self::FAILURE;
        }

        // Check if already installed
        $installed = collect($this->pluginService->getAllPlugins());
        $existing = $installed->firstWhere('slug', $slug);

        if ($existing && ! $this->option('force')) {
            $this->warn("Plugin \"{$slug}\" is already installed.");
            $this->line('  Use --force to reinstall.');

            return self::FAILURE;
        }

        $this->info("Fetching plugin \"{$slug}\" from marketplace...");

        // Get plugin metadata from marketplace
        $response = $this->marketplaceRequest('GET', "/plugins/{$slug}");

        if (! $response) {
            // Fallback: try direct Composer install
            return $this->installViaComposer($slug);
        }

        $plugin = $response['data'] ?? $response;
        $downloadUrl = $plugin['download_url'] ?? null;

        if (! $downloadUrl) {
            $this->warn('No download URL found. Trying Composer install...');

            return $this->installViaComposer($plugin['composer_package'] ?? $slug);
        }

        return $this->installFromUrl($slug, $downloadUrl);
    }

    protected function installFromUrl(string $slug, string $url): int
    {
        $this->components->task('Downloading plugin', function () use ($slug, $url, &$tempPath) {
            $tempDir = storage_path('app/temp');

            if (! File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            $tempPath = $tempDir.'/'.$slug.'.zip';

            $response = Http::timeout(60)->withOptions(['sink' => $tempPath])->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Download failed: HTTP '.$response->status());
            }
        });

        $this->components->task('Extracting plugin', function () use ($slug, &$tempPath) {
            $pluginsPath = config('escalated.plugins.path', app_path('Plugins/Escalated'));

            if (! File::exists($pluginsPath)) {
                File::makeDirectory($pluginsPath, 0755, true);
            }

            $targetPath = $pluginsPath.'/'.$slug;

            // Remove existing if force
            if (File::exists($targetPath) && $this->option('force')) {
                File::deleteDirectory($targetPath);
            }

            $zip = new ZipArchive;

            if ($zip->open($tempPath) !== true) {
                throw new \RuntimeException('Failed to open downloaded ZIP file');
            }

            // Determine root folder in ZIP
            $rootFolder = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_contains($name, '/')) {
                    $rootFolder = substr($name, 0, strpos($name, '/'));
                    break;
                }
            }

            // Extract to temp location first
            $extractPath = storage_path('app/temp/'.$slug.'_extracted');
            $zip->extractTo($extractPath);
            $zip->close();

            // Move the root folder contents to the plugin target
            $sourcePath = $rootFolder ? $extractPath.'/'.$rootFolder : $extractPath;

            if (File::exists($sourcePath)) {
                File::moveDirectory($sourcePath, $targetPath);
            }

            // Clean up
            File::deleteDirectory($extractPath);
            File::delete($tempPath);
        });

        $this->components->task('Validating plugin', function () use ($slug) {
            $pluginsPath = config('escalated.plugins.path', app_path('Plugins/Escalated'));
            $manifestPath = $pluginsPath.'/'.$slug.'/plugin.json';

            if (! File::exists($manifestPath)) {
                // Check for package.json as fallback (npm-style plugins)
                $packageJson = $pluginsPath.'/'.$slug.'/package.json';
                if (! File::exists($packageJson)) {
                    throw new \RuntimeException('Invalid plugin: missing plugin.json or package.json');
                }
            }
        });

        // Ask to activate
        if ($this->components->confirm('Activate the plugin now?', true)) {
            $this->components->task('Activating plugin', function () use ($slug) {
                $this->pluginService->activatePlugin($slug);
            });
        }

        $this->newLine();
        $this->components->info("Plugin \"{$slug}\" installed successfully!");

        return self::SUCCESS;
    }

    protected function installViaComposer(string $package): int
    {
        $this->info("Installing \"{$package}\" via Composer...");

        $this->components->task('Running composer require', function () use ($package) {
            $result = \Illuminate\Support\Facades\Process::run("composer require {$package}");

            if (! $result->successful()) {
                throw new \RuntimeException('Composer install failed: '.$result->errorOutput());
            }
        });

        $this->newLine();
        $this->components->info("Plugin \"{$package}\" installed via Composer!");
        $this->line('  Use: php artisan escalated:plugin activate <slug>');

        return self::SUCCESS;
    }

    protected function handleRemove(): int
    {
        $slug = $this->argument('query');

        if (! $slug) {
            $this->error('Please provide a plugin slug to remove.');

            return self::FAILURE;
        }

        $installed = collect($this->pluginService->getAllPlugins());
        $plugin = $installed->firstWhere('slug', $slug);

        if (! $plugin) {
            $this->error("Plugin \"{$slug}\" is not installed.");

            return self::FAILURE;
        }

        if ($plugin['source'] === 'composer') {
            $this->warn('Composer plugins must be removed via: composer remove <package>');

            return self::FAILURE;
        }

        if (! $this->components->confirm("Remove plugin \"{$slug}\"? This cannot be undone.", false)) {
            return self::SUCCESS;
        }

        $this->components->task('Removing plugin', function () use ($slug) {
            $this->pluginService->deletePlugin($slug);
        });

        $this->newLine();
        $this->components->info("Plugin \"{$slug}\" has been removed.");

        return self::SUCCESS;
    }

    protected function handleList(): int
    {
        $plugins = $this->pluginService->getAllPlugins();

        if (empty($plugins)) {
            $this->warn('No plugins installed.');
            $this->line('  Search for plugins: php artisan escalated:plugin search <term>');

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Name', 'Version', 'Source', 'Status'],
            collect($plugins)->map(fn ($p) => [
                $p['slug'],
                $p['name'],
                $p['version'] ?? '-',
                $p['source'],
                $p['is_active']
                    ? '<fg=green>Active</>'
                    : '<fg=gray>Inactive</>',
            ])->toArray()
        );

        return self::SUCCESS;
    }

    protected function handleActivate(): int
    {
        $slug = $this->argument('query');

        if (! $slug) {
            $this->error('Please provide a plugin slug.');

            return self::FAILURE;
        }

        $this->components->task("Activating \"{$slug}\"", function () use ($slug) {
            $this->pluginService->activatePlugin($slug);
        });

        $this->components->info("Plugin \"{$slug}\" activated.");

        return self::SUCCESS;
    }

    protected function handleDeactivate(): int
    {
        $slug = $this->argument('query');

        if (! $slug) {
            $this->error('Please provide a plugin slug.');

            return self::FAILURE;
        }

        $this->components->task("Deactivating \"{$slug}\"", function () use ($slug) {
            $this->pluginService->deactivatePlugin($slug);
        });

        $this->components->info("Plugin \"{$slug}\" deactivated.");

        return self::SUCCESS;
    }

    protected function handleUnknown(): int
    {
        $this->error("Unknown action: {$this->argument('action')}");
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  search <term>      Search the plugin marketplace');
        $this->line('  install <slug>     Install a plugin from marketplace or Composer');
        $this->line('  remove <slug>      Remove a locally-installed plugin');
        $this->line('  list               List all installed plugins');
        $this->line('  activate <slug>    Activate an installed plugin');
        $this->line('  deactivate <slug>  Deactivate an active plugin');

        return self::FAILURE;
    }

    /**
     * Make a request to the Escalated marketplace API.
     */
    protected function marketplaceRequest(string $method, string $path, array $query = []): ?array
    {
        $baseUrl = config('escalated.marketplace.url', 'https://marketplace.escalated.dev/api/v1');

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'X-Escalated-Version' => config('escalated.version', '0.6.0'),
                ])
                ->{strtolower($method)}($baseUrl.$path, $query);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 404) {
                $this->warn('Plugin not found in marketplace.');
            } else {
                $this->error("Marketplace API error: HTTP {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->warn('Could not connect to marketplace: '.$e->getMessage());
            $this->line('  The marketplace may be unavailable, or you may need to check your network.');
        }

        return null;
    }
}
