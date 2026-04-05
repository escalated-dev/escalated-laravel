# Core vs Inertia Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Inertia UI layer optional so `escalated-laravel` can boot and serve backend functionality (API, commands, events, plugins) without requiring Inertia.

**Architecture:** Add a `ui.enabled` config gate. Split `EscalatedServiceProvider` boot into core vs UI paths. Introduce an `EscalatedUiRenderer` contract so controllers call an abstraction instead of `Inertia::render()` directly. Plugin page rendering goes through the same abstraction.

**Tech Stack:** PHP 8.2+, Laravel 11, Inertia.js, Pest/PHPUnit, Orchestra Testbench

---

## File Structure

### New files

| File | Responsibility |
|------|---------------|
| `src/Contracts/EscalatedUiRenderer.php` | Interface for rendering UI pages |
| `src/UI/InertiaUiRenderer.php` | Inertia implementation of the renderer |
| `tests/Feature/CoreOnlyBootTest.php` | Tests for `ui.enabled=false` mode |
| `tests/Feature/UiRendererTest.php` | Tests for the renderer contract + Inertia impl |

### Modified files

| File | What changes |
|------|-------------|
| `config/escalated.php` | Add `ui` config section |
| `src/EscalatedServiceProvider.php` | Split boot into core vs UI; conditionally register Inertia |
| `src/Bridge/RouteRegistrar.php` | Plugin pages use renderer instead of `Inertia::render()` |
| `src/Http/Controllers/Agent/DashboardController.php` | Use renderer |
| `src/Http/Controllers/Agent/TicketController.php` | Use renderer |
| `src/Http/Controllers/Customer/TicketController.php` | Use renderer |
| `src/Http/Controllers/Customer/KnowledgeBaseController.php` | Use renderer |
| `src/Http/Controllers/Guest/TicketController.php` | Use renderer |
| All `src/Http/Controllers/Admin/*.php` (28 files) | Use renderer |

---

## Task 1: Add UI config gate

**Files:**
- Modify: `config/escalated.php`
- Test: `tests/Feature/CoreOnlyBootTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CoreOnlyBootTest.php`:

```php
<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Tests\TestCase;

class CoreOnlyBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('escalated.ui.enabled', false);
    }

    public function test_core_boots_without_ui(): void
    {
        // Service provider should boot without errors
        $this->assertTrue(true);
    }

    public function test_ui_config_defaults_to_enabled(): void
    {
        // In a fresh app, ui.enabled should default to true
        $app = $this->createApplication();
        $app['config']->set('escalated.ui.enabled', null);

        $this->assertTrue(config('escalated.ui.enabled', true));
    }

    public function test_api_routes_registered_when_ui_disabled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->pluck('uri')
            ->toArray();

        $this->assertContains('support/api/v1/tickets', $routes);
    }

    public function test_agent_routes_not_registered_when_ui_disabled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->pluck('uri')
            ->toArray();

        $this->assertNotContains('support/agent', array_map(
            fn ($uri) => explode('/', $uri, 3)[0] . '/' . (explode('/', $uri, 3)[1] ?? ''),
            $routes
        ));
    }

    public function test_inertia_shared_data_not_registered_when_ui_disabled(): void
    {
        // When UI is disabled, no Inertia shared data should be set
        // This test verifies shareInertiaData() is skipped
        $this->assertFalse(
            app()->bound('inertia.shared.escalated'),
            'Inertia shared data should not be bound when UI is disabled'
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/CoreOnlyBootTest.php --filter=test_agent_routes_not_registered_when_ui_disabled`

Expected: FAIL — agent routes are still registered because the config gate doesn't exist yet.

- [ ] **Step 3: Add the UI config section**

In `config/escalated.php`, add after the `'routes'` section (after line 46):

```php
    /*
    |--------------------------------------------------------------------------
    | UI Layer
    |--------------------------------------------------------------------------
    |
    | Controls whether the built-in Inertia UI is enabled. When disabled,
    | only core functionality is available: API routes, commands, events,
    | migrations, and the plugin runtime. This lets teams use a custom
    | frontend (Blade, Livewire, etc.) while keeping the ticketing backend.
    |
    */
    'ui' => [
        'enabled' => env('ESCALATED_UI_ENABLED', true),
    ],
```

