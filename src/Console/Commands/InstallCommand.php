<?php

namespace Escalated\Laravel\Console\Commands;

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

        if ($publishAll) {
            $this->publishEmailViews($force);
            $this->installNpmPackage();
        }

        $this->newLine();
        $this->outputSetupInstructions();

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
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-migrations',
                '--force' => $force,
            ]);
        });
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

    protected function outputSetupInstructions(): void
    {
        $this->components->info(__('escalated::commands.install.success'));
        $this->newLine();

        $this->line('  '.__('escalated::commands.install.next_steps'));
        $this->newLine();
        $this->line('  '.__('escalated::commands.install.step1'));
        $this->newLine();
        $this->line('     use Escalated\Laravel\Contracts\HasTickets;');
        $this->line('     use Escalated\Laravel\Contracts\Ticketable;');
        $this->newLine();
        $this->line('     class User extends Authenticatable implements Ticketable');
        $this->line('     {');
        $this->line('         use HasTickets;');
        $this->line('     }');
        $this->newLine();
        $this->line('  '.__('escalated::commands.install.step2'));
        $this->newLine();
        $this->line('     Gate::define(\'escalated-admin\', fn ($user) => $user->is_admin);');
        $this->line('     Gate::define(\'escalated-agent\', fn ($user) => $user->is_agent);');
        $this->newLine();
        $this->line('  '.__('escalated::commands.install.step3'));
        $this->newLine();
        $this->line('     php artisan migrate');
        $this->newLine();
        $this->line('  '.__('escalated::commands.install.step4'));
        $this->newLine();
        $this->line('     // tailwind.config.js');
        $this->line('     content: [');
        $this->line('         // ...existing paths,');
        $this->line('         \'./node_modules/@escalated-dev/escalated/src/**/*.vue\',');
        $this->line('     ]');
        $this->newLine();
        $this->line('  '.__('escalated::commands.install.step5'));
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
        $this->line('  '.__('escalated::commands.install.step6'));
        $this->newLine();
    }
}
