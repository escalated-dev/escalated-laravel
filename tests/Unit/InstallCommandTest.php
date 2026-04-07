<?php

use Escalated\Laravel\Console\Commands\InstallCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

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
