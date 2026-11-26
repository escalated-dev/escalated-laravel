<?php

use Escalated\Laravel\Console\Commands\InstallCommand;
use Escalated\Laravel\Tests\Fixtures\PlainUser;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;

// Several cases here point escalated.user_model at classes that do not exist,
// which is the point -- resolveUserModelPath() works from the name alone. The
// config outlives the test body though, and Testbench rolls the migrations back
// on teardown: the down migrations ask for the user key type, which
// instantiates the model. On SQLite the rollback does little enough to get away
// with it; on PostgreSQL and MySQL it fails with "Class not found".
afterEach(function () {
    config(['escalated.user_model' => TestUser::class]);
});

function callMethod(object $object, string $method, array $args = []): mixed
{
    $ref = new ReflectionMethod($object, $method);

    return $ref->invoke($object, ...$args);
}

function makeCommand(bool $withOutput = false): InstallCommand
{
    $command = new InstallCommand;
    $command->setLaravel(app());

    if ($withOutput) {
        $output = new OutputStyle(new ArrayInput([]), new NullOutput);
        $command->setOutput($output);

        // Initialize $this->components (normally done by Command::run)
        $ref = new ReflectionProperty($command, 'components');
        $ref->setValue($command, app()->make(ComponentsFactory::class, ['output' => $output]));
    }

    return $command;
}

// --- addImportStatements ---

it('adds import statements after existing use imports', function () {
    $input = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImportStatements', [$input]);

    expect($result)
        ->toContain("use Illuminate\\Notifications\\Notifiable;\nuse Escalated\\Laravel\\Contracts\\HasTickets;\nuse Escalated\\Laravel\\Contracts\\Ticketable;");
});

it('adds imports after namespace when no existing use statements', function () {
    $input = <<<'PHP'
<?php

namespace App\Models;

class User
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImportStatements', [$input]);

    expect($result)
        ->toContain("namespace App\\Models;\n\nuse Escalated\\Laravel\\Contracts\\HasTickets;\nuse Escalated\\Laravel\\Contracts\\Ticketable;");
});

it('skips imports that already exist', function () {
    $input = <<<'PHP'
<?php

namespace App\Models;

use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImportStatements', [$input]);

    expect($result)->toBe($input);
});

// --- addImplementsTicketable ---

it('adds implements clause when none exists', function () {
    $input = <<<'PHP'
<?php

class User extends Authenticatable
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImplementsTicketable', [$input]);

    expect($result)->toContain('class User extends Authenticatable implements Ticketable');
});

it('appends Ticketable to existing implements clause', function () {
    $input = <<<'PHP'
<?php

class User extends Authenticatable implements MustVerifyEmail
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImplementsTicketable', [$input]);

    expect($result)->toContain('implements MustVerifyEmail, Ticketable');
});

it('appends Ticketable to multiple existing interfaces', function () {
    $input = <<<'PHP'
<?php

class User extends Authenticatable implements MustVerifyEmail, CanResetPassword
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImplementsTicketable', [$input]);

    expect($result)->toContain('implements MustVerifyEmail, CanResetPassword, Ticketable');
});

it('skips if Ticketable already in implements clause', function () {
    $input = <<<'PHP'
<?php

class User extends Authenticatable implements Ticketable
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImplementsTicketable', [$input]);

    expect($result)->toBe($input);
});

it('adds implements to class with no extends clause', function () {
    $input = <<<'PHP'
<?php

class User
{
}
PHP;

    $result = callMethod(makeCommand(), 'addImplementsTicketable', [$input]);

    expect($result)->toContain('class User implements Ticketable');
});

// --- addHasTicketsTrait ---

it('appends HasTickets to existing trait use statement', function () {
    $input = <<<'PHP'
<?php

use Escalated\Laravel\Contracts\HasTickets;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
}
PHP;

    $result = callMethod(makeCommand(), 'addHasTicketsTrait', [$input]);

    expect($result)->toContain('use HasFactory, Notifiable, HasTickets;');
});