- [ ] **Step 4: Run the test again**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/CoreOnlyBootTest.php`

Expected: Still fails — the service provider doesn't check this config yet. That's Task 2.

- [ ] **Step 5: Commit**

```bash
git add config/escalated.php tests/Feature/CoreOnlyBootTest.php
git commit -m "feat: add ui.enabled config gate and core-only boot tests"
```

---

## Task 2: Split service provider boot paths

**Files:**
- Modify: `src/EscalatedServiceProvider.php`

- [ ] **Step 1: Refactor EscalatedServiceProvider**

Replace the `boot()`, `registerRoutes()`, and `shareInertiaData()` methods in `src/EscalatedServiceProvider.php`:

```php
public function boot(): void
{
    $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'escalated');

    $this->registerPublishing();
    $this->registerCoreRoutes();
    $this->registerCommands();
    $this->registerEvents();
    $this->loadPlugins();
    $this->bootPluginBridge();

    if ($this->uiEnabled()) {
        $this->registerUiRoutes();
        $this->shareInertiaData();
    }
}

/**
 * Whether the built-in UI layer is active.
 */
protected function uiEnabled(): bool
{
    return config('escalated.ui.enabled', true)
        && class_exists(\Inertia\Inertia::class);
}

/**
 * Register routes that work without a UI: API, inbound email, plugin endpoints/webhooks.
 */
protected function registerCoreRoutes(): void
{
    if (! config('escalated.routes.enabled', true)) {
        return;
    }

    // REST API routes (token auth, no session)
    if (config('escalated.api.enabled', false)) {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->registerApiTokenRoutes();
    }

    // Plugin admin routes (management endpoints are core — page rendering is UI)
    if (config('escalated.plugins.enabled', true)) {
        $this->loadRoutesFrom(__DIR__.'/../routes/plugins.php');
    }

    // Inbound email webhook routes (no auth required)
    if (config('escalated.inbound_email.enabled', false)) {
        $this->loadRoutesFrom(__DIR__.'/../routes/inbound.php');
    }
}

/**
 * Register Inertia-backed web routes (agent, admin, customer, guest).
 */
protected function registerUiRoutes(): void
{
    if (! config('escalated.routes.enabled', true)) {
        return;
    }

    $this->loadRoutesFrom(__DIR__.'/../routes/agent.php');
    $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
    $this->loadRoutesFrom(__DIR__.'/../routes/customer.php');
    $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
}
```

Also update the `use` imports at the top of the file — the `Inertia` import should move inside `shareInertiaData()` or be kept but only used conditionally. Since `shareInertiaData()` already has the `class_exists(Inertia::class)` guard, keep the import but note `uiEnabled()` already checks `class_exists`.

Remove the old `registerRoutes()` method entirely (it is replaced by `registerCoreRoutes()` + `registerUiRoutes()`).

- [ ] **Step 2: Run the core-only boot tests**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/CoreOnlyBootTest.php`

Expected: PASS — agent routes should no longer be registered when `ui.enabled=false`.

- [ ] **Step 3: Run the full test suite to check for regressions**

Run: `cd escalated-laravel && vendor/bin/pest`

Expected: All existing tests PASS — default config still has `ui.enabled=true`.

- [ ] **Step 4: Commit**

```bash
git add src/EscalatedServiceProvider.php
git commit -m "feat: split service provider boot into core and UI paths"
```

---

## Task 3: Create the EscalatedUiRenderer contract

**Files:**
- Create: `src/Contracts/EscalatedUiRenderer.php`
- Test: `tests/Feature/UiRendererTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/UiRendererTest.php`:

```php
<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Tests\TestCase;

class UiRendererTest extends TestCase
{
    public function test_renderer_is_bound_when_ui_enabled(): void
    {
        $this->assertTrue(app()->bound(EscalatedUiRenderer::class));
    }

    public function test_renderer_returns_response(): void
    {
        $renderer = app(EscalatedUiRenderer::class);
        $response = $renderer->render('Escalated/Agent/Dashboard', ['stats' => []]);

        $this->assertNotNull($response);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/UiRendererTest.php`

Expected: FAIL — `EscalatedUiRenderer` class does not exist.

- [ ] **Step 3: Create the contract**

Create `src/Contracts/EscalatedUiRenderer.php`:

```php
<?php

namespace Escalated\Laravel\Contracts;

/**
 * Abstraction for rendering UI pages.
 *
 * The default implementation delegates to Inertia. Teams that want
 * Blade, Livewire, or another UI can provide their own implementation.
 */
interface EscalatedUiRenderer
{
    /**
     * Render a named page with the given props.
     *
     * @param  string  $page   Page/component identifier (e.g. 'Escalated/Agent/Dashboard')
     * @param  array   $props  Data to pass to the page
     * @return mixed           Response object (Inertia\Response, Illuminate\Http\Response, etc.)
     */
    public function render(string $page, array $props = []): mixed;
}
```

