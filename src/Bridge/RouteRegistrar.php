<?php

namespace Escalated\Laravel\Bridge;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Reads plugin manifests returned by the runtime and registers Laravel routes.
 *
 * Three route categories are generated per plugin:
 *
 *  - Pages      → Inertia routes:  admin/plugins/{plugin}/{route}
 *  - Endpoints  → API routes:       api/plugins/{plugin}/{path}
 *  - Webhooks   → Public routes:   webhooks/plugins/{plugin}/{path}
 *
 * All routes are registered lazily after the manifest exchange during bridge boot.
 */
class RouteRegistrar
{
    public function __construct(
        private readonly PluginBridge $bridge,
        private readonly EscalatedUiRenderer $renderer,
    ) {}

    /**
     * Register routes for all plugins described in the manifest.
     *
     * @param  array  $manifests  Keyed by plugin name, each containing pages/endpoints/webhooks arrays.
     */
    public function registerAll(array $manifests): void
    {
        foreach ($manifests as $pluginName => $manifest) {
            $this->registerPlugin($pluginName, $manifest);
        }
    }

    /**
     * Register routes for a single plugin.
     */
    public function registerPlugin(string $pluginName, array $manifest): void
    {
        $prefix = config('escalated.routes.prefix', 'support');

        $this->registerPages($pluginName, $manifest['pages'] ?? [], $prefix);
        $this->registerEndpoints($pluginName, $manifest['endpoints'] ?? [], $prefix);
        $this->registerWebhooks($pluginName, $manifest['webhooks'] ?? [], $prefix);
    }

    // -------------------------------------------------------------------------
    // Pages — Inertia routes
    // -------------------------------------------------------------------------

    /**
     * Register plugin admin page routes.
     *
     * Each page in the manifest becomes an Inertia route:
     *   GET {prefix}/admin/plugins/{pluginName}/{route}
     *
     * The route renders the generic Escalated/Plugin/Page Inertia component,
     * passing the plugin name, component name, and initial props fetched from
     * the plugin's own endpoint.
     */
    private function registerPages(string $pluginName, array $pages, string $prefix): void
    {
        if (empty($pages)) {
            return;
        }

        $middleware = array_merge(
            config('escalated.routes.admin_middleware', ['web', 'auth']),
            [EnsureIsAdmin::class]
        );

        Route::middleware($middleware)
            ->prefix("{$prefix}/admin/plugins/{$pluginName}")
            ->group(function () use ($pluginName, $pages) {
                foreach ($pages as $page) {
                    $route = ltrim($page['route'] ?? '', '/');
                    $component = $page['component'] ?? '';
                    $layout = $page['layout'] ?? 'admin';
                    $capability = $page['capability'] ?? null;

                    if (empty($route) || empty($component)) {
                        Log::warning('Escalated PluginBridge: skipping page with missing route or component', [
                            'plugin' => $pluginName,
                            'page' => $page,
                        ]);

                        continue;
                    }

                    $routeName = "escalated.plugin.{$pluginName}.page.{$route}";

                    Route::get($route, function () use ($pluginName, $component, $layout, $capability) {
                        if ($capability !== null) {
                            abort_unless(
                                auth()->check() && auth()->user()->can($capability),
                                403
                            );
                        }

                        // Fetch initial props from the plugin's GET /settings-style endpoint.
                        // We attempt a best-effort props fetch; on failure we pass empty props.
                        $props = [];
                        try {
                            $props = $this->bridge->callEndpoint($pluginName, 'GET', '/'.$component, []);
                        } catch (\Throwable) {
                            // No matching endpoint for this page — props remain empty.
                        }

                        return $this->renderer->render('Escalated/Plugin/Page', [
                            'plugin' => $pluginName,
                            'component' => $component,
                            'layout' => $layout,
                            'props' => $props,
                        ]);
                    })->name($routeName);
                }
            });
    }

    // -------------------------------------------------------------------------
    // Endpoints — API routes
    // -------------------------------------------------------------------------