it('inserts HasTickets trait when no trait use statement exists', function () {
    $input = <<<'PHP'
<?php

class User extends Authenticatable
{
    protected $fillable = ['name'];
}
PHP;

    $result = callMethod(makeCommand(), 'addHasTicketsTrait', [$input]);

    expect($result)->toContain("{\n    use HasTickets;");
});

it('skips if HasTickets trait already used', function () {
    $input = <<<'PHP'
<?php

use Escalated\Laravel\Contracts\HasTickets;

class User extends Authenticatable
{
    use HasFactory, HasTickets;
}
PHP;

    $result = callMethod(makeCommand(), 'addHasTicketsTrait', [$input]);

    expect($result)->toBe($input);
});

it('does not confuse import statement with trait use statement', function () {
    $input = <<<'PHP'
<?php

use Escalated\Laravel\Contracts\HasTickets;

class User extends Authenticatable
{
    use HasFactory;
}
PHP;

    $result = callMethod(makeCommand(), 'addHasTicketsTrait', [$input]);

    expect($result)
        ->toContain('use HasFactory, HasTickets;')
        ->toContain('use Escalated\Laravel\Contracts\HasTickets;');
});

// --- Full pipeline ---

it('transforms a standard Laravel User model correctly', function () {
    $input = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
PHP;

    $command = makeCommand();
    $result = callMethod($command, 'addImportStatements', [$input]);
    $result = callMethod($command, 'addImplementsTicketable', [$result]);
    $result = callMethod($command, 'addHasTicketsTrait', [$result]);

    expect($result)
        ->toContain('use Escalated\Laravel\Contracts\HasTickets;')
        ->toContain('use Escalated\Laravel\Contracts\Ticketable;')
        ->toContain('class User extends Authenticatable implements Ticketable')
        ->toContain('use HasFactory, Notifiable, HasTickets;')
        // Original content preserved
        ->toContain("protected \$fillable = ['name', 'email', 'password'];")
        ->toContain("'password' => 'hashed',");
});

it('transforms a User model with existing implements correctly', function () {
    $input = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;
}
PHP;

    $command = makeCommand();
    $result = callMethod($command, 'addImportStatements', [$input]);
    $result = callMethod($command, 'addImplementsTicketable', [$result]);
    $result = callMethod($command, 'addHasTicketsTrait', [$result]);

    expect($result)
        ->toContain('implements MustVerifyEmail, Ticketable')
        ->toContain('use HasFactory, Notifiable, HasTickets;');
});

it('is idempotent when pipeline runs twice', function () {
    $input = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
}
PHP;

    $command = makeCommand();

    // First pass
    $first = callMethod($command, 'addImportStatements', [$input]);
    $first = callMethod($command, 'addImplementsTicketable', [$first]);
    $first = callMethod($command, 'addHasTicketsTrait', [$first]);

    // Second pass
    $second = callMethod($command, 'addImportStatements', [$first]);
    $second = callMethod($command, 'addImplementsTicketable', [$second]);
    $second = callMethod($command, 'addHasTicketsTrait', [$second]);

    expect($second)->toBe($first);
});

// --- resolveUserModelPath ---

it('resolves user model path from config', function () {
    config(['escalated.user_model' => 'App\\Models\\User']);
    $path = callMethod(makeCommand(), 'resolveUserModelPath');

    expect($path)->toContain('app');
    expect($path)->toContain('Models');
    expect(basename($path))->toBe('User.php');
});

it('resolves custom model path from config', function () {
    config(['escalated.user_model' => 'App\\Models\\Auth\\Customer']);
    $path = callMethod(makeCommand(), 'resolveUserModelPath');

    expect($path)->toContain('app');
    expect(basename($path))->toBe('Customer.php');
});

it('returns null for non-App namespace models', function () {
    config(['escalated.user_model' => 'Custom\\Models\\User']);
    $path = callMethod(makeCommand(), 'resolveUserModelPath');

    expect($path)->toBeNull();
});

// --- existingPublishedMigrations ---