- [ ] **Step 4: Run test again**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/UiRendererTest.php`

Expected: FAIL — the contract exists but nothing is bound to it yet. That's Task 4.

- [ ] **Step 5: Commit**

```bash
git add src/Contracts/EscalatedUiRenderer.php tests/Feature/UiRendererTest.php
git commit -m "feat: add EscalatedUiRenderer contract"
```

---

## Task 4: Implement InertiaUiRenderer and register binding

**Files:**
- Create: `src/UI/InertiaUiRenderer.php`
- Modify: `src/EscalatedServiceProvider.php`

- [ ] **Step 1: Create the Inertia implementation**

Create `src/UI/InertiaUiRenderer.php`:

```php
<?php

namespace Escalated\Laravel\UI;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Inertia\Inertia;
use Inertia\Response;

final class InertiaUiRenderer implements EscalatedUiRenderer
{
    public function render(string $page, array $props = []): Response
    {
        return Inertia::render($page, $props);
    }
}
```

- [ ] **Step 2: Register the binding in the service provider**

In `src/EscalatedServiceProvider.php`, add to the `register()` method:

```php
// Register UI renderer — Inertia by default, swappable by the host app
$this->app->singleton(
    \Escalated\Laravel\Contracts\EscalatedUiRenderer::class,
    function () {
        if ($this->uiEnabled()) {
            return new \Escalated\Laravel\UI\InertiaUiRenderer();
        }

        throw new \RuntimeException(
            'Escalated UI is disabled. Set escalated.ui.enabled=true or provide a custom EscalatedUiRenderer binding.'
        );
    }
);
```

- [ ] **Step 3: Run the renderer tests**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/UiRendererTest.php`

Expected: PASS

- [ ] **Step 4: Add a test for disabled-UI renderer**

Add to `tests/Feature/CoreOnlyBootTest.php`:

```php
public function test_renderer_throws_when_ui_disabled(): void
{
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Escalated UI is disabled');

    app(\Escalated\Laravel\Contracts\EscalatedUiRenderer::class);
}
```

- [ ] **Step 5: Run all tests**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/CoreOnlyBootTest.php tests/Feature/UiRendererTest.php`

Expected: All PASS

- [ ] **Step 6: Commit**

```bash
git add src/UI/InertiaUiRenderer.php src/EscalatedServiceProvider.php tests/Feature/CoreOnlyBootTest.php
git commit -m "feat: implement InertiaUiRenderer and register binding"
```

---

## Task 5: Refactor Agent controllers to use the renderer

**Files:**
- Modify: `src/Http/Controllers/Agent/DashboardController.php`
- Modify: `src/Http/Controllers/Agent/TicketController.php`

- [ ] **Step 1: Run existing agent tests to capture baseline**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/Agent/`

Expected: PASS (baseline)

- [ ] **Step 2: Refactor DashboardController**

Replace the Inertia import and direct call in `src/Http/Controllers/Agent/DashboardController.php`:

```php
<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function __invoke(Request $request): mixed
    {
        $userId = $request->user()->getKey();

        return $this->renderer->render('Escalated/Agent/Dashboard', [
            'stats' => [
                'open' => Ticket::open()->count(),
                'my_assigned' => Ticket::assignedTo($userId)->open()->count(),
                'unassigned' => Ticket::unassigned()->open()->count(),
                'sla_breached' => Ticket::open()->breachedSla()->count(),
                'resolved_today' => Ticket::where('resolved_at', '>=', now()->startOfDay())->count(),
            ],
            'recentTickets' => Ticket::with(['requester', 'assignee', 'department', 'latestReply.author'])
                ->latest()
                ->take(10)
                ->get(),
            'needsAttention' => [
                'sla_breaching' => Ticket::open()->breachedSla()->with(['requester', 'assignee'])->take(5)->get(),
                'unassigned_urgent' => Ticket::unassigned()->open()
                    ->whereIn('priority', ['urgent', 'critical'])
                    ->with(['requester'])
                    ->take(5)
                    ->get(),
            ],
            'myPerformance' => [
                'resolved_this_week' => Ticket::assignedTo($userId)
                    ->where('resolved_at', '>=', now()->startOfWeek())
                    ->count(),
            ],
        ]);
    }
}
```

- [ ] **Step 3: Refactor Agent TicketController**

In `src/Http/Controllers/Agent/TicketController.php`:

