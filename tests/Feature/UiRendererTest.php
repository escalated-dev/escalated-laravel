<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\UI\InertiaUiRenderer;
use Escalated\Laravel\Tests\TestCase;

class UiRendererTest extends TestCase
{
    public function test_renderer_is_bound_when_ui_enabled(): void
    {
        $this->assertTrue(app()->bound(EscalatedUiRenderer::class));
    }

    public function test_renderer_resolves_to_inertia_implementation(): void
    {
        $renderer = app(EscalatedUiRenderer::class);

        $this->assertInstanceOf(InertiaUiRenderer::class, $renderer);
    }

    public function test_renderer_returns_inertia_response(): void
    {
        $renderer = app(EscalatedUiRenderer::class);
        $response = $renderer->render('Escalated/Agent/Dashboard', ['stats' => []]);

        $this->assertInstanceOf(\Inertia\Response::class, $response);
    }

    public function test_custom_renderer_can_be_bound(): void
    {
        $custom = new class implements EscalatedUiRenderer {
            public function render(string $page, array $props = []): mixed
            {
                return response()->json(['page' => $page, 'props' => $props]);
            }
        };

        app()->instance(EscalatedUiRenderer::class, $custom);

        $renderer = app(EscalatedUiRenderer::class);
        $response = $renderer->render('Test/Page', ['foo' => 'bar']);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
    }
}
