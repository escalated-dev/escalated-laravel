<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Support\ConnectionInspector;
use Escalated\Laravel\Support\ConnectionStore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Admin screen for the database Escalated's own tables live on.
 *
 * Two things make this safe enough to expose in a web panel at all:
 *
 * The choice is not stored in the database it selects. It cannot be -- reading
 * it back would mean already knowing it. It goes to a file, so pointing
 * Escalated at an empty database does not also destroy the only record of how
 * to point it back.
 *
 * And a switch is refused unless the target is reachable AND already migrated.
 * Escalated on an unmigrated database does not error; it renders a panel with
 * no tickets, no departments and no settings, which reads exactly like data
 * loss. Refusing up front is the difference between a configuration screen and
 * a trap.
 *
 * `config('escalated.connection')` still wins. A host that pins the connection
 * in config or .env has deployed infrastructure, and this screen shows the
 * setting as locked rather than accepting a change it would silently ignore.
 */
class DatabaseConnectionController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
        protected ConnectionInspector $inspector,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Settings/DatabaseConnection', [
            'connections' => $this->inspector->all(),
            'current' => Escalated::connection(),
            'hostDefault' => config('database.default'),
            'pinnedByConfig' => Escalated::connectionIsPinnedByConfig(),
            'tablePrefix' => Escalated::tablePrefix(),
        ]);
    }

    /**
     * Probe one connection without changing anything, so an admin can check a
     * target before committing to it.
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'connection' => ['required', 'string', 'max:64'],
        ]);

        $this->assertConfigured($validated['connection']);

        return response()->json(
            $this->inspector->describe($validated['connection'])
        );
    }

    public function update(Request $request)
    {
        if (Escalated::connectionIsPinnedByConfig()) {
            // Not a validation failure -- the request is well formed, the
            // screen is simply not in charge here.
            return redirect()->back()->with(
                'error',
                'The database connection is set in configuration (escalated.connection) and cannot be changed from here.'
            );
        }

        $validated = $request->validate([
            // Empty string clears the override and returns Escalated to the
            // host's default connection.
            'connection' => ['present', 'nullable', 'string', 'max:64'],
        ]);

        $connection = $validated['connection'] ?: null;

        if ($connection !== null) {
            $this->assertConfigured($connection);

            $described = $this->inspector->describe($connection);

            if (! $described['reachable']) {
                throw ValidationException::withMessages([
                    'connection' => "Could not connect to [{$connection}]: ".($described['error'] ?? 'unknown error'),
                ]);
            }

            if (! $described['migrated']) {
                throw ValidationException::withMessages([
                    'connection' => "[{$connection}] has no Escalated tables. Run the package migrations against it "
                        .'before switching, or the panel will come up empty as though its data had been deleted.',
                ]);
            }
        }

        ConnectionStore::put($connection);

        $message = $connection === null
            ? 'Escalated is now using the application default database connection.'
            : "Escalated is now using the [{$connection}] database connection.";

        return redirect()->back()->with('success', $message.' Existing data was not moved.');
    }

    /**
     * A connection must exist in config/database.php. Anything else is a name
     * the host never configured, and resolving it would throw somewhere far
     * less helpful than here.
     */
    protected function assertConfigured(string $connection): void
    {
        if (! array_key_exists($connection, config('database.connections', []))) {
            throw ValidationException::withMessages([
                'connection' => "[{$connection}] is not configured in config/database.php.",
            ]);
        }
    }
}