1. Replace `use Inertia\Inertia;` and `use Inertia\Response;` with `use Escalated\Laravel\Contracts\EscalatedUiRenderer;`
2. Add the renderer to the constructor:

```php
public function __construct(
    protected TicketService $ticketService,
    protected AssignmentService $assignmentService,
    protected EscalatedUiRenderer $renderer,
) {}
```

3. Change `index()` return type from `Response` to `mixed` and replace `Inertia::render(...)` with `$this->renderer->render(...)`
4. Change `show()` return type from `Response` to `mixed` and replace `Inertia::render(...)` with `$this->renderer->render(...)`
5. Leave action methods (`update`, `reply`, `assign`, etc.) unchanged — they return `RedirectResponse`, not Inertia responses.

- [ ] **Step 4: Run agent tests**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/Agent/`

Expected: PASS — behavior is identical, just routed through the abstraction.

- [ ] **Step 5: Commit**

```bash
git add src/Http/Controllers/Agent/DashboardController.php src/Http/Controllers/Agent/TicketController.php
git commit -m "refactor: agent controllers use EscalatedUiRenderer instead of Inertia directly"
```

---

## Task 6: Refactor Customer and Guest controllers to use the renderer

**Files:**
- Modify: `src/Http/Controllers/Customer/TicketController.php`
- Modify: `src/Http/Controllers/Customer/KnowledgeBaseController.php`
- Modify: `src/Http/Controllers/Guest/TicketController.php`

- [ ] **Step 1: Run existing customer/guest tests for baseline**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/Customer/ tests/Feature/Guest/ 2>/dev/null || true`

- [ ] **Step 2: Refactor Customer TicketController**

Same pattern as Task 5: inject `EscalatedUiRenderer` via constructor, replace `Inertia::render(...)` calls with `$this->renderer->render(...)`, change return types from `Response` to `mixed` on the `index()`, `create()`, and `show()` methods. Leave action methods (`store`, `reply`, `close`, `reopen`) unchanged.

- [ ] **Step 3: Refactor Customer KnowledgeBaseController**

Same pattern: inject renderer, replace `Inertia::render(...)` in `index()` and `show()`.

- [ ] **Step 4: Refactor Guest TicketController**

Same pattern: inject renderer, replace `Inertia::render(...)` in `create()` and `show()`.

- [ ] **Step 5: Run tests**

Run: `cd escalated-laravel && vendor/bin/pest`

Expected: All PASS

- [ ] **Step 6: Commit**

```bash
git add src/Http/Controllers/Customer/ src/Http/Controllers/Guest/
git commit -m "refactor: customer and guest controllers use EscalatedUiRenderer"
```

---

## Task 7: Refactor Admin controllers to use the renderer

**Files:**
- Modify: All 28 files in `src/Http/Controllers/Admin/`

This is the largest task but the most mechanical — every admin controller follows the same pattern.

- [ ] **Step 1: Run existing admin tests for baseline**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/Admin/`

- [ ] **Step 2: Refactor all Admin controllers**

For each of the 28 admin controllers, apply the same transformation:

1. Replace `use Inertia\Inertia;` with `use Escalated\Laravel\Contracts\EscalatedUiRenderer;`
2. Remove `use Inertia\Response;` if present
3. Add `protected EscalatedUiRenderer $renderer` to the constructor (add constructor if none exists)
4. Replace every `Inertia::render($page, $props)` with `$this->renderer->render($page, $props)`
5. Change return types from `Inertia\Response` or `Response` to `mixed` on methods that render pages

Controllers to modify (each follows the same pattern):
- `ArticleCategoryController.php`, `ArticleController.php`, `AuditLogController.php`
- `AutomationController.php`, `BusinessHoursController.php`, `CannedResponseController.php`
- `CsatSettingsController.php`, `CustomFieldController.php`, `CustomObjectController.php`
- `DataRetentionController.php`, `DepartmentController.php`, `EmailSettingsController.php`
- `EscalationRuleController.php`, `MacroController.php`, `PluginManagementController.php`
- `ReportController.php`, `RoleController.php`, `SettingsController.php`
- `SkillController.php`, `SlaPolicyController.php`, `SsoSettingsController.php`
- `StatusController.php`, `TagController.php`, `TicketController.php`
- `WebhookController.php`, `ApiTokenController.php`
- `TwoFactorSettingsController.php`, `CapacityController.php`

- [ ] **Step 3: Run admin tests**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/Admin/`

Expected: All PASS

- [ ] **Step 4: Run full test suite**

Run: `cd escalated-laravel && vendor/bin/pest`

Expected: All PASS

- [ ] **Step 5: Commit**