function seedFakeMigrationsDir(array $filenames): string
{
    $dir = sys_get_temp_dir().'/escalated-install-test-'.uniqid();
    mkdir($dir.'/migrations', 0777, true);
    foreach ($filenames as $name) {
        file_put_contents($dir.'/migrations/'.$name, "<?php\n");
    }
    app()->useDatabasePath($dir);

    return $dir;
}

it('returns empty array when no escalated migrations are published yet', function () {
    seedFakeMigrationsDir([
        '2024_01_01_000001_create_users_table.php',
        '2024_01_01_000002_create_password_resets_table.php',
    ]);

    $result = callMethod(makeCommand(), 'existingPublishedMigrations');

    expect($result)->toBe([]);
});

it('detects published escalated migrations by filename pattern', function () {
    seedFakeMigrationsDir([
        '2024_01_01_000001_create_users_table.php',
        '2026_04_19_120000_create_escalated_departments_table.php',
        '2026_04_19_120001_create_escalated_tickets_table.php',
    ]);

    $result = callMethod(makeCommand(), 'existingPublishedMigrations');

    expect($result)->toHaveCount(2);
    expect(basename($result[0]))->toContain('create_escalated_departments_table');
    expect(basename($result[1]))->toContain('create_escalated_tickets_table');
});

it('does not match unrelated migrations that happen to mention escalated', function () {
    seedFakeMigrationsDir([
        '2024_01_01_000001_add_escalated_at_column_to_tickets.php',
        '2026_04_19_120000_create_escalated_departments_table.php',
    ]);

    $result = callMethod(makeCommand(), 'existingPublishedMigrations');

    // Pattern requires _create_escalated_*_table.php — first file must not match.
    expect($result)->toHaveCount(1);
    expect(basename($result[0]))->toContain('create_escalated_departments_table');
});

it('returns empty array when migrations directory does not exist', function () {
    $dir = sys_get_temp_dir().'/escalated-install-test-'.uniqid();
    mkdir($dir, 0777, true);
    app()->useDatabasePath($dir);

    $result = callMethod(makeCommand(), 'existingPublishedMigrations');

    expect($result)->toBe([]);
});

// --- installNpmPackage ---

it('does not abort the installer when npm install times out', function () {
    // Reproduces the reported failure: `npm install` exceeds the Process timeout,
    // which throws ProcessTimedOutException. The installer must degrade to manual
    // instructions rather than letting the exception crash escalated:install.
    Process::preventStrayProcesses();
    Process::fake([
        'npm install*' => function () {
            $process = SymfonyProcess::fromShellCommandline('npm install @escalated-dev/escalated');
            $process->setTimeout(300);

            throw new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL);
        },
    ]);

    // Pre-fix, installNpmPackage lets the Illuminate ProcessTimedOutException
    // propagate and aborts escalated:install. The installer must instead swallow
    // it and degrade to manual instructions.
    $thrown = null;
    try {
        callMethod(makeCommand(true), 'installNpmPackage');
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeNull();
});

