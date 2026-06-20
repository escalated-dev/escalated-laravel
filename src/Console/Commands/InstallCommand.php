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
        {--migrations : Only publish migrations}
        {--with-newsletters : Enable the newsletter system non-interactively}
        {--no-newsletters : Disable the newsletter system non-interactively}';

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
        $migrationsRan = false;
        $permissionsSeeded = false;

        if ($publishAll) {
            $this->publishEmailViews($force);

            // Seeding permissions requires the escalated_permissions table that
            // the migrations create. Offer to run both together so users don't
            // hit "table doesn't exist" on a clean install.
            if ($this->confirmRunMigrations()) {
                $migrationsRan = $this->runMigrations();
                if ($migrationsRan) {
                    $permissionsSeeded = $this->seedPermissions();
                }
            }

            $this->installNpmPackage();
            $userModelConfigured = $this->configureUserModel();

            $enableNewsletters = $this->resolveNewsletterChoice();
            $this->writeEnv('ESCALATED_ENABLE_NEWSLETTERS', $enableNewsletters ? 'true' : 'false');
            if ($enableNewsletters) {
                $this->components->task('Seeding newsletter permissions on Admin role', function () {
                    $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
                });
            }
        }

        $this->newLine();
        $this->outputSetupInstructions(
            userModelConfigured: $userModelConfigured,
            migrationsRan: $migrationsRan,
            permissionsSeeded: $permissionsSeeded,
        );

        return self::SUCCESS;
    }

    protected function resolveNewsletterChoice(): bool
    {
        if ($this->option('with-newsletters')) {
            return true;
        }
        if ($this->option('no-newsletters')) {
            return false;
        }
        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm(
            'Enable newsletter system? (admins-only feature for sending broadcasts to contacts)',
            false,
        );
    }

    protected function writeEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            return;
        }
        $contents = file_get_contents($envPath);
        $line = "{$key}={$value}";
        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", $line, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n{$line}\n";
        }
        file_put_contents($envPath, $contents);
    }

    protected function publishConfig(bool $force): void
    {
        $this->components->task(__('escalated::commands.install.publishingConfig'), function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-config',
                '--force' => $force,
            ]);
        });
    }

    protected function publishMigrations(bool $force): void
    {
        $this->components->task(__('escalated::commands.install.publishingMigrations'), function () use ($force) {
            $existing = $this->existingPublishedMigrations();

            if (! empty($existing) && ! $force) {
                $this->components->info(__(
                    'escalated::commands.install.migrationsAlreadyPublished',
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
        $this->components->task(__('escalated::commands.install.publishingViews'), function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-views',
                '--force' => $force,
            ]);
        });
    }

    protected function confirmRunMigrations(): bool
    {
        // Non-interactive contexts (CI, --no-interaction) opt out of running
        // migrations automatically. The on-screen instructions still tell the
        // user what to run themselves.
        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->components->confirm(
            __('escalated::commands.install.runMigrationsConfirm'),
            true
        );
    }

    protected function runMigrations(): bool
    {
        // Print output directly (no `task` component / no `callSilently`) so
        // the user sees the actual SQL error if a migration fails. Swallowing
        // it produces a useless "FAIL" with no diagnosis — see issue #88.
        $this->components->info(__('escalated::commands.install.runningMigrations'));
        try {
            $exit = $this->call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            $this->components->error('migrate threw: '.$e->getMessage());

            return false;
        }

        if ($exit !== 0) {
            $this->components->error(
                'migrate exited with code '.$exit.'. The error is shown above. '
                .'Run `php artisan migrate -vvv` to see the full PDO/SQL detail, '
                .'then re-run this command.'
            );

            return false;
        }

        return true;
    }

    protected function seedPermissions(): bool
    {
        $this->components->info(__('escalated::commands.install.seedingPermissions'));
        try {
            $exit = $this->call('db:seed', [
                '--class' => PermissionSeeder::class,
                '--force' => true,
            ]);
        } catch (\Throwable $e) {
            $this->components->error('db:seed threw: '.$e->getMessage());

            return false;
        }

        if ($exit !== 0) {
            $this->components->error('db:seed exited with code '.$exit.'. The error is shown above.');

            return false;
        }

        return true;
    }

    protected function installNpmPackage(): void
    {
        $this->components->task(__('escalated::commands.install.installingNpm'), function () {
            try {
                // npm install is a network + dependency-resolution operation that
                // routinely exceeds Laravel's default 60s Process timeout. Give it
                // headroom, and catch the timeout (and a missing/offline npm) so the
                // whole installer degrades to manual instructions instead of aborting.
                $result = Process::timeout(300)->run('npm install @escalated-dev/escalated');

                if ($result->successful()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Fall through to the manual-instructions path below.
            }

            $this->components->warn(__('escalated::commands.install.npmManual'));
            $this->line('  npm install @escalated-dev/escalated');

            return false;
        });
    }

    protected function configureUserModel(): bool
    {
        $modelPath = $this->resolveUserModelPath();

        if ($modelPath === null || ! file_exists($modelPath)) {
            $this->components->warn(__('escalated::commands.install.userModelNotFound'));

            return false;
        }

        $contents = file_get_contents($modelPath);

        if ($contents === false) {
            $this->components->warn(__('escalated::commands.install.userModelNotFound'));

            return false;
        }

        if (preg_match('/\bimplements\b[^{]*\bTicketable\b/', $contents)) {
            $this->components->info(__('escalated::commands.install.userModelAlreadyConfigured'));

            return true;
        }

        if (! $this->components->confirm(__('escalated::commands.install.userModelConfirm'), true)) {
            return false;
        }

        try {
            $modified = $this->addImportStatements($contents);
            $modified = $this->addImplementsTicketable($modified);
            $modified = $this->addHasTicketsTrait($modified);

            file_put_contents($modelPath, $modified);

            $this->components->info(__('escalated::commands.install.userModelConfigured'));

            return true;
        } catch (\RuntimeException $e) {
            $this->components->warn(__('escalated::commands.install.userModelFailed', ['error' => $e->getMessage()]));

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

    protected function outputSetupInstructions(
        bool $userModelConfigured = false,
        bool $migrationsRan = false,
        bool $permissionsSeeded = false,
    ): void {
        $this->components->info(__('escalated::commands.install.success'));
        $this->newLine();

        $this->line('  '.__('escalated::commands.install.nextSteps'));
        $this->newLine();

        $step = 1;

        if (! $userModelConfigured) {
            $this->line('  '.$step.'. '.__('escalated::commands.install.stepTicketable'));
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

        $this->line('  '.$step.'. '.__('escalated::commands.install.stepGates'));
        $this->newLine();
        $this->line('     // Laravel 12+: App\Providers\AppServiceProvider::boot()');
        $this->line('     // Laravel 11 and earlier: App\Providers\AuthServiceProvider::boot()');
        $this->line('     Gate::define(\'escalated-admin\', fn ($user) => $user->is_admin);');
        $this->line('     Gate::define(\'escalated-agent\', fn ($user) => $user->is_agent);');
        $this->newLine();
        $step++;

        if (! $migrationsRan) {
            $this->line('  '.$step.'. '.__('escalated::commands.install.stepMigrate'));
            $this->newLine();
            $this->line('     php artisan migrate');
            $this->newLine();
            $step++;
        }

        if (! $permissionsSeeded) {
            $this->line('  '.$step.'. '.__('escalated::commands.install.stepSeed'));
            $this->newLine();
            $this->line('     php artisan db:seed --class="'.PermissionSeeder::class.'"');
            $this->newLine();
            $step++;
        }

        $this->line('  '.$step.'. '.__('escalated::commands.install.stepTailwind'));
        $this->newLine();
        $this->line('     // Tailwind CSS v3 and earlier: tailwind.config.js');
        $this->line('     content: [');
        $this->line('         // ...existing paths,');
        $this->line('         \'./node_modules/@escalated-dev/escalated/src/**/*.vue\',');
        $this->line('     ]');
        $this->newLine();
        $this->line('     // Tailwind CSS v4+: resources/css/app.css');
        $this->line('     @source \'../../node_modules/@escalated-dev/escalated/src/**/*.vue\';');
        $this->newLine();
        $step++;

        $this->line('  '.$step.'. '.__('escalated::commands.install.stepInertia'));
        $this->newLine();
        $this->line('     This assumes Inertia and Vue are already installed and configured.');
        $this->line('     Merge the resolver from the README Frontend Integration section into your existing app.ts.');
        $this->line('     Optionally register EscalatedPlugin there to render Escalated inside your app layout.');
        $this->newLine();
        $step++;

        $this->line('  '.$step.'. '.__('escalated::commands.install.stepVisit'));
        $this->newLine();
    }
}