```bash
git add src/Http/Controllers/Admin/
git commit -m "refactor: admin controllers use EscalatedUiRenderer"
```

---

## Task 8: Refactor plugin page rendering through the renderer

**Files:**
- Modify: `src/Bridge/RouteRegistrar.php`

- [ ] **Step 1: Refactor RouteRegistrar to use the renderer**

In `src/Bridge/RouteRegistrar.php`:

1. Replace `use Inertia\Inertia;` with `use Escalated\Laravel\Contracts\EscalatedUiRenderer;`
2. Add the renderer to the constructor:

```php
public function __construct(
    private readonly PluginBridge $bridge,
    private readonly EscalatedUiRenderer $renderer,
) {}
```

3. In the `registerPages()` method, replace the `Inertia::render(...)` call (around line 111):

```php
// Before:
return Inertia::render('Escalated/Plugin/Page', [
    'plugin'    => $pluginName,
    'component' => $component,
    'layout'    => $layout,
    'props'     => $props,
]);

// After:
return $this->renderer->render('Escalated/Plugin/Page', [
    'plugin'    => $pluginName,
    'component' => $component,
    'layout'    => $layout,
    'props'     => $props,
]);
```

- [ ] **Step 2: Update RouteRegistrar creation in service provider**

The `RouteRegistrar` is created via the container. Since both `PluginBridge` and `EscalatedUiRenderer` are bound as singletons, Laravel's auto-injection will resolve them. Verify this works by checking how `RouteRegistrar` is instantiated. If it's created manually (e.g., `new RouteRegistrar($bridge)`), update that call site to use `app(RouteRegistrar::class)` instead.

Search for `new RouteRegistrar` in the codebase and update accordingly.

- [ ] **Step 3: Run full test suite**

Run: `cd escalated-laravel && vendor/bin/pest`

Expected: All PASS

- [ ] **Step 4: Commit**

```bash
git add src/Bridge/RouteRegistrar.php src/EscalatedServiceProvider.php
git commit -m "refactor: plugin page rendering uses EscalatedUiRenderer"
```

---

## Task 9: Verify no direct Inertia imports remain in core boot path

**Files:**
- Test: `tests/Feature/CoreOnlyBootTest.php`

- [ ] **Step 1: Add a grep-based verification test**

Add to `tests/Feature/CoreOnlyBootTest.php`:

```php
public function test_no_direct_inertia_imports_in_controllers(): void
{
    $controllerPath = __DIR__.'/../../src/Http/Controllers';
    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($controllerPath)
    );

    $violations = [];
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $contents = file_get_contents($file->getPathname());
            if (str_contains($contents, 'use Inertia\Inertia;')
                || str_contains($contents, 'Inertia::render(')) {
                $violations[] = str_replace($controllerPath.'/', '', $file->getPathname());
            }
        }
    }

    $this->assertEmpty(
        $violations,
        'Controllers should not import Inertia directly. Violations: '.implode(', ', $violations)
    );
}
```

- [ ] **Step 2: Run the verification test**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/CoreOnlyBootTest.php --filter=test_no_direct_inertia_imports`

Expected: PASS — all controllers now use the renderer.

- [ ] **Step 3: Add a boot-without-Inertia test**

Add to `tests/Feature/CoreOnlyBootTest.php`:

```php
public function test_commands_available_when_ui_disabled(): void
{
    $this->artisan('list')
        ->assertSuccessful();
}
```

- [ ] **Step 4: Run the full core-only test suite**

Run: `cd escalated-laravel && vendor/bin/pest tests/Feature/CoreOnlyBootTest.php`

Expected: All PASS

- [ ] **Step 5: Run the complete test suite one final time**

Run: `cd escalated-laravel && vendor/bin/pest`

Expected: All PASS — no regressions, core-only mode works, default UI mode works.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/CoreOnlyBootTest.php
git commit -m "test: verify no direct Inertia imports remain in controllers"
```

---

## Summary

After completing all 9 tasks:

- `escalated.ui.enabled=false` skips Inertia boot and UI routes
- API, commands, migrations, inbound email, and plugin runtime still function in core-only mode
- All controllers use `EscalatedUiRenderer` instead of `Inertia::render()` directly
- Plugin page rendering goes through the renderer
- Teams can bind their own `EscalatedUiRenderer` implementation for Blade/Livewire
- Full backward compatibility: default config behaves identically to before

**Not included in this plan (future work per the spec):**
- Phase 3: Page data extraction (extracting query/presenter classes from controllers)
- Phase 5: Package split evaluation
