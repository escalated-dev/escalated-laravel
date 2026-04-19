<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class InstallCommand extends Command
{
    protected $signature = 'escalated:install
        {--force : Overwrite existing files}
        {--config : Only publish configuration}
        {--migrations : Only publish migrations}';

    protected $description = 'Install the Escalated support ticket system';

    public function handle(): int
    {
        $this->info(__('escalated::commands.install.installing'));
        $this->newLine();

        $force = $this->option('force');
        $onlyConfig = $this->option('config');
        $onlyMigrations = $this->option('migrations');
        $publishAll = ! $onlyConfig && ! $onlyMigrations;

        if ($publishAll || $onlyConfig) {
            $this->publishConfig($force);
        }

        if ($publishAll || $onlyMigrations) {
            $this->publishMigrations($force);
        }

        $userModelConfigured = false;

        if ($publishAll) {
            $this->publishEmailViews($force);
            $this->seedPermissions();
            $this->installNpmPackage();
            $userModelConfigured = $this->configureUserModel();
        }

        $this->newLine();
        $this->outputSetupInstructions($userModelConfigured);

        return self::SUCCESS;
    }

    protected function publishConfig(bool $force): void
    {
        $this->components->task(__('escalated::commands.install.publishing_config'), function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-config',
                '--force' => $force,
            ]);
        });
    }

    protected function publishMigrations(bool $force): void
    {
        $this->components->task(__('escalated::commands.install.publishing_migrations'), function () use ($force) {
            $existing = $this->existingPublishedMigrations();

            if (! empty($existing) && ! $force) {
                $this->components->info(__(
                    'escalated::commands.install.migrations_already_published',
                    ['count' => count($existing)]
                ));

                return;
            }

            if (! empty($existing) && $force) {
                foreach ($existing as $path) {
                    @unlink($path);
                }
            }

            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-migrations',
                '--force' => $force,
            ]);
        });
    }

    /**
     * Returns absolute paths of already-published Escalated migration files in
     * the host app's database/migrations directory. A migration counts as
     * "published by Escalated" if its filename ends in _create_escalated_*_table.php.
     *
     * @return array<int, string>
     */
    protected function existingPublishedMigrations(): array
    {
        $migrationsDir = database_path('migrations');

        if (! is_dir($migrationsDir)) {
            return [];
        }

        $matches = glob($migrationsDir.'/*_create_escalated_*_table.php') ?: [];

        return array_values($matches);
    }

    protected function publishEmailViews(bool $force): void
    {
        $this->components->task(__('escalated::commands.install.publishing_views'), function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-views',
                '--force' => $force,
            ]);
        });
    }

    protected function seedPermissions(): void
    {
        $this->components->task(__('escalated::commands.install.seeding_permissions', [], 'Seeding permissions and roles'), function () {
            $this->callSilently('db:seed', [
                '--class' => PermissionSeeder::class,
            ]);
        });
    }

    protected function installNpmPackage(): void
    {
        $this->components->task(__('escalated::commands.install.installing_npm'), function () {
            $result = Process::run('npm install @escalated-dev/escalated');

            if (! $result->successful()) {
                $this->components->warn(__('escalated::commands.install.npm_manual'));
                $this->line('  npm install @escalated-dev/escalated');

                return false;
            }
        });
    }

    protected function configureUserModel(): bool
    {
        $modelPath = $this->resolveUserModelPath();

        if ($modelPath === null || ! file_exists($modelPath)) {
            $this->components->warn(__('escalated::commands.install.user_model_not_found'));

            return false;
        }

        $contents = file_get_contents($modelPath);

        if ($contents === false) {
            $this->components->warn(__('escalated::commands.install.user_model_not_found'));

            return false;
        }

        if (preg_match('/\bimplements\b[^{]*\bTicketable\b/', $contents)) {
            $this->components->info(__('escalated::commands.install.user_model_already_configured'));

            return true;
        }

        if (! $this->components->confirm(__('escalated::commands.install.user_model_confirm'), true)) {
            return false;
        }

        try {
            $modified = $this->addImportStatements($contents);
            $modified = $this->addImplementsTicketable($modified);
            $modified = $this->addHasTicketsTrait($modified);

            file_put_contents($modelPath, $modified);

            $this->components->info(__('escalated::commands.install.user_model_configured'));

            return true;
        } catch (\RuntimeException $e) {
            $this->components->warn(__('escalated::commands.install.user_model_failed', ['error' => $e->getMessage()]));

            return false;
        }
    }

    protected function resolveUserModelPath(): ?string
    {
        $modelClass = config('escalated.user_model', 'App\\Models\\User');

        if (str_starts_with($modelClass, 'App\\')) {
            $relativePath = str_replace('\\', '/', substr($modelClass, 4));

            return base_path('app/'.$relativePath.'.php');
        }

        return null;
    }

    protected function addImportStatements(string $contents): string
    {
        $hasTicketsImport = 'use Escalated\Laravel\Contracts\HasTickets;';
        $ticketableImport = 'use Escalated\Laravel\Contracts\Ticketable;';

        $needsHasTickets = ! str_contains($contents, $hasTicketsImport);
        $needsTicketable = ! str_contains($contents, $ticketableImport);

        if (! $needsHasTickets && ! $needsTicketable) {
            return $contents;
        }

        $newImports = '';
        if ($needsHasTickets) {
            $newImports .= $hasTicketsImport."\n";
        }
        if ($needsTicketable) {
            $newImports .= $ticketableImport."\n";
        }

        $classPos = $this->findClassDeclarationPosition($contents);

        if ($classPos === false) {
            throw new \RuntimeException('Could not find class declaration in User model.');
        }

        $headerSection = substr($contents, 0, $classPos);

        if (preg_match_all('/^use\s+[^;]+;/m', $headerSection, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPosition = $lastMatch[1] + strlen(rtrim($lastMatch[0]));

            return substr($contents, 0, $insertPosition)."\n".$newImports.substr($contents, $insertPosition);
        }

        if (preg_match('/^namespace\s+[^;]+;/m', $contents, $nsMatch, PREG_OFFSET_CAPTURE)) {
            $insertPosition = $nsMatch[0][1] + strlen($nsMatch[0][0]);

            return substr($contents, 0, $insertPosition)."\n\n".$newImports.substr($contents, $insertPosition);
        }

        throw new \RuntimeException('Could not determine where to insert import statements.');
    }

    protected function addImplementsTicketable(string $contents): string
    {
        if (preg_match('/\bimplements\b[^{]*\bTicketable\b/', $contents)) {
            return $contents;
        }

        if (preg_match('/\bimplements\s+([^{]+)/s', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $trimmed = rtrim($match[1][0]);
            $insertPos = $match[1][1] + strlen($trimmed);

            return substr($contents, 0, $insertPos).', Ticketable'.substr($contents, $insertPos);
        }

        if (preg_match('/(class\s+\w+\s+extends\s+[\w\\\\]+)/', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[1][1] + strlen($match[1][0]);

            return substr($contents, 0, $insertPos).' implements Ticketable'.substr($contents, $insertPos);
        }

        if (preg_match('/(class\s+\w+)(\s*\{)/', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[1][1] + strlen($match[1][0]);

            return substr($contents, 0, $insertPos).' implements Ticketable'.substr($contents, $insertPos);
        }

        throw new \RuntimeException('Could not find class declaration to add implements Ticketable.');
    }

    protected function addHasTicketsTrait(string $contents): string
    {
        $classPos = $this->findClassDeclarationPosition($contents);

        if ($classPos === false) {
            throw new \RuntimeException('Could not find class declaration.');
        }

        $bracePos = strpos($contents, '{', $classPos);

        if ($bracePos === false) {
            throw new \RuntimeException('Could not find opening brace of class.');
        }

        $classBody = substr($contents, $bracePos);

        // Check within class body only to avoid matching import statements
        if (preg_match('/^\s*use\s+[^;]*\bHasTickets\b[^;]*;/m', $classBody)) {
            return $contents;
        }

        if (preg_match('/^(\s*use\s+)([^;]+)(;)/m', $classBody, $match, PREG_OFFSET_CAPTURE)) {
            $traitListEnd = $bracePos + $match[2][1] + strlen($match[2][0]);

            return substr($contents, 0, $traitListEnd).', HasTickets'.substr($contents, $traitListEnd);
        }

        $afterBrace = substr($contents, $bracePos + 1, 200);
        $indent = '    ';
        if (preg_match('/\n([ \t]+)\S/', $afterBrace, $indentMatch)) {
            $indent = $indentMatch[1];
        }

        $insertPos = $bracePos + 1;

        return substr($contents, 0, $insertPos)."\n".$indent.'use HasTickets;'.substr($contents, $insertPos);
    }

    protected function findClassDeclarationPosition(string $contents): int|false
    {
        if (preg_match('/^(?:abstract\s+|final\s+)?class\s+\w+/m', $contents, $match, PREG_OFFSET_CAPTURE)) {
            return $match[0][1];
        }

        return false;
    }

    protected function outputSetupInstructions(bool $userModelConfigured = false): void
    {
        $this->components->info(__('escalated::commands.install.success'));
        $this->newLine();

        $this->line('  '.__('escalated::commands.install.next_steps'));
        $this->newLine();

        $step = 1;

        if (! $userModelConfigured) {
            $this->line('  '.$step.'. '.__('escalated::commands.install.step_ticketable'));
            $this->newLine();
            $this->line('     use Escalated\Laravel\Contracts\HasTickets;');
            $this->line('     use Escalated\Laravel\Contracts\Ticketable;');
            $this->newLine();
            $this->line('     class User extends Authenticatable implements Ticketable');
            $this->line('     {');
            $this->line('         use HasTickets;');
            $this->line('     }');
            $this->newLine();
            $step++;
        }

        $this->line('  '.$step.'. '.__('escalated::commands.install.step_gates'));
        $this->newLine();
        $this->line('     Gate::define(\'escalated-admin\', fn ($user) => $user->is_admin);');
        $this->line('     Gate::define(\'escalated-agent\', fn ($user) => $user->is_agent);');
        $this->newLine();
        $step++;

        $this->line('  '.$step.'. '.__('escalated::commands.install.step_migrate'));
        $this->newLine();
        $this->line('     php artisan migrate');
        $this->newLine();
        $step++;

        $this->line('  '.$step.'. '.__('escalated::commands.install.step_tailwind'));
        $this->newLine();
        $this->line('     // tailwind.config.js');
        $this->line('     content: [');
        $this->line('         // ...existing paths,');
        $this->line('         \'./node_modules/@escalated-dev/escalated/src/**/*.vue\',');
        $this->line('     ]');
        $this->newLine();
        $step++;

        $this->line('  '.$step.'. '.__('escalated::commands.install.step_inertia'));
        $this->newLine();
        $this->line('     import { EscalatedPlugin } from \'@escalated-dev/escalated\';');
        $this->newLine();
        $this->line('     // In createInertiaApp resolve:');
        $this->line('     const escalatedPages = import.meta.glob(');
        $this->line('         \'../../node_modules/@escalated-dev/escalated/src/pages/**/*.vue\'');
        $this->line('     );');
        $this->line('     resolve: (name) => {');
        $this->line('         if (name.startsWith(\'Escalated/\')) {');
        $this->line('             const path = name.replace(\'Escalated/\', \'\');');
        $this->line('             return resolvePageComponent(');
        $this->line('                 `../../node_modules/@escalated-dev/escalated/src/pages/${path}.vue`,');
        $this->line('                 escalatedPages');
        $this->line('             );');
        $this->line('         }');
        $this->line('         // ...existing resolver');
        $this->line('     }');
        $this->newLine();
        $this->line('     // In setup:');
        $this->line('     app.use(EscalatedPlugin, { layout: YourAppLayout })');
        $this->newLine();
        $step++;

        $this->line('  '.$step.'. '.__('escalated::commands.install.step_visit'));
        $this->newLine();
    }
}