it('shows manual instructions when npm install exits non-zero', function () {
    Process::preventStrayProcesses();
    Process::fake([
        'npm install*' => Process::result(output: '', errorOutput: 'E404 not found', exitCode: 1),
    ]);

    $thrown = null;
    try {
        callMethod(makeCommand(true), 'installNpmPackage');
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeNull();
});

// --- enableScheduleAutoRegister ---

/**
 * Run $callback with the application's base path pointed at a scratch
 * directory holding the given .env, and return the .env afterwards.
 */
function withScratchApp(?string $env, Closure $callback, ?string $publishedConfig = null): array
{
    $original = app()->basePath();
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escalated-install-'.bin2hex(random_bytes(6));
    mkdir($dir.DIRECTORY_SEPARATOR.'config', 0777, true);
    if ($env !== null) {
        file_put_contents($dir.DIRECTORY_SEPARATOR.'.env', $env);
    }
    if ($publishedConfig !== null) {
        file_put_contents($dir.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'escalated.php', $publishedConfig);
    }

    app()->setBasePath($dir);
    try {
        $result = $callback();
    } finally {
        app()->setBasePath($original);
    }

    $after = is_file($dir.DIRECTORY_SEPARATOR.'.env') ? file_get_contents($dir.DIRECTORY_SEPARATOR.'.env') : null;
    @unlink($dir.DIRECTORY_SEPARATOR.'.env');
    @unlink($dir.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'escalated.php');
    @rmdir($dir.DIRECTORY_SEPARATOR.'config');
    @rmdir($dir);

    return [$result, $after];
}

it('turns on schedule auto-registration for a new install', function () {
    [$on, $env] = withScratchApp("APP_NAME=Host\n", fn () => callMethod(makeCommand(true), 'enableScheduleAutoRegister'));

    expect($on)->toBeTrue()
        ->and($env)->toBe("APP_NAME=Host\nESCALATED_SCHEDULE_AUTO_REGISTER=true\n");
});

it('leaves a schedule auto-registration value the host already set alone', function () {
    [$on, $env] = withScratchApp("ESCALATED_SCHEDULE_AUTO_REGISTER=false\n", fn () => callMethod(makeCommand(true), 'enableScheduleAutoRegister'));

    expect($on)->toBeFalse()
        ->and($env)->toBe("ESCALATED_SCHEDULE_AUTO_REGISTER=false\n");
});

it('does not claim auto-registration is on when the published config ignores the env value', function () {
    [$on, $env] = withScratchApp(
        "APP_NAME=Host\n",
        fn () => callMethod(makeCommand(true), 'enableScheduleAutoRegister'),
        "<?php return ['scheduling' => ['auto_register' => false]];\n",
    );

    expect($on)->toBeFalse()
        ->and($env)->toContain('ESCALATED_SCHEDULE_AUTO_REGISTER=true');
});

it('reads the auto-registration switch from the environment', function () {
    $config = file_get_contents(dirname(__DIR__, 2).'/config/escalated.php');

    expect($config)->toContain("'auto_register' => (bool) env('ESCALATED_SCHEDULE_AUTO_REGISTER', false)");
});

// --- setupProblems ---

it('reports missing staff gates and a user model without Ticketable', function () {
    config(['escalated.user_model' => PlainUser::class]);

    $problems = callMethod(makeCommand(), 'setupProblems');

    expect($problems)->toHaveCount(3)
        ->and($problems[0])->toContain('`escalated-agent` gate is not defined')
        ->and($problems[1])->toContain('`escalated-admin` gate is not defined')
        ->and($problems[2])->toContain(PlainUser::class.' does not implement Escalated\Laravel\Contracts\Ticketable');
});

it('reports a user model that is not a class', function () {
    config(['escalated.user_model' => 'App\Models\Missing']);

    expect(callMethod(makeCommand(), 'setupProblems'))->toContain(
        'escalated.user_model (App\Models\Missing) is not a class. Set ESCALATED_USER_MODEL to your user model.'
    );
});

it('finds nothing wrong once the gates are defined and the user model is Ticketable', function () {
    Gate::define('escalated-agent', fn () => true);
    Gate::define('escalated-admin', fn () => true);

    expect(callMethod(makeCommand(), 'setupProblems'))->toBe([]);
});

// --- outputSetupInstructions ---

it('prints the Tailwind setup for v3 and for v4', function () {
    $command = new InstallCommand;
    $command->setLaravel(app());
    $buffer = new BufferedOutput;
    $output = new OutputStyle(new ArrayInput([]), $buffer);
    $command->setOutput($output);
    (new ReflectionProperty($command, 'components'))
        ->setValue($command, app()->make(ComponentsFactory::class, ['output' => $output]));

    callMethod($command, 'outputSetupInstructions');

    expect($buffer->fetch())
        ->toContain("'./node_modules/@escalated-dev/escalated/src/**/*.{vue,js}',")
        ->toContain("@source '../../node_modules/@escalated-dev/escalated/src';");
});