    /**
     * Register plugin data endpoint routes.
     *
     * Each endpoint key is "{METHOD} {path}", e.g. "GET /settings".
     * Generates:  {METHOD} {prefix}/api/plugins/{pluginName}{path}
     */
    private function registerEndpoints(string $pluginName, array $endpoints, string $prefix): void
    {
        if (empty($endpoints)) {
            return;
        }

        $middleware = array_merge(
            config('escalated.routes.admin_middleware', ['web', 'auth']),
            [EnsureIsAdmin::class]
        );

        Route::middleware($middleware)
            ->prefix("{$prefix}/api/plugins/{$pluginName}")
            ->group(function () use ($pluginName, $endpoints) {
                foreach ($endpoints as $signature => $definition) {
                    [$httpMethod, $path] = $this->parseSignature($signature);

                    if ($httpMethod === null) {
                        continue;
                    }

                    $capability = $definition['capability'] ?? null;
                    $routeName = "escalated.plugin.{$pluginName}.endpoint.".strtolower($httpMethod).str_replace('/', '.', $path);

                    Route::match(
                        [strtolower($httpMethod)],
                        ltrim($path, '/'),
                        function (Request $request) use ($pluginName, $httpMethod, $path, $capability) {
                            if ($capability !== null) {
                                abort_unless(
                                    auth()->check() && auth()->user()->can($capability),
                                    403
                                );
                            }

                            $result = $this->bridge->callEndpoint(
                                $pluginName,
                                $httpMethod,
                                $path,
                                [
                                    'body' => $request->all(),
                                    'params' => $request->query(),
                                    'headers' => $request->headers->all(),
                                ]
                            );

                            return response()->json($result);
                        }
                    )->name($routeName);
                }
            });
    }

    // -------------------------------------------------------------------------
    // Webhooks — public routes (no auth)
    // -------------------------------------------------------------------------

    /**
     * Register plugin webhook routes.
     *
     * Webhooks are public routes (no auth). Signature verification is the
     * plugin's responsibility within its handler.
     *
     * Generates:  {METHOD} {prefix}/webhooks/plugins/{pluginName}{path}
     */
    private function registerWebhooks(string $pluginName, array $webhooks, string $prefix): void
    {
        if (empty($webhooks)) {
            return;
        }

        Route::middleware(['api'])
            ->prefix("{$prefix}/webhooks/plugins/{$pluginName}")
            ->group(function () use ($pluginName, $webhooks) {
                foreach ($webhooks as $signature => $definition) {
                    [$httpMethod, $path] = $this->parseSignature($signature);

                    if ($httpMethod === null) {
                        continue;
                    }

                    $routeName = "escalated.plugin.{$pluginName}.webhook.".strtolower($httpMethod).str_replace('/', '.', $path);

                    Route::match(
                        [strtolower($httpMethod)],
                        ltrim($path, '/'),
                        function (Request $request) use ($pluginName, $httpMethod, $path) {
                            $result = $this->bridge->callWebhook(
                                $pluginName,
                                $httpMethod,
                                $path,
                                $request->all(),
                                $request->headers->all()
                            );

                            return response()->json($result ?? []);
                        }
                    )->name($routeName);
                }
            });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Parse an endpoint signature like "GET /settings" into [method, path].
     *
     * @return array{string|null, string}
     */
    private function parseSignature(string $signature): array
    {
        $parts = explode(' ', trim($signature), 2);

        if (count($parts) !== 2) {
            Log::warning("Escalated PluginBridge: could not parse endpoint signature '{$signature}'");

            return [null, '/'];
        }

        $method = strtoupper($parts[0]);
        $path = $parts[1];

        $valid = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        if (! in_array($method, $valid, true)) {
            Log::warning("Escalated PluginBridge: unsupported HTTP method '{$method}' in '{$signature}'");

            return [null, $path];
        }

        return [$method, $path];
    }
}
